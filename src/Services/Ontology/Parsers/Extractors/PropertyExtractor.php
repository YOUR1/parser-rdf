<?php

namespace App\Services\Ontology\Parsers\Extractors;

use App\Services\Ontology\Parsers\Traits\ResourceHelperTrait;
use App\Services\Ontology\Parsers\ValueObjects\ParsedRdf;
use EasyRdf\Graph;
use EasyRdf\Literal;

/**
 * Extracts OWL/RDF properties from parsed RDF content
 *
 * This extractor handles two methods of property extraction:
 * 1. From EasyRdf Graph for formats that parse well (Turtle, JSON-LD, N-Triples)
 * 2. From SimpleXML for RDF/XML when fallback parsing is used
 *
 * Handles:
 * - Property type determination (object, datatype, annotation)
 * - Functional property detection
 * - Complex class expressions (union classes) for domain/range
 * - Range extraction from comments (Dublin Core pattern)
 */
class PropertyExtractor
{
    use ResourceHelperTrait;

    /**
     * Property type URIs to search for
     */
    protected const PROPERTY_TYPE_URIS = [
        'http://www.w3.org/1999/02/22-rdf-syntax-ns#Property',
        'http://www.w3.org/2002/07/owl#DatatypeProperty',
        'http://www.w3.org/2002/07/owl#ObjectProperty',
        'http://www.w3.org/2002/07/owl#AnnotationProperty',
        'http://www.w3.org/2002/07/owl#FunctionalProperty',
    ];

    /**
     * Extract all properties from parsed RDF
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
     * Extract properties from EasyRdf Graph
     */
    protected function extractFromGraph(Graph $graph): array
    {
        $properties = [];

        // Find all resources that are properties by checking their types
        $propertyResources = $this->findPropertyResources($graph);

        foreach ($propertyResources as $propertyResource) {
            $uri = $propertyResource->getUri();

            // Skip blank nodes and anonymous expressions
            if (! $uri || $this->isBlankNode($propertyResource) || $this->isAnonymousOwlExpression($propertyResource)) {
                continue;
            }

            // Determine property type
            $types = $this->getResourceValues($propertyResource, 'rdf:type');
            $propertyType = $this->determinePropertyType($types);

            // Check if functional property
            $isFunctional = in_array('http://www.w3.org/2002/07/owl#FunctionalProperty', $types);

            $range = $this->extractComplexClassExpression($propertyResource, 'rdfs:range', $graph);

            // If no formal range, try to extract from rdfs:comment
            if (empty($range)) {
                $range = $this->extractRangeFromResourceComments($propertyResource);
            }

            $property = [
                'uri' => $uri,
                'label' => $this->getResourceLabel($propertyResource),
                'labels' => $this->getAllResourceLabels($propertyResource),
                'description' => $this->getResourceComment($propertyResource),
                'descriptions' => $this->getAllResourceComments($propertyResource),
                'property_type' => $propertyType,
                'domain' => $this->extractComplexClassExpression($propertyResource, 'rdfs:domain', $graph),
                'range' => $range,
                'parent_properties' => $this->getResourceValues($propertyResource, 'rdfs:subPropertyOf'),
                'inverse_of' => $this->getResourceValues($propertyResource, 'owl:inverseOf'),
                'is_functional' => $isFunctional,
                'metadata' => [
                    'source' => 'easyrdf',
                    'types' => $types,
                    'annotations' => $this->extractCustomAnnotations($propertyResource),
                ],
            ];

            $properties[] = $property;
        }

        return $properties;
    }

    /**
     * Find all resources in the graph that are properties
     */
    protected function findPropertyResources(Graph $graph): array
    {
        $propertyResources = [];

        foreach ($graph->resources() as $resource) {
            $types = $resource->all('rdf:type');
            foreach ($types as $type) {
                if (method_exists($type, 'getUri') && in_array($type->getUri(), self::PROPERTY_TYPE_URIS)) {
                    $propertyResources[] = $resource;
                    break; // Don't add the same resource multiple times
                }
            }
        }

        return $propertyResources;
    }

    /**
     * Extract properties from SimpleXML element
     */
    protected function extractFromXml(\SimpleXMLElement $xml): array
    {
        $properties = [];

        // Find all property elements - both by element name and by rdf:type
        $propertyElements = array_merge(
            // Find by element name
            $xml->xpath('//rdf:Property[@rdf:about]') ?: [],
            $xml->xpath('//owl:DatatypeProperty[@rdf:about]') ?: [],
            $xml->xpath('//owl:ObjectProperty[@rdf:about]') ?: [],
            $xml->xpath('//owl:AnnotationProperty[@rdf:about]') ?: [],
            $xml->xpath('//owl:FunctionalProperty[@rdf:about]') ?: [],

            // Find by rdf:type attribute (common in Dublin Core files)
            $xml->xpath('//*[@rdf:about and rdf:type/@rdf:resource="http://www.w3.org/1999/02/22-rdf-syntax-ns#Property"]') ?: [],
            $xml->xpath('//*[@rdf:about and rdf:type/@rdf:resource="http://www.w3.org/2002/07/owl#DatatypeProperty"]') ?: [],
            $xml->xpath('//*[@rdf:about and rdf:type/@rdf:resource="http://www.w3.org/2002/07/owl#ObjectProperty"]') ?: [],
            $xml->xpath('//*[@rdf:about and rdf:type/@rdf:resource="http://www.w3.org/2002/07/owl#AnnotationProperty"]') ?: [],
            $xml->xpath('//*[@rdf:about and rdf:type/@rdf:resource="http://www.w3.org/2002/07/owl#FunctionalProperty"]') ?: []
        );

        foreach ($propertyElements as $element) {
            $attributes = $element->attributes('rdf', true);
            $uri = (string) ($attributes['about'] ?? '');

            if (empty($uri)) {
                continue;
            }

            // Determine property type based on element name or rdf:type
            $elementName = $element->getName();
            $propertyType = $this->determinePropertyTypeFromXml($element, $elementName);

            // Determine if property is functional
            $isFunctional = $this->isXmlPropertyFunctional($element, $elementName);

            $range = $this->getXmlElementResources($element, 'rdfs:range');

            // If no formal range, try to extract from rdfs:comment
            if (empty($range)) {
                $range = $this->extractRangeFromXmlComments($element);
            }

            $property = [
                'uri' => $uri,
                'label' => $this->getXmlElementText($element, 'rdfs:label'),
                'labels' => $this->getXmlElementTextsWithLang($element, 'rdfs:label'),
                'description' => $this->getXmlElementText($element, 'rdfs:comment'),
                'descriptions' => $this->getXmlElementTextsWithLang($element, 'rdfs:comment'),
                'property_type' => $propertyType,
                'domain' => $this->getXmlElementResources($element, 'rdfs:domain'),
                'range' => $range,
                'parent_properties' => $this->getXmlElementResources($element, 'rdfs:subPropertyOf'),
                'inverse_of' => $this->getXmlElementResources($element, 'owl:inverseOf'),
                'is_functional' => $isFunctional,
                'metadata' => [
                    'source' => 'fallback_rdf_xml',
                    'element_name' => $elementName,
                ],
            ];

            $properties[] = $property;
        }

        return $properties;
    }

    /**
     * Determine property type from XML element
     */
    protected function determinePropertyTypeFromXml(\SimpleXMLElement $element, string $elementName): string
    {
        $propertyType = 'datatype'; // default

        // Check element name first
        if (str_contains($elementName, 'ObjectProperty')) {
            return 'object';
        } elseif (str_contains($elementName, 'AnnotationProperty')) {
            return 'annotation';
        }

        // Check rdf:type attribute if element name doesn't indicate type
        $this->ensureXmlNamespaces($element);
        $typeElements = $element->xpath('./rdf:type');
        foreach ($typeElements as $typeElement) {
            $typeAttrs = $typeElement->attributes('rdf', true);
            $typeUri = (string) ($typeAttrs['resource'] ?? '');

            if (str_contains($typeUri, 'ObjectProperty')) {
                return 'object';
            } elseif (str_contains($typeUri, 'DatatypeProperty')) {
                return 'datatype';
            } elseif (str_contains($typeUri, 'AnnotationProperty')) {
                return 'annotation';
            }
        }

        return $propertyType;
    }

    /**
     * Determine if XML property is functional
     */
    protected function isXmlPropertyFunctional(\SimpleXMLElement $element, string $elementName): bool
    {
        // Check element name first
        if (str_contains($elementName, 'FunctionalProperty')) {
            return true;
        }

        // Check rdf:type attribute
        $this->ensureXmlNamespaces($element);
        $typeElements = $element->xpath('./rdf:type');
        foreach ($typeElements as $typeElement) {
            $typeAttrs = $typeElement->attributes('rdf', true);
            $typeUri = (string) ($typeAttrs['resource'] ?? '');
            if (str_contains($typeUri, 'FunctionalProperty')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract complex class expressions from domain/range including union classes
     */
    protected function extractComplexClassExpression($resource, string $property, Graph $graph): array
    {
        $classExpressions = [];
        $values = $resource->all($property);

        foreach ($values as $value) {
            if (method_exists($value, 'getUri') && $value->getUri() && ! str_starts_with($value->getUri(), '_:')) {
                // Simple class URI (exclude blank nodes)
                $classExpressions[] = $value->getUri();
            } elseif ($this->isBlankNode($value)) {
                // Complex expression (possibly union class)
                $unionOf = $value->get('owl:unionOf');
                if ($unionOf) {
                    // Extract union members
                    $unionMembers = $this->extractUnionMembers($unionOf);
                    // Filter out blank node URIs
                    $unionMembers = array_filter($unionMembers, fn ($uri) => ! str_starts_with($uri, '_:'));
                    $classExpressions = array_merge($classExpressions, $unionMembers);
                }
            }
        }

        return array_unique($classExpressions);
    }

    /**
     * Extract members from an owl:unionOf list
     */
    protected function extractUnionMembers($unionOf): array
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
     * Check if a resource is rdf:nil (end of list)
     */
    protected function isNilResource($resource): bool
    {
        $uri = method_exists($resource, 'getUri') ? $resource->getUri() : null;

        return $uri === 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil';
    }

    /**
     * Extract range information from rdfs:comment when formal rdfs:range is missing
     */
    protected function extractRangeFromResourceComments($resource): array
    {
        $comments = $resource->all('rdfs:comment');
        $ranges = [];

        foreach ($comments as $comment) {
            $text = (string) $comment;
            $rangeUri = $this->parseRangeFromCommentText($text);
            if ($rangeUri) {
                $ranges[] = $rangeUri;
            }
        }

        return array_unique($ranges);
    }

    /**
     * Extract range information from rdfs:comment XML elements
     */
    protected function extractRangeFromXmlComments(\SimpleXMLElement $element): array
    {
        $this->ensureXmlNamespaces($element);
        $commentElements = $element->xpath('./rdfs:comment');
        $ranges = [];

        foreach ($commentElements as $commentElement) {
            $text = (string) $commentElement;
            $rangeUri = $this->parseRangeFromCommentText($text);
            if ($rangeUri) {
                $ranges[] = $rangeUri;
            }
        }

        return array_unique($ranges);
    }

    /**
     * Parse range URI from comment text
     * Recognizes common patterns in ontology comments about ranges
     */
    protected function parseRangeFromCommentText(string $text): ?string
    {
        $text = strtolower(trim($text));

        // Pattern: "The range of ... is the class of RDF plain literals"
        if (preg_match('/range.*(?:plain literal|rdf literal|language-tagged|lang.*string)/i', $text)) {
            return 'http://www.w3.org/1999/02/22-rdf-syntax-ns#langString';
        }

        // Pattern: "The range ... is ... Literal"
        if (preg_match('/range.*rdfs:literal/i', $text) || preg_match('/range.*is.*literal/i', $text)) {
            return 'http://www.w3.org/2001/XMLSchema#string';
        }

        // Pattern: "The range ... is ... string"
        if (preg_match('/range.*(?:xsd:string|string)/i', $text)) {
            return 'http://www.w3.org/2001/XMLSchema#string';
        }

        // Pattern: "The range ... is ... dateTime"
        if (preg_match('/range.*(?:xsd:datetime|datetime)/i', $text)) {
            return 'http://www.w3.org/2001/XMLSchema#dateTime';
        }

        // Pattern: "The range ... is ... boolean"
        if (preg_match('/range.*(?:xsd:boolean|boolean)/i', $text)) {
            return 'http://www.w3.org/2001/XMLSchema#boolean';
        }

        // Pattern: "The range ... is ... integer"
        if (preg_match('/range.*(?:xsd:integer|integer)/i', $text)) {
            return 'http://www.w3.org/2001/XMLSchema#integer';
        }

        return null;
    }

    /**
     * Determine property type from type URIs
     */
    protected function determinePropertyType(array $types): string
    {
        foreach ($types as $type) {
            if (str_contains($type, 'ObjectProperty')) {
                return 'object';
            } elseif (str_contains($type, 'DatatypeProperty')) {
                return 'datatype';
            } elseif (str_contains($type, 'AnnotationProperty')) {
                return 'annotation';
            }
        }

        return 'datatype'; // Default
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
