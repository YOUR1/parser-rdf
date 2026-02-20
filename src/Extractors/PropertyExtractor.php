<?php

declare(strict_types=1);

namespace Youri\vandenBogert\Software\ParserRdf\Extractors;

use EasyRdf\Graph;
use EasyRdf\Resource;
use Youri\vandenBogert\Software\ParserCore\Traits\ResourceHelperTrait;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;

/**
 * Extracts OWL/RDF property definitions from parsed RDF content.
 *
 * Handles two extraction paths:
 * 1. EasyRdf Graph path — for Turtle, JSON-LD, N-Triples, and well-parsed RDF/XML
 * 2. SimpleXML fallback path — for RDF/XML when xml_element metadata is present
 *
 * Features:
 * - Property type determination (object, datatype, annotation)
 * - Functional property detection
 * - Complex class expressions (owl:unionOf with RDF list traversal) for domain/range
 * - Range extraction from comments (Dublin Core pattern with 6 regex fallbacks)
 */
final class PropertyExtractor
{
    use ResourceHelperTrait;

    /**
     * @var list<string>
     */
    private const PROPERTY_TYPE_URIS = [
        'http://www.w3.org/1999/02/22-rdf-syntax-ns#Property',
        'http://www.w3.org/2002/07/owl#DatatypeProperty',
        'http://www.w3.org/2002/07/owl#ObjectProperty',
        'http://www.w3.org/2002/07/owl#AnnotationProperty',
        'http://www.w3.org/2002/07/owl#FunctionalProperty',
    ];

    /**
     * Extract all properties from parsed RDF content.
     *
     * @return list<array<string, mixed>>
     */
    public function extract(ParsedRdf $parsedRdf, bool $includeSkolemizedBlankNodes = false): array
    {
        if ($parsedRdf->format === 'rdf/xml' && isset($parsedRdf->metadata['xml_element'])) {
            $xmlElement = $parsedRdf->metadata['xml_element'];
            if ($xmlElement instanceof \SimpleXMLElement) {
                return $this->extractFromXml($xmlElement);
            }
        }

        return $this->extractFromGraph($parsedRdf->graph, $includeSkolemizedBlankNodes);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractFromGraph(Graph $graph, bool $includeSkolemizedBlankNodes = false): array
    {
        $properties = [];

        $propertyResources = $this->findPropertyResources($graph);

        foreach ($propertyResources as $propertyResource) {
            $uri = $propertyResource->getUri();

            if (! $uri) {
                continue;
            }

            if ($this->isBlankNode($propertyResource)) {
                if (!$includeSkolemizedBlankNodes || $this->isAnonymousOwlExpression($propertyResource)) {
                    continue;
                }
                $uri = 'urn:bnode:' . $uri;
            } elseif ($this->isAnonymousOwlExpression($propertyResource)) {
                continue;
            }

            $types = $this->getResourceValues($propertyResource, 'rdf:type');
            $propertyType = $this->determinePropertyType($types);
            $isFunctional = in_array('http://www.w3.org/2002/07/owl#FunctionalProperty', $types, true);

            $range = $this->extractPropertyClassExpression($propertyResource, 'rdfs:range', $graph);

            if (empty($range)) {
                $range = $this->extractRangeFromResourceComments($propertyResource);
            }

            $properties[] = [
                'uri' => $uri,
                'label' => $this->getResourceLabel($propertyResource),
                'labels' => $this->getAllResourceLabels($propertyResource),
                'description' => $this->getResourceComment($propertyResource),
                'descriptions' => $this->getAllResourceComments($propertyResource),
                'property_type' => $propertyType,
                'domain' => $this->extractPropertyClassExpression($propertyResource, 'rdfs:domain', $graph),
                'range' => $range,
                'parent_properties' => $this->getResourceValues($propertyResource, 'rdfs:subPropertyOf'),
                'inverse_of' => $this->getResourceValues($propertyResource, 'owl:inverseOf'),
                'is_functional' => $isFunctional,
                'metadata' => [
                    'source' => 'easyrdf',
                    'types' => $types,
                    'see_also' => $this->getNamedResourceValues($propertyResource, 'rdfs:seeAlso'),
                    'is_defined_by' => $this->getNamedResourceValues($propertyResource, 'rdfs:isDefinedBy'),
                    'annotations' => $this->extractCustomAnnotations($propertyResource),
                ],
            ];
        }

        return $properties;
    }

    /**
     * @return list<Resource>
     */
    private function findPropertyResources(Graph $graph): array
    {
        $propertyResources = [];

        foreach ($graph->resources() as $resource) {
            $types = $resource->all('rdf:type');
            foreach ($types as $type) {
                if ($type instanceof Resource && in_array($type->getUri(), self::PROPERTY_TYPE_URIS, true)) {
                    $propertyResources[] = $resource;
                    break;
                }
            }
        }

        return $propertyResources;
    }

    /**
     * @param array<string> $types
     */
    private function determinePropertyType(array $types): string
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

        return 'datatype';
    }

    /**
     * Extract complex class expressions from domain/range including union classes.
     *
     * Different from the trait's extractComplexClassExpression() which takes 1 param
     * and returns ?string. This version extracts URIs from a specific predicate and
     * resolves owl:unionOf blank nodes.
     *
     * @return array<string>
     */
    private function extractPropertyClassExpression(Resource $resource, string $property, Graph $graph): array
    {
        $classExpressions = [];
        $values = $resource->all($property);

        foreach ($values as $value) {
            if ($value instanceof Resource && $value->getUri() && ! str_starts_with($value->getUri(), '_:')) {
                $classExpressions[] = $value->getUri();
            } elseif ($value instanceof Resource && $this->isBlankNode($value)) {
                $unionOf = $value->get('owl:unionOf');
                if ($unionOf instanceof Resource) {
                    $unionMembers = $this->extractRdfListMembers($unionOf);
                    $unionMembers = array_filter($unionMembers, static fn (string $uri): bool => ! str_starts_with($uri, '_:'));
                    $classExpressions = array_merge($classExpressions, $unionMembers);
                }
            }
        }

        return array_values(array_unique($classExpressions));
    }

    /**
     * Extract members from an RDF list (rdf:first/rdf:rest/rdf:nil traversal).
     *
     * Different from the trait's extractUnionMembers() which takes a Resource
     * and traverses from owl:unionOf internally. This version takes the list
     * head node directly.
     *
     * @return array<string>
     */
    private function extractRdfListMembers(Resource $listNode): array
    {
        $members = [];

        $current = $listNode;
        while (! $this->isNilResource($current)) {
            $first = $current->get('rdf:first');
            if ($first instanceof Resource && $first->getUri()) {
                $members[] = $first->getUri();
            }

            $rest = $current->get('rdf:rest');
            if (! $rest instanceof Resource) {
                break;
            }
            $current = $rest;
        }

        return $members;
    }

    private function isNilResource(Resource $resource): bool
    {
        return $resource->getUri() === 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil';
    }

    /**
     * @return array<string>
     */
    private function extractRangeFromResourceComments(Resource $resource): array
    {
        $comments = $resource->all('rdfs:comment');
        $ranges = [];

        foreach ($comments as $comment) {
            $text = (string) $comment;
            $rangeUri = $this->parseRangeFromCommentText($text);
            if ($rangeUri !== null) {
                $ranges[] = $rangeUri;
            }
        }

        return array_values(array_unique($ranges));
    }

    private function parseRangeFromCommentText(string $text): ?string
    {
        $text = strtolower(trim($text));

        if (preg_match('/range.*(?:plain literal|rdf literal|language-tagged|lang.*string)/i', $text)) {
            return 'http://www.w3.org/1999/02/22-rdf-syntax-ns#langString';
        }

        if (preg_match('/range.*rdfs:literal/i', $text) || preg_match('/range.*is.*literal/i', $text)) {
            return 'http://www.w3.org/2001/XMLSchema#string';
        }

        if (preg_match('/range.*(?:xsd:string|string)/i', $text)) {
            return 'http://www.w3.org/2001/XMLSchema#string';
        }

        if (preg_match('/range.*(?:xsd:datetime|datetime)/i', $text)) {
            return 'http://www.w3.org/2001/XMLSchema#dateTime';
        }

        if (preg_match('/range.*(?:xsd:boolean|boolean)/i', $text)) {
            return 'http://www.w3.org/2001/XMLSchema#boolean';
        }

        if (preg_match('/range.*(?:xsd:integer|integer)/i', $text)) {
            return 'http://www.w3.org/2001/XMLSchema#integer';
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractFromXml(\SimpleXMLElement $xml): array
    {
        $properties = [];

        $this->ensureXmlNamespaces($xml);

        $propertyElements = array_merge(
            $xml->xpath('//rdf:Property[@rdf:about]') ?: [],
            $xml->xpath('//owl:DatatypeProperty[@rdf:about]') ?: [],
            $xml->xpath('//owl:ObjectProperty[@rdf:about]') ?: [],
            $xml->xpath('//owl:AnnotationProperty[@rdf:about]') ?: [],
            $xml->xpath('//owl:FunctionalProperty[@rdf:about]') ?: [],
            $xml->xpath('//*[@rdf:about and rdf:type/@rdf:resource="http://www.w3.org/1999/02/22-rdf-syntax-ns#Property"]') ?: [],
            $xml->xpath('//*[@rdf:about and rdf:type/@rdf:resource="http://www.w3.org/2002/07/owl#DatatypeProperty"]') ?: [],
            $xml->xpath('//*[@rdf:about and rdf:type/@rdf:resource="http://www.w3.org/2002/07/owl#ObjectProperty"]') ?: [],
            $xml->xpath('//*[@rdf:about and rdf:type/@rdf:resource="http://www.w3.org/2002/07/owl#AnnotationProperty"]') ?: [],
            $xml->xpath('//*[@rdf:about and rdf:type/@rdf:resource="http://www.w3.org/2002/07/owl#FunctionalProperty"]') ?: [],
        );

        foreach ($propertyElements as $element) {
            $attributes = $element->attributes('rdf', true);
            $uri = (string) ($attributes['about'] ?? '');

            if ($uri === '') {
                continue;
            }

            $elementName = $element->getName();
            $propertyType = $this->determinePropertyTypeFromXml($element, $elementName);
            $isFunctional = $this->isXmlPropertyFunctional($element, $elementName);

            $range = $this->getXmlElementResources($element, 'rdfs:range');

            if (empty($range)) {
                $range = $this->extractRangeFromXmlComments($element);
            }

            $properties[] = [
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
        }

        return $properties;
    }

    private function determinePropertyTypeFromXml(\SimpleXMLElement $element, string $elementName): string
    {
        if (str_contains($elementName, 'ObjectProperty')) {
            return 'object';
        } elseif (str_contains($elementName, 'AnnotationProperty')) {
            return 'annotation';
        }

        $this->ensureXmlNamespaces($element);
        $typeElements = $element->xpath('./rdf:type');

        if (is_array($typeElements)) {
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
        }

        return 'datatype';
    }

    private function isXmlPropertyFunctional(\SimpleXMLElement $element, string $elementName): bool
    {
        if (str_contains($elementName, 'FunctionalProperty')) {
            return true;
        }

        $this->ensureXmlNamespaces($element);
        $typeElements = $element->xpath('./rdf:type');

        if (is_array($typeElements)) {
            foreach ($typeElements as $typeElement) {
                $typeAttrs = $typeElement->attributes('rdf', true);
                $typeUri = (string) ($typeAttrs['resource'] ?? '');
                if (str_contains($typeUri, 'FunctionalProperty')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<string>
     */
    private function extractRangeFromXmlComments(\SimpleXMLElement $element): array
    {
        $this->ensureXmlNamespaces($element);
        $commentElements = $element->xpath('./rdfs:comment');
        $ranges = [];

        if (is_array($commentElements)) {
            foreach ($commentElements as $commentElement) {
                $text = (string) $commentElement;
                $rangeUri = $this->parseRangeFromCommentText($text);
                if ($rangeUri !== null) {
                    $ranges[] = $rangeUri;
                }
            }
        }

        return array_values(array_unique($ranges));
    }

    private function getXmlElementText(\SimpleXMLElement $element, string $childName): ?string
    {
        $this->ensureXmlNamespaces($element);
        $children = $element->xpath("./$childName");

        if (is_array($children) && ! empty($children)) {
            return (string) $children[0];
        }

        return null;
    }

    /**
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
