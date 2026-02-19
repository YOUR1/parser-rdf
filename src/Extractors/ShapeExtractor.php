<?php

declare(strict_types=1);

namespace Youri\vandenBogert\Software\ParserRdf\Extractors;

use EasyRdf\Graph;
use EasyRdf\RdfNamespace;
use EasyRdf\Resource;
use Youri\vandenBogert\Software\ParserCore\Traits\ResourceHelperTrait;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;

/**
 * Extracts SHACL shapes from parsed RDF content.
 *
 * Handles NodeShape and PropertyShape extraction, property constraints,
 * target specifications, and nested property shapes (including blank nodes).
 *
 * This is the BASE shape extraction at the RDF level.
 * ShaclParser (Epic 6) enhances these shapes with additional SHACL-specific detail.
 */
final class ShapeExtractor
{
    use ResourceHelperTrait;

    /** @var list<string> */
    private const SHAPE_TYPE_URIS = [
        'http://www.w3.org/ns/shacl#NodeShape',
        'http://www.w3.org/ns/shacl#PropertyShape',
    ];

    /** @var list<string> */
    private const CONSTRAINT_PROPERTIES = [
        'sh:minCount',
        'sh:maxCount',
        'sh:minLength',
        'sh:maxLength',
        'sh:pattern',
        'sh:datatype',
        'sh:nodeKind',
        'sh:class',
        'sh:node',
        'sh:minInclusive',
        'sh:maxInclusive',
        'sh:minExclusive',
        'sh:maxExclusive',
    ];

    /**
     * Extract all SHACL shapes from parsed RDF.
     *
     * @return list<array<string, mixed>>
     */
    public function extract(ParsedRdf $parsedRdf): array
    {
        if ($parsedRdf->format === 'rdf/xml') {
            return [];
        }

        return $this->extractFromGraph($parsedRdf->graph);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractFromGraph(Graph $graph): array
    {
        $shapes = [];

        RdfNamespace::set('sh', 'http://www.w3.org/ns/shacl#');
        RdfNamespace::set('dct', 'http://purl.org/dc/terms/');

        foreach ($graph->resources() as $resource) {
            if (! $this->isShape($resource)) {
                continue;
            }

            $uri = $resource->getUri();
            if (! $uri) {
                continue;
            }

            $shapes[] = [
                'uri' => $uri,
                'label' => $this->getResourceLabel($resource),
                'description' => $this->getResourceComment($resource),
                'target_class' => $this->getResourceValue($resource, 'sh:targetClass'),
                'target_node' => $this->getResourceValue($resource, 'sh:targetNode'),
                'target_subjects_of' => $this->getResourceValue($resource, 'sh:targetSubjectsOf'),
                'target_objects_of' => $this->getResourceValue($resource, 'sh:targetObjectsOf'),
                'target_property' => $this->getResourceValue($resource, 'sh:path'),
                'property_shapes' => $this->extractPropertyShapes($resource),
                'constraints' => $this->extractConstraints($resource),
                'metadata' => [
                    'source' => 'easyrdf',
                    'types' => $this->getResourceValues($resource, 'rdf:type'),
                    'annotations' => $this->extractCustomAnnotations($resource),
                ],
            ];
        }

        return $shapes;
    }

    private function isShape(Resource $resource): bool
    {
        $types = $resource->all('rdf:type');

        foreach ($types as $type) {
            if ($type instanceof Resource && in_array($type->getUri(), self::SHAPE_TYPE_URIS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractPropertyShapes(Resource $shapeResource): array
    {
        $properties = [];
        $propertyShapes = $shapeResource->all('sh:property');

        foreach ($propertyShapes as $propertyShape) {
            if (! $propertyShape instanceof Resource) {
                continue;
            }

            $property = $this->extractPropertyShape($propertyShape);

            if (! empty($property['path'])) {
                $properties[] = array_filter($property);
            }
        }

        return $properties;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractPropertyShape(Resource $propertyShape): array
    {
        return [
            'path' => $this->getResourceValue($propertyShape, 'sh:path'),
            'label' => $this->getResourceLabel($propertyShape),
            'labels' => $this->getAllResourceLabels($propertyShape),
            'datatype' => $this->getResourceValue($propertyShape, 'sh:datatype'),
            'nodeKind' => $this->getResourceValue($propertyShape, 'sh:nodeKind'),
            'minCount' => $this->getResourceValue($propertyShape, 'sh:minCount'),
            'maxCount' => $this->getResourceValue($propertyShape, 'sh:maxCount'),
            'minLength' => $this->getResourceValue($propertyShape, 'sh:minLength'),
            'maxLength' => $this->getResourceValue($propertyShape, 'sh:maxLength'),
            'pattern' => $this->getResourceValue($propertyShape, 'sh:pattern'),
            'class' => $this->getResourceValue($propertyShape, 'sh:class'),
            'message' => $this->getResourceValue($propertyShape, 'sh:message'),
            'name' => $this->getResourceValue($propertyShape, 'sh:name'),
            'description' => $this->getResourceValue($propertyShape, 'sh:description'),
            'descriptions' => $this->getAllResourceComments($propertyShape),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractConstraints(Resource $shapeResource): array
    {
        $constraints = [];

        foreach (self::CONSTRAINT_PROPERTIES as $property) {
            $value = $this->getResourceValue($shapeResource, $property);
            if ($value !== null) {
                $key = str_replace('sh:', '', $property);
                $constraints[$key] = $value;
            }
        }

        return $constraints;
    }
}
