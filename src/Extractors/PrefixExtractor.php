<?php

declare(strict_types=1);

namespace Youri\vandenBogert\Software\ParserRdf\Extractors;

use EasyRdf\Graph;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;

/**
 * Extracts namespace prefix-to-URI declarations from parsed RDF content.
 *
 * Uses a layered extraction approach:
 * 1. Graph namespace map (EasyRdf registered namespaces)
 * 2. Content-based regex parsing (format-specific: Turtle @prefix, RDF/XML xmlns, JSON-LD @context)
 * 3. SimpleXML namespace extraction (RDF/XML only, when xml_element metadata is present)
 * 4. Common prefix auto-detection (well-known prefixes used in graph but not explicitly declared)
 *
 * Results are merged with array_merge (later sources override earlier for same keys).
 */
final class PrefixExtractor
{
    /**
     * Extract prefix-to-URI namespace declarations from parsed RDF content.
     *
     * @return array<string, string> prefix => full URI mappings (never null, empty array if no prefixes)
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
            $xmlElement = $parsedRdf->metadata['xml_element'];
            if ($xmlElement instanceof \SimpleXMLElement) {
                $xmlPrefixes = $this->extractFromXml($xmlElement);
                $prefixes = array_merge($prefixes, $xmlPrefixes);
            }
        }

        // Method 4: Add common RDF prefixes found in the graph
        $commonPrefixes = $this->addCommonPrefixes($parsedRdf->graph, $prefixes);
        $prefixes = array_merge($prefixes, $commonPrefixes);

        return $prefixes;
    }

    /**
     * @return array<string, string>
     */
    private function extractFromGraph(Graph $graph): array
    {
        $prefixes = [];

        if (method_exists($graph, 'getNamespaceMap')) {
            /** @var array<string, string> $namespaces */
            $namespaces = $graph->getNamespaceMap();
            foreach ($namespaces as $prefix => $namespace) {
                if ($prefix !== '' && $namespace !== '') {
                    $prefixes[$prefix] = $namespace;
                }
            }
        }

        return $prefixes;
    }

    /**
     * @return array<string, string>
     */
    private function extractFromContent(string $content, string $format): array
    {
        return match ($format) {
            'turtle', 'ttl' => $this->extractFromTurtle($content),
            'rdf/xml', 'xml' => $this->extractFromRdfXml($content),
            'json-ld', 'jsonld' => $this->extractFromJsonLd($content),
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    private function extractFromTurtle(string $content): array
    {
        $prefixes = [];

        if (preg_match_all('/@prefix\s+([^:]+):\s*<([^>]+)>/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $prefix = trim($match[1]);
                $namespace = trim($match[2]);
                if ($prefix !== '' && $namespace !== '') {
                    $prefixes[$prefix] = $namespace;
                }
            }
        }

        if (preg_match_all('/PREFIX\s+([^:]+):\s*<([^>]+)>/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $prefix = trim($match[1]);
                $namespace = trim($match[2]);
                if ($prefix !== '' && $namespace !== '') {
                    $prefixes[$prefix] = $namespace;
                }
            }
        }

        return $prefixes;
    }

    /**
     * @return array<string, string>
     */
    private function extractFromRdfXml(string $content): array
    {
        $prefixes = [];

        if (preg_match_all('/xmlns:([^=]+)="([^"]+)"/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $prefix = trim($match[1]);
                $namespace = trim($match[2]);
                if ($prefix !== '' && $namespace !== '') {
                    $prefixes[$prefix] = $namespace;
                }
            }
        }

        return $prefixes;
    }

    /**
     * @return array<string, string>
     */
    private function extractFromXml(\SimpleXMLElement $xml): array
    {
        $prefixes = [];

        $namespaces = $xml->getNamespaces(true);

        foreach ($namespaces as $prefix => $namespace) {
            if ($prefix !== '' && $namespace !== '') {
                $prefixes[$prefix] = $namespace;
            }
        }

        return $prefixes;
    }

    /**
     * @return array<string, string>
     */
    private function extractFromJsonLd(string $content): array
    {
        $prefixes = [];
        $decoded = json_decode($content, true);

        if (isset($decoded['@context']) && is_array($decoded['@context'])) {
            foreach ($decoded['@context'] as $key => $value) {
                if (is_string($key) && is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false) {
                    $prefixes[$key] = $value;
                }
            }
        }

        return $prefixes;
    }

    /**
     * @param array<string, string> $existingPrefixes
     * @return array<string, string>
     */
    private function addCommonPrefixes(Graph $graph, array $existingPrefixes): array
    {
        $commonPrefixes = $this->getCommonPrefixes();
        $additionalPrefixes = [];

        foreach ($commonPrefixes as $prefix => $namespace) {
            if (!isset($existingPrefixes[$prefix]) && $this->hasResourcesInNamespace($graph, $namespace)) {
                $additionalPrefixes[$prefix] = $namespace;
            }
        }

        return $additionalPrefixes;
    }

    /**
     * @return array<string, string>
     */
    private function getCommonPrefixes(): array
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

    private function hasResourcesInNamespace(Graph $graph, string $namespace): bool
    {
        foreach ($graph->resources() as $resource) {
            $uri = $resource->getUri();
            if (str_starts_with($uri, $namespace)) {
                return true;
            }
        }

        return false;
    }
}
