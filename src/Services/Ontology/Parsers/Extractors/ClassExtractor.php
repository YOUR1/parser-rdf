<?php

namespace App\Services\Ontology\Parsers\Extractors;

use App\Services\Ontology\Parsers\Traits\ResourceHelperTrait;
use App\Services\Ontology\Parsers\ValueObjects\ParsedRdf;
use EasyRdf\Graph;

/**
 * Extracts OWL/RDFS classes from parsed RDF content
 *
 * This extractor handles two methods of class extraction:
 * 1. From EasyRdf Graph for formats that parse well (Turtle, JSON-LD, N-Triples)
 * 2. From SimpleXML for RDF/XML when fallback parsing is used
 */
class ClassExtractor
{
    use ResourceHelperTrait;

    /**
     * Class type URIs to search for
     */
    protected const CLASS_TYPE_URIS = [
        'http://www.w3.org/2000/01/rdf-schema#Class',
        'http://www.w3.org/2002/07/owl#Class',
    ];

    /**
     * Extract all classes from parsed RDF
     */
    public function extract(ParsedRdf $parsedRdf): array
    {
        // For RDF/XML with XML fallback, use SimpleXML extraction
        if ($parsedRdf->format === 'rdf/xml' && isset($parsedRdf->metadata['xml_element'])) {
            return $this->extractFromXml($parsedRdf->metadata['xml_element']);
        }

        // For all other formats, use EasyRdf Graph extraction
        return $this->extractFromGraph($parsedRdf->graph);
    }

    /**
     * Extract classes from EasyRdf Graph
     */
    protected function extractFromGraph(Graph $graph): array
    {
        $classes = [];

        // Find all resources that are classes by checking their types
        $classResources = $this->findClassResources($graph);

        foreach ($classResources as $classResource) {
            $uri = $classResource->getUri();

            // Skip blank nodes and anonymous class expressions
            if (! $uri || $this->isBlankNode($classResource) || $this->isAnonymousOwlExpression($classResource)) {
                continue;
            }

            $class = [
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

            $classes[] = $class;
        }

        return $classes;
    }

    /**
     * Find all resources in the graph that are classes
     */
    protected function findClassResources(Graph $graph): array
    {
        $classResources = [];

        foreach ($graph->resources() as $resource) {
            $types = $resource->all('rdf:type');
            foreach ($types as $type) {
                if (method_exists($type, 'getUri') && in_array($type->getUri(), self::CLASS_TYPE_URIS)) {
                    $classResources[] = $resource;
                    break; // Don't add the same resource multiple times
                }
            }
        }

        return $classResources;
    }

    /**
     * Extract classes from SimpleXML element
     */
    protected function extractFromXml(\SimpleXMLElement $xml): array
    {
        $classes = [];

        // Find all rdfs:Class and owl:Class elements
        $classElements = array_merge(
            $xml->xpath('//rdfs:Class[@rdf:about]') ?: [],
            $xml->xpath('//owl:Class[@rdf:about]') ?: []
        );

        foreach ($classElements as $element) {
            $attributes = $element->attributes('rdf', true);
            $uri = (string) ($attributes['about'] ?? '');

            if (empty($uri)) {
                continue;
            }

            $class = [
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

            $classes[] = $class;
        }

        return $classes;
    }

    /**
     * Extract text content from XML element child
     */
    protected function getXmlElementText(\SimpleXMLElement $element, string $childName): ?string
    {
        $this->ensureXmlNamespaces($element);
        $children = $element->xpath("./$childName");

        if (! empty($children)) {
            return (string) $children[0];
        }

        return null;
    }

    /**
     * Extract all language-tagged texts from XML element
     */
    protected function getXmlElementTextsWithLang(\SimpleXMLElement $element, string $childName): array
    {
        $texts = [];
        $this->ensureXmlNamespaces($element);
        $children = $element->xpath("./$childName");

        foreach ($children as $child) {
            $lang = (string) $child->attributes('xml', true)['lang'] ?? null;
            $value = (string) $child;

            if ($lang) {
                $texts[$lang] = $value;
            } elseif (empty($texts['en'])) {
                $texts['en'] = $value;
            }
        }

        return $texts;
    }

    /**
     * Extract resource URIs from XML element children
     */
    protected function getXmlElementResources(\SimpleXMLElement $element, string $childName): array
    {
        $resources = [];
        $this->ensureXmlNamespaces($element);
        $children = $element->xpath("./$childName");

        foreach ($children as $child) {
            $attributes = $child->attributes('rdf', true);
            $resource = (string) ($attributes['resource'] ?? '');
            if (! empty($resource)) {
                $resources[] = $resource;
            }
        }

        return $resources;
    }

    /**
     * Ensure common RDF namespaces are registered on XML element
     */
    protected function ensureXmlNamespaces(\SimpleXMLElement $node): void
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
