<?php

declare(strict_types=1);

namespace Youri\vandenBogert\Software\ParserRdf;

use Youri\vandenBogert\Software\ParserCore\Contracts\OntologyParserInterface;
use Youri\vandenBogert\Software\ParserCore\Contracts\RdfFormatHandlerInterface;
use Youri\vandenBogert\Software\ParserCore\Exceptions\FormatDetectionException;
use Youri\vandenBogert\Software\ParserCore\Exceptions\ParseException;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedOntology;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserJsonLd\JsonLdHandler;
use Youri\vandenBogert\Software\ParserRdf\Extractors\ClassExtractor;
use Youri\vandenBogert\Software\ParserRdf\Extractors\PrefixExtractor;
use Youri\vandenBogert\Software\ParserRdf\Extractors\PropertyExtractor;
use Youri\vandenBogert\Software\ParserRdf\Extractors\ShapeExtractor;
use Youri\vandenBogert\Software\ParserRdf\Handlers\NTriplesHandler;
use Youri\vandenBogert\Software\ParserRdfXml\RdfXmlHandler;
use Youri\vandenBogert\Software\ParserTurtle\TurtleHandler;

/**
 * RDF Parser - Orchestrates RDF format parsing and entity extraction.
 *
 * Uses a handler-extractor pattern to support multiple RDF formats.
 * Format handlers detect and parse RDF serializations; extractors
 * pull semantic entities from the parsed graph.
 *
 * NOT final: OwlParser and ShaclParser extend this class.
 */
class RdfParser implements OntologyParserInterface
{
    /** @var list<RdfFormatHandlerInterface> */
    private array $handlers = [];

    private readonly ClassExtractor $classExtractor;

    private readonly PrefixExtractor $prefixExtractor;

    private readonly PropertyExtractor $propertyExtractor;

    private readonly ShapeExtractor $shapeExtractor;

    public function __construct()
    {
        $this->classExtractor = new ClassExtractor();
        $this->prefixExtractor = new PrefixExtractor();
        $this->propertyExtractor = new PropertyExtractor();
        $this->shapeExtractor = new ShapeExtractor();
        $this->registerDefaultHandlers();
    }

    /**
     * @param array<string, string|int|bool|null> $options
     */
    public function parse(string $content, array $options = []): ParsedOntology
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            throw new ParseException('Cannot parse empty content');
        }

        try {
            $handler = $this->getHandler($content, $options);
            $parsedRdf = $handler->parse($content);

            return $this->buildParsedOntology($parsedRdf, $handler, $content);
        } catch (FormatDetectionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($e instanceof ParseException) {
                throw $e;
            }

            throw new ParseException('RDF parsing failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function canParse(string $content): bool
    {
        try {
            foreach ($this->handlers as $handler) {
                if ($handler->canHandle($content)) {
                    return true;
                }
            }
        } catch (\Throwable) {
            // canParse MUST NOT throw
        }

        return false;
    }

    /** @return list<string> */
    public function getSupportedFormats(): array
    {
        return array_map(
            static fn (RdfFormatHandlerInterface $handler): string => $handler->getFormatName(),
            $this->handlers,
        );
    }

    public function registerHandler(RdfFormatHandlerInterface $handler): void
    {
        array_unshift($this->handlers, $handler);
    }

    protected function buildParsedOntology(ParsedRdf $parsedRdf, RdfFormatHandlerInterface $handler, string $content): ParsedOntology
    {
        return new ParsedOntology(
            classes: $this->extractClasses($parsedRdf),
            properties: $this->extractProperties($parsedRdf),
            prefixes: $this->extractPrefixes($parsedRdf),
            shapes: $this->extractShapes($parsedRdf),
            restrictions: $this->extractRestrictions($parsedRdf),
            metadata: $this->buildMetadata($parsedRdf, $handler),
            rawContent: $content,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildMetadata(ParsedRdf $parsedRdf, RdfFormatHandlerInterface $handler): array
    {
        $metadata = $parsedRdf->metadata;
        $metadata['format'] = $handler->getFormatName();
        $metadata['resource_count'] = $parsedRdf->getResourceCount();

        return $metadata;
    }

    /** @return array<string, array<string, mixed>> */
    protected function extractClasses(ParsedRdf $parsedRdf): array
    {
        $classes = $this->classExtractor->extract($parsedRdf);
        $result = [];

        foreach ($classes as $class) {
            $result[$class['uri']] = $class;
        }

        return $result;
    }

    /** @return array<string, array<string, mixed>> */
    protected function extractProperties(ParsedRdf $parsedRdf): array
    {
        $properties = $this->propertyExtractor->extract($parsedRdf);
        $result = [];

        foreach ($properties as $property) {
            $result[$property['uri']] = $property;
        }

        return $result;
    }

    /** @return array<string, string> */
    protected function extractPrefixes(ParsedRdf $parsedRdf): array
    {
        return $this->prefixExtractor->extract($parsedRdf);
    }

    /** @return array<string, array<string, mixed>> */
    protected function extractShapes(ParsedRdf $parsedRdf): array
    {
        $shapes = $this->shapeExtractor->extract($parsedRdf);
        $result = [];

        foreach ($shapes as $shape) {
            $result[$shape['uri']] = $shape;
        }

        return $result;
    }

    /** @return array<string, array<string, mixed>> */
    protected function extractRestrictions(ParsedRdf $parsedRdf): array
    {
        return [];
    }

    private function registerDefaultHandlers(): void
    {
        $this->handlers = [
            new JsonLdHandler(),
            new TurtleHandler(),
            new NTriplesHandler(),
            new RdfXmlHandler(),
        ];
    }

    /**
     * @param array<string, string|int|bool|null> $options
     */
    private function getHandler(string $content, array $options): RdfFormatHandlerInterface
    {
        if (isset($options['format'])) {
            $requestedFormat = (string) $options['format'];

            foreach ($this->handlers as $handler) {
                if ($handler->getFormatName() === $requestedFormat) {
                    return $handler;
                }
            }

            $available = implode(', ', $this->getSupportedFormats());
            throw new FormatDetectionException(
                "No handler registered for format: {$requestedFormat}. Available: {$available}"
            );
        }

        foreach ($this->handlers as $handler) {
            if ($handler->canHandle($content)) {
                return $handler;
            }
        }

        $available = implode(', ', $this->getSupportedFormats());
        throw new FormatDetectionException(
            "No handler could detect the format of the provided content. Tried: {$available}"
        );
    }
}
