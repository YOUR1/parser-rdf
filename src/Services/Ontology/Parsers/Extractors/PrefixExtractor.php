<?php

namespace App\Services\Ontology\Parsers\Extractors;

use App\Services\Ontology\Parsers\ValueObjects\ParsedRdf;
use EasyRdf\Graph;

/**
 * Extracts namespace prefixes from parsed RDF content
 *
 * This extractor handles three methods of prefix extraction:
 * 1. From EasyRdf Graph namespace map
 * 2. From raw content based on format (Turtle @prefix, RDF/XML xmlns, JSON-LD @context)
 * 3. Common RDF prefixes found in the graph
 */
class PrefixExtractor
{
    /**
     * Extract all namespace prefixes from parsed RDF
     */
    public function extract(ParsedRdf $parsedRdf): array
    {
        $prefixes = [];

        // Method 1: Extract from EasyRdf graph's namespace map
        $prefixes = array_merge($prefixes, $this->extractFromGraph($parsedRdf->graph));

        // Method 2: Parse prefixes from raw content based on format
        $contentPrefixes = $this->extractFromContent($parsedRdf->rawContent, $parsedRdf->format);
        $prefixes = array_merge($prefixes, $contentPrefixes);

        // Method 3: For RDF/XML, also extract from SimpleXML if available
        if ($parsedRdf->format === 'rdf/xml' && isset($parsedRdf->metadata['xml_element'])) {
            $xmlPrefixes = $this->extractFromXml($parsedRdf->metadata['xml_element']);
            $prefixes = array_merge($prefixes, $xmlPrefixes);
        }

        // Method 4: Add common RDF prefixes found in the graph
        $commonPrefixes = $this->addCommonPrefixes($parsedRdf->graph, $prefixes);
        $prefixes = array_merge($prefixes, $commonPrefixes);

        return $prefixes;
    }

    /**
     * Extract prefixes from EasyRdf Graph namespace map
     */
    protected function extractFromGraph(Graph $graph): array
    {
        $prefixes = [];

        if (method_exists($graph, 'getNamespaceMap')) {
            $namespaces = $graph->getNamespaceMap();
            foreach ($namespaces as $prefix => $namespace) {
                if (! empty($prefix) && ! empty($namespace)) {
                    $prefixes[$prefix] = $namespace;
                }
            }
        }

        return $prefixes;
    }

    /**
     * Extract prefixes from raw content based on format
     */
    protected function extractFromContent(string $content, string $format): array
    {
        return match ($format) {
            'turtle', 'ttl' => $this->extractFromTurtle($content),
            'rdf/xml', 'xml' => $this->extractFromRdfXml($content),
            'json-ld', 'jsonld' => $this->extractFromJsonLd($content),
            default => []
        };
    }

    /**
     * Extract @prefix declarations from Turtle content
     */
    protected function extractFromTurtle(string $content): array
    {
        $prefixes = [];

        // Extract @prefix declarations
        if (preg_match_all('/@prefix\s+([^:]+):\s*<([^>]+)>/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $prefix = trim($match[1]);
                $namespace = trim($match[2]);
                if (! empty($prefix) && ! empty($namespace)) {
                    $prefixes[$prefix] = $namespace;
                }
            }
        }

        // Also handle PREFIX (SPARQL style)
        if (preg_match_all('/PREFIX\s+([^:]+):\s*<([^>]+)>/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $prefix = trim($match[1]);
                $namespace = trim($match[2]);
                if (! empty($prefix) && ! empty($namespace)) {
                    $prefixes[$prefix] = $namespace;
                }
            }
        }

        return $prefixes;
    }

    /**
     * Extract xmlns declarations from RDF/XML content
     */
    protected function extractFromRdfXml(string $content): array
    {
        $prefixes = [];

        if (preg_match_all('/xmlns:([^=]+)="([^"]+)"/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $prefix = trim($match[1]);
                $namespace = trim($match[2]);
                if (! empty($prefix) && ! empty($namespace)) {
                    $prefixes[$prefix] = $namespace;
                }
            }
        }

        return $prefixes;
    }

    /**
     * Extract prefixes from SimpleXML element
     */
    protected function extractFromXml(\SimpleXMLElement $xml): array
    {
        $prefixes = [];

        // Get all namespace declarations from the root element
        $namespaces = $xml->getNamespaces(true);

        foreach ($namespaces as $prefix => $namespace) {
            if (! empty($prefix) && ! empty($namespace)) {
                $prefixes[$prefix] = $namespace;
            }
        }

        return $prefixes;
    }

    /**
     * Extract @context from JSON-LD content
     */
    protected function extractFromJsonLd(string $content): array
    {
        $prefixes = [];
        $decoded = json_decode($content, true);

        if (isset($decoded['@context']) && is_array($decoded['@context'])) {
            foreach ($decoded['@context'] as $key => $value) {
                if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
                    $prefixes[$key] = $value;
                }
            }
        }

        return $prefixes;
    }

    /**
     * Add common RDF prefixes if they're used in the graph
     */
    protected function addCommonPrefixes(Graph $graph, array $existingPrefixes): array
    {
        $commonPrefixes = $this->getCommonPrefixes();
        $additionalPrefixes = [];

        foreach ($commonPrefixes as $prefix => $namespace) {
            // Only add if not already present and namespace is used in graph
            if (! isset($existingPrefixes[$prefix]) && $this->hasResourcesInNamespace($graph, $namespace)) {
                $additionalPrefixes[$prefix] = $namespace;
            }
        }

        return $additionalPrefixes;
    }

    /**
     * Get list of common RDF namespace prefixes
     */
    protected function getCommonPrefixes(): array
    {
        return [
            'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
            'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
            'owl' => 'http://www.w3.org/2002/07/owl#',
            'xsd' => 'http://www.w3.org/2001/XMLSchema#',
            'dc' => 'http://purl.org/dc/elements/1.1/',
            'dcterms' => 'http://purl.org/dc/terms/',
            'dct' => 'http://purl.org/dc/terms/',
            'foaf' => 'http://xmlns.com/foaf/0.1/',
            'skos' => 'http://www.w3.org/2004/02/skos/core#',
            'sh' => 'http://www.w3.org/ns/shacl#',
            'schema' => 'https://schema.org/',
        ];
    }

    /**
     * Check if graph contains resources in the given namespace
     */
    protected function hasResourcesInNamespace(Graph $graph, string $namespace): bool
    {
        foreach ($graph->resources() as $resource) {
            if ($resource->getUri() && str_starts_with($resource->getUri(), $namespace)) {
                return true;
            }
        }

        return false;
    }
}
