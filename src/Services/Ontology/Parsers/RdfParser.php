<?php

namespace App\Services\Ontology\Parsers;

use App\Services\Ontology\Exceptions\OntologyImportException;
use App\Services\Ontology\Parsers\Contracts\RdfFormatHandlerInterface;
use App\Services\Ontology\Parsers\Extractors\ClassExtractor;
use App\Services\Ontology\Parsers\Extractors\PrefixExtractor;
use App\Services\Ontology\Parsers\Extractors\PropertyExtractor;
use App\Services\Ontology\Parsers\Extractors\ShapeExtractor;
use App\Services\Ontology\Parsers\Handlers\JsonLdHandler;
use App\Services\Ontology\Parsers\Handlers\NTriplesHandler;
use App\Services\Ontology\Parsers\Handlers\RdfXmlHandler;
use App\Services\Ontology\Parsers\Handlers\TurtleHandler;
use App\Services\Ontology\Parsers\ValueObjects\ParsedRdf;

/**
 * RDF Parser - Orchestrates RDF format parsing and entity extraction.
 *
 * This parser uses a handler-extractor pattern to support multiple RDF formats
 * and maintain separation of concerns:
 * - Format handlers: Parse different RDF syntaxes (RDF/XML, Turtle, JSON-LD, N-Triples)
 * - Extractors: Extract semantic entities (prefixes, classes, properties, shapes)
 *
 * Reduced from 1,191 lines to ~260 lines through strategic refactoring.
 */
class RdfParser implements OntologyParserInterface
{
    /**
     * @var RdfFormatHandlerInterface[]
     */
    private array $handlers = [];

    public function __construct(
        private readonly PrefixExtractor $prefixExtractor,
        private readonly ClassExtractor $classExtractor,
        private readonly PropertyExtractor $propertyExtractor,
        private readonly ShapeExtractor $shapeExtractor
    ) {
        $this->registerDefaultHandlers();
    }

    /**
     * Parse RDF content and extract ontology entities.
     */
    public function parse(string $content, array $options = []): array
    {
        try {
            // Select appropriate format handler
            $handler = $this->getHandler($content, $options);

            // Parse content into structured RDF representation
            $parsedRdf = $handler->parse($content);

            // Extract ontology entities using specialized extractors
            return [
                'metadata' => $this->buildMetadata($parsedRdf, $handler),
                'prefixes' => $this->prefixExtractor->extract($parsedRdf),
                'classes' => $this->classExtractor->extract($parsedRdf),
                'properties' => $this->propertyExtractor->extract($parsedRdf),
                'shapes' => $this->shapeExtractor->extract($parsedRdf),
                'restrictions' => $this->extractGraphRestrictions($parsedRdf),
                'raw_content' => $content,
            ];
        } catch (\Throwable $e) {
            throw new OntologyImportException('RDF parsing failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if this parser can handle the given content.
     */
    public function canParse(string $content): bool
    {
        $content = trim($content);

        // Check for common RDF format indicators
        return str_starts_with($content, '<?xml') ||
               str_starts_with($content, '<rdf:RDF') ||
               str_starts_with($content, '@prefix') ||
               str_contains($content, '@prefix') ||
               (str_starts_with($content, '{') && str_contains($content, '@context'));
    }

    /**
     * Get list of supported RDF formats.
     */
    public function getSupportedFormats(): array
    {
        return ['rdf/xml', 'turtle', 'json-ld', 'n-triples', 'rdfa'];
    }

    /**
     * Register a custom format handler.
     */
    public function registerHandler(RdfFormatHandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
    }

    /**
     * Get appropriate handler for the given content.
     */
    private function getHandler(string $content, array $options): RdfFormatHandlerInterface
    {
        // If format is explicitly specified in options, find matching handler
        if (isset($options['format'])) {
            foreach ($this->handlers as $handler) {
                if ($handler->canHandle($content) && $handler->getFormatName() === $options['format']) {
                    return $handler;
                }
            }
        }

        // Auto-detect format by trying each handler
        foreach ($this->handlers as $handler) {
            if ($handler->canHandle($content)) {
                return $handler;
            }
        }

        // Fallback to RDF/XML handler (most common)
        return new RdfXmlHandler;
    }

    /**
     * Register default format handlers in priority order.
     */
    private function registerDefaultHandlers(): void
    {
        // Order matters: more specific formats first
        $this->handlers = [
            new JsonLdHandler,    // Check JSON-LD first (distinctive @context)
            new TurtleHandler,    // Check Turtle (@prefix is distinctive)
            new NTriplesHandler,  // Check N-Triples (simple triple format)
            new RdfXmlHandler,    // RDF/XML last (can be confused with HTML)
        ];
    }

    /**
     * Build metadata about the parsed RDF content.
     */
    private function buildMetadata(ParsedRdf $parsedRdf, RdfFormatHandlerInterface $handler): array
    {
        $metadata = $parsedRdf->metadata;
        $metadata['format'] = $handler->getFormatName();
        $metadata['resource_count'] = $parsedRdf->getResourceCount();

        return $metadata;
    }

    /**
     * Extract OWL restrictions that define allowed relationships between classes.
     *
     * This handles rdfs:subClassOf restrictions with owl:someValuesFrom constraints.
     */
    private function extractGraphRestrictions(ParsedRdf $parsedRdf): array
    {
        $restrictions = [];
        $graph = $parsedRdf->graph;

        foreach ($graph->resources() as $resource) {
            $uri = $resource->getUri();
            if (! $uri) {
                continue; // Skip blank nodes for class URIs
            }

            // Look for rdfs:subClassOf restrictions
            $subClassRestrictions = $resource->all('rdfs:subClassOf');
            foreach ($subClassRestrictions as $restriction) {
                // Check if this is a blank node representing an OWL restriction
                if (! $this->isBlankNode($restriction)) {
                    continue;
                }

                $onProperty = $this->getResourceValue($restriction, 'owl:onProperty');
                if (! $onProperty) {
                    continue; // Not an OWL restriction
                }

                // Extract the allowed targets from someValuesFrom
                $someValuesFrom = $restriction->get('owl:someValuesFrom');
                if (! $someValuesFrom) {
                    continue;
                }

                $allowedTargets = [];
                if ($this->isBlankNode($someValuesFrom)) {
                    // It's a union class - extract the unionOf members
                    $unionOf = $someValuesFrom->get('owl:unionOf');
                    if ($unionOf) {
                        $allowedTargets = $this->extractUnionMembers($unionOf);
                    }
                } else {
                    // It's a single class
                    $targetUri = method_exists($someValuesFrom, 'getUri') ? $someValuesFrom->getUri() : null;
                    if ($targetUri) {
                        $allowedTargets = [$targetUri];
                    }
                }

                if (! empty($allowedTargets)) {
                    $restrictions[] = [
                        'source_class' => $uri,
                        'property' => $onProperty,
                        'allowed_targets' => $allowedTargets,
                        'restriction_type' => 'someValuesFrom',
                    ];
                }
            }
        }

        return $restrictions;
    }

    /**
     * Check if a resource is a blank node (anonymous resource).
     */
    private function isBlankNode($resource): bool
    {
        if (method_exists($resource, 'isBNode') && $resource->isBNode()) {
            return true;
        }

        $uri = method_exists($resource, 'getUri') ? $resource->getUri() : null;

        return $uri && str_starts_with($uri, '_:');
    }

    /**
     * Get a single value from a resource property.
     */
    private function getResourceValue($resource, string $property): ?string
    {
        $value = $resource->get($property);
        if (! $value) {
            return null;
        }

        if (method_exists($value, 'getUri') && $value->getUri()) {
            return $value->getUri();
        }

        return (string) $value;
    }

    /**
     * Extract members from an owl:unionOf list.
     */
    private function extractUnionMembers($unionOf): array
    {
        $members = [];

        // Handle RDF list traversal
        $current = $unionOf;
        while ($current && ! $this->isNilResource($current)) {
            $first = $current->get('rdf:first');
            if ($first) {
                $memberUri = method_exists($first, 'getUri') ? $first->getUri() : null;
                if ($memberUri) {
                    $members[] = $memberUri;
                }
            }

            $current = $current->get('rdf:rest');
        }

        return $members;
    }

    /**
     * Check if a resource is rdf:nil (end of list).
     */
    private function isNilResource($resource): bool
    {
        $uri = method_exists($resource, 'getUri') ? $resource->getUri() : null;

        return $uri === 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil';
    }
}
