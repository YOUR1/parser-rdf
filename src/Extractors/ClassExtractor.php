<?php

declare(strict_types=1);

namespace Youri\vandenBogert\Software\ParserRdf\Extractors;

use EasyRdf\Graph;
use Youri\vandenBogert\Software\ParserCore\Traits\ResourceHelperTrait;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;

/**
 * Extracts OWL/RDFS class definitions from parsed RDF content.
 *
 * Handles two extraction paths:
 * 1. EasyRdf Graph path — for Turtle, JSON-LD, N-Triples, and well-parsed RDF/XML
 * 2. SimpleXML fallback path — for RDF/XML when xml_element metadata is present
 *
 * Uses ResourceHelperTrait from parser-core for label, comment, annotation,
 * and blank-node handling on the Graph path.
 */
final class ClassExtractor
{
    use ResourceHelperTrait;

    /**
     * @var list<string>
     */
    private const CLASS_TYPE_URIS = [
        'http://www.w3.org/2000/01/rdf-schema#Class',
        'http://www.w3.org/2002/07/owl#Class',
    ];

    /**
     * Extract all classes from parsed RDF content.
     *
     * Dispatches to the XML fallback path when format is 'rdf/xml' and
     * a SimpleXMLElement is available in metadata. Otherwise uses the
     * EasyRdf Graph path.
     *
     * @return list<array<string, mixed>>
     */
    public function extract(ParsedRdf $parsedRdf): array
    {
        if ($parsedRdf->format === 'rdf/xml' && isset($parsedRdf->metadata['xml_element'])) {
            $xmlElement = $parsedRdf->metadata['xml_element'];
            if ($xmlElement instanceof \SimpleXMLElement) {
                return $this->extractFromXml($xmlElement);
            }
        }

        return $this->extractFromGraph($parsedRdf->graph);
    }

    /**
     * Extract classes from EasyRdf Graph.
     *
     * @return list<array<string, mixed>>
     */
    private function extractFromGraph(Graph $graph): array
    {
        $classes = [];

        $classResources = $this->findClassResources($graph);

        foreach ($classResources as $classResource) {
            $uri = $classResource->getUri();

            // Skip blank nodes and anonymous class expressions
            if (! $uri || $this->isBlankNode($classResource) || $this->isAnonymousOwlExpression($classResource)) {
                continue;
            }

            $classes[] = [
                'uri' => $uri,
                'label' => $this->getResourceLabel($classResource),
                'labels' => $this->getAllResourceLabels($classResource),
                'description' => $this->getResourceComment($classResource),
                'descriptions' => $this->getAllResourceComments($classResource),
                'parent_classes' => $this->getResourceValues($classResource, 'rdfs:subClassOf'),
                'metadata' => [
                    'source' => 'easyrdf',
                    'types' => $this->getResourceValues($classResource, 'rdf:type'),
                    'annotations' => $this->extractCustomAnnotations($classResource),
                ],
            ];
        }

        return $classes;
    }

    /**
     * Find all resources in the graph that are classes.
     *
     * @return list<\EasyRdf\Resource>
     */
    private function findClassResources(Graph $graph): array
    {
        $classResources = [];

        foreach ($graph->resources() as $resource) {
            $types = $resource->all('rdf:type');
            foreach ($types as $type) {
                if ($type instanceof \EasyRdf\Resource && in_array($type->getUri(), self::CLASS_TYPE_URIS, true)) {
                    $classResources[] = $resource;
                    break;
                }
            }
        }

        return $classResources;
    }

    /**
     * Extract classes from SimpleXML element.
     *
     * @return list<array<string, mixed>>
     */
    private function extractFromXml(\SimpleXMLElement $xml): array
    {
        $classes = [];

        $this->ensureXmlNamespaces($xml);

        $classElements = array_merge(
            $xml->xpath('//rdfs:Class[@rdf:about]') ?: [],
            $xml->xpath('//owl:Class[@rdf:about]') ?: [],
        );

        foreach ($classElements as $element) {
            $attributes = $element->attributes('rdf', true);
            $uri = (string) ($attributes['about'] ?? '');

            if ($uri === '') {
                continue;
            }

            $classes[] = [
                'uri' => $uri,
                'label' => $this->getXmlElementText($element, 'rdfs:label'),
                'labels' => $this->getXmlElementTextsWithLang($element, 'rdfs:label'),
                'description' => $this->getXmlElementText($element, 'rdfs:comment'),
                'descriptions' => $this->getXmlElementTextsWithLang($element, 'rdfs:comment'),
                'parent_classes' => $this->getXmlElementResources($element, 'rdfs:subClassOf'),
                'metadata' => [
                    'source' => 'fallback_rdf_xml',
                    'element_name' => $element->getName(),
                ],
            ];
        }

        return $classes;
    }

    /**
     * Extract text content from XML element child.
     */
    private function getXmlElementText(\SimpleXMLElement $element, string $childName): ?string
    {
        $this->ensureXmlNamespaces($element);
        $children = $element->xpath("./$childName");

        if (! empty($children)) {
            return (string) $children[0];
        }

        return null;
    }

    /**
     * Extract all language-tagged texts from XML element.
     *
     * @return array<string, string>
     */
    private function getXmlElementTextsWithLang(\SimpleXMLElement $element, string $childName): array
    {
        $texts = [];
        $this->ensureXmlNamespaces($element);
        $children = $element->xpath("./$childName");

        if (! is_array($children)) {
            return $texts;
        }

        foreach ($children as $child) {
            $langAttrs = $child->attributes('xml', true);
            $lang = isset($langAttrs['lang']) ? (string) $langAttrs['lang'] : '';
            $value = (string) $child;

            if ($lang !== '') {
                $texts[$lang] = $value;
            } elseif (empty($texts['en'])) {
                $texts['en'] = $value;
            }
        }

        return $texts;
    }

    /**
     * Extract resource URIs from XML element children.
     *
     * @return array<int, string>
     */
    private function getXmlElementResources(\SimpleXMLElement $element, string $childName): array
    {
        $resources = [];
        $this->ensureXmlNamespaces($element);
        $children = $element->xpath("./$childName");

        if (! is_array($children)) {
            return $resources;
        }

        foreach ($children as $child) {
            $attributes = $child->attributes('rdf', true);
            $resource = (string) ($attributes['resource'] ?? '');
            if ($resource !== '') {
                $resources[] = $resource;
            }
        }

        return $resources;
    }

    /**
     * Ensure common RDF namespaces are registered on XML element for XPath queries.
     */
    private function ensureXmlNamespaces(\SimpleXMLElement $node): void
    {
        $namespaces = [
            'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
            'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
            'owl' => 'http://www.w3.org/2002/07/owl#',
            'skos' => 'http://www.w3.org/2004/02/skos/core#',
            'dc' => 'http://purl.org/dc/elements/1.1/',
            'dcterms' => 'http://purl.org/dc/terms/',
        ];

        foreach ($namespaces as $prefix => $uri) {
            @$node->registerXPathNamespace($prefix, $uri);
        }
    }
}
