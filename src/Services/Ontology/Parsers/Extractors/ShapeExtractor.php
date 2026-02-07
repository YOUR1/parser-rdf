<?php

namespace App\Services\Ontology\Parsers\Extractors;

use App\Services\Ontology\Parsers\Traits\ResourceHelperTrait;
use App\Services\Ontology\Parsers\ValueObjects\ParsedRdf;
use EasyRdf\Graph;
use EasyRdf\RdfNamespace;

/**
 * Extracts SHACL shapes from parsed RDF content
 *
 * SHACL (Shapes Constraint Language) is used to validate RDF graphs.
 * This extractor handles:
 * - NodeShape and PropertyShape extraction
 * - Property constraints (minCount, maxCount, datatype, etc.)
 * - Target specifications (targetClass, targetNode, etc.)
 * - Nested property shapes (including blank nodes)
 */
class ShapeExtractor
{
    use ResourceHelperTrait;

    /**
     * SHACL shape type URIs
     */
    protected const SHAPE_TYPE_URIS = [
        'http://www.w3.org/ns/shacl#NodeShape',
        'http://www.w3.org/ns/shacl#PropertyShape',
    ];

    /**
     * SHACL constraint properties to extract
     */
    protected const CONSTRAINT_PROPERTIES = [
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
     * Extract all SHACL shapes from parsed RDF
     */
    public function extract(ParsedRdf $parsedRdf): array
    {
        // SHACL shapes are rare in RDF/XML ontologies, typically only in Turtle/JSON-LD
        if ($parsedRdf->format === 'rdf/xml') {
            return [];
        }

        return $this->extractFromGraph($parsedRdf->graph);
    }

    /**
     * Extract shapes from EasyRdf Graph
     */
    protected function extractFromGraph(Graph $graph): array
    {
        $shapes = [];

        // Register SHACL and DCT namespaces
        RdfNamespace::set('sh', 'http://www.w3.org/ns/shacl#');
        RdfNamespace::set('dct', 'http://purl.org/dc/terms/');

        // Find all resources that are SHACL shapes
        foreach ($graph->resources() as $resource) {
            if (! $this->isShape($resource)) {
                continue;
            }

            $uri = $resource->getUri();
            if (! $uri) {
                continue; // Skip blank nodes for shape URIs
            }

            $shape = [
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

            $shapes[] = $shape;
        }

        return $shapes;
    }

    /**
     * Check if a resource is a SHACL shape
     */
    protected function isShape($resource): bool
    {
        $types = $resource->all('rdf:type');

        foreach ($types as $type) {
            if (! method_exists($type, 'getUri')) {
                continue;
            }

            $typeUri = $type->getUri();
            if (in_array($typeUri, self::SHAPE_TYPE_URIS)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract property shapes from a SHACL shape resource
     */
    protected function extractPropertyShapes($shapeResource): array
    {
        $properties = [];
        $propertyShapes = $shapeResource->all('sh:property');

        foreach ($propertyShapes as $propertyShape) {
            $property = $this->extractPropertyShape($propertyShape);

            // Only add if it has a path (required for property shapes)
            if (! empty($property['path'])) {
                $properties[] = array_filter($property); // Remove null values
            }
        }

        return $properties;
    }

    /**
     * Extract a single property shape (handles both URIs and blank nodes)
     */
    protected function extractPropertyShape($propertyShape): array
    {
        return [
            'path' => $this->getResourceValue($propertyShape, 'sh:path'),
            'label' => $this->getResourceLabel($propertyShape), // Extract label (single value)
            'labels' => $this->getAllResourceLabels($propertyShape), // Extract all language-tagged labels
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
            'descriptions' => $this->getAllResourceComments($propertyShape), // Extract all language-tagged descriptions
        ];
    }

    /**
     * Extract constraint properties from a shape resource
     */
    protected function extractConstraints($shapeResource): array
    {
        $constraints = [];

        foreach (self::CONSTRAINT_PROPERTIES as $property) {
            $value = $this->getResourceValue($shapeResource, $property);
            if ($value !== null) {
                // Remove 'sh:' prefix for cleaner keys
                $key = str_replace('sh:', '', $property);
                $constraints[$key] = $value;
            }
        }

        return $constraints;
    }
}
