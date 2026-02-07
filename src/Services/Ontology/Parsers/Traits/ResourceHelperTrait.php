<?php

namespace App\Services\Ontology\Parsers\Traits;

use EasyRdf\Resource;

/**
 * Shared helper methods for working with RDF resources.
 *
 * Provides common operations for extracting labels, comments, values,
 * and handling blank nodes and complex OWL expressions.
 */
trait ResourceHelperTrait
{
    /**
     * Get the first label for a resource (multilingual support).
     */
    protected function getResourceLabel(Resource $resource, ?string $preferredLang = null): ?string
    {
        $labels = $this->getAllResourceLabels($resource);

        if (empty($labels)) {
            return null;
        }

        // Prefer specified language
        if ($preferredLang && isset($labels[$preferredLang])) {
            return $labels[$preferredLang];
        }

        // Fallback to English
        if (isset($labels['en'])) {
            return $labels['en'];
        }

        // Return first available
        return reset($labels) ?: null;
    }

    /**
     * Get all labels for a resource with language tags.
     * Only extracts rdfs:label - all other label properties (skos:prefLabel, dc:title, etc.)
     * should be preserved as custom annotations.
     */
    protected function getAllResourceLabels(Resource $resource): array
    {
        $labels = [];

        // Only extract rdfs:label (the standard property mapped to database)
        foreach ($resource->allLiterals('rdfs:label') as $label) {
            $lang = $label->getLang() ?? 'none';
            $labels[$lang] = (string) $label;
        }

        return $labels;
    }

    /**
     * Get the first comment/description for a resource.
     */
    protected function getResourceComment(Resource $resource, ?string $preferredLang = null): ?string
    {
        $comments = $this->getAllResourceComments($resource);

        if (empty($comments)) {
            return null;
        }

        // Prefer specified language
        if ($preferredLang && isset($comments[$preferredLang])) {
            return $comments[$preferredLang];
        }

        // Fallback to English
        if (isset($comments['en'])) {
            return $comments['en'];
        }

        // Return first available
        return reset($comments) ?: null;
    }

    /**
     * Get all comments/descriptions for a resource with language tags.
     * Only extracts rdfs:comment - all other description properties (skos:definition,
     * skos:note, dc:description, etc.) should be preserved as custom annotations.
     */
    protected function getAllResourceComments(Resource $resource): array
    {
        $comments = [];

        // Only extract rdfs:comment (the standard property mapped to database)
        foreach ($resource->allLiterals('rdfs:comment') as $comment) {
            $lang = $comment->getLang() ?? 'none';
            $comments[$lang] = (string) $comment;
        }

        return $comments;
    }

    /**
     * Extract custom annotations from a resource.
     * Returns all properties that are NOT in the standard RDFS/OWL properties list.
     * Standard properties are those explicitly handled by the system and mapped to database fields.
     */
    protected function extractCustomAnnotations(Resource $resource): array
    {
        // Standard properties that map to database fields
        // Everything else becomes a custom annotation
        $standardProperties = [
            'http://www.w3.org/1999/02/22-rdf-syntax-ns#type',
            'http://www.w3.org/2000/01/rdf-schema#label',
            'http://www.w3.org/2000/01/rdf-schema#comment',
            'http://www.w3.org/2000/01/rdf-schema#subClassOf',
            'http://www.w3.org/2002/07/owl#equivalentClass',
            'http://www.w3.org/2002/07/owl#disjointWith',
            'http://www.w3.org/2000/01/rdf-schema#domain',
            'http://www.w3.org/2000/01/rdf-schema#range',
            'http://www.w3.org/2000/01/rdf-schema#subPropertyOf',
            'http://www.w3.org/2002/07/owl#equivalentProperty',
            'http://www.w3.org/2002/07/owl#inverseOf',
            'http://www.w3.org/2002/07/owl#deprecated',
        ];

        $annotations = [];

        // Get all property URIs used by this resource
        foreach ($resource->propertyUris() as $propertyUri) {
            // Skip standard properties
            if (in_array($propertyUri, $standardProperties)) {
                continue;
            }

            // Get all values for this property
            // IMPORTANT: Try prefixed notation FIRST because EasyRDF stores properties
            // in prefixed form internally after parsing
            $shortProperty = $this->shortenUri($propertyUri);
            $values = $resource->all($shortProperty);

            // Fallback to full URI if prefixed didn't work
            if (empty($values)) {
                $values = $resource->all($propertyUri);
            }

            foreach ($values as $value) {
                $annotation = [
                    'property' => $shortProperty,
                ];

                // Check if it's a literal with language
                if ($value instanceof \EasyRdf\Literal) {
                    $annotation['value'] = $value->getValue();
                    if ($lang = $value->getLang()) {
                        $annotation['language'] = $lang;
                    }
                } elseif ($value instanceof Resource && $value->getUri()) {
                    // It's a resource reference
                    $annotation['value'] = $this->shortenUri($value->getUri());
                } else {
                    $annotation['value'] = (string) $value;
                }

                $annotations[] = $annotation;
            }
        }

        return $annotations;
    }

    /**
     * Shorten a URI to prefixed notation if possible.
     */
    protected function shortenUri(string $uri): string
    {
        // Try to find a matching namespace
        $namespaces = \EasyRdf\RdfNamespace::namespaces();

        foreach ($namespaces as $prefix => $namespace) {
            if (str_starts_with($uri, $namespace)) {
                $localPart = substr($uri, strlen($namespace));

                return $prefix.':'.$localPart;
            }
        }

        // No matching namespace found, return full URI
        return $uri;
    }

    /**
     * Get a single value from a resource property.
     */
    protected function getResourceValue(Resource $resource, string $property): mixed
    {
        $value = $resource->get($property);

        if (! $value) {
            return null;
        }

        if ($value instanceof Resource) {
            return $value->getUri();
        }

        return (string) $value;
    }

    /**
     * Get all values from a resource property.
     */
    protected function getResourceValues(Resource $resource, string $property): array
    {
        $values = [];

        foreach ($resource->all($property) as $value) {
            if ($value instanceof Resource) {
                $values[] = $value->getUri();
            } else {
                $values[] = (string) $value;
            }
        }

        return $values;
    }

    /**
     * Check if a resource is a blank node.
     */
    protected function isBlankNode(Resource $resource): bool
    {
        return $resource->isBNode();
    }

    /**
     * Check if a resource is an anonymous OWL expression (blank node with owl: types).
     */
    protected function isAnonymousOwlExpression(Resource $resource): bool
    {
        if (! $this->isBlankNode($resource)) {
            return false;
        }

        // Check for common OWL expression types
        $owlTypes = [
            'owl:Restriction',
            'owl:Class',
            'owl:unionOf',
            'owl:intersectionOf',
            'owl:complementOf',
        ];

        foreach ($owlTypes as $type) {
            if ($resource->isA($type)) {
                return true;
            }
        }

        // Check if it's used in OWL expressions
        if ($resource->get('owl:unionOf') || $resource->get('owl:intersectionOf')) {
            return true;
        }

        return false;
    }

    /**
     * Extract complex class expression (handles blank nodes and restrictions).
     */
    protected function extractComplexClassExpression(Resource $resource): ?string
    {
        // Handle named classes
        if (! $this->isBlankNode($resource)) {
            return $resource->getUri();
        }

        // Handle restrictions
        if ($resource->isA('owl:Restriction')) {
            $onProperty = $resource->get('owl:onProperty');
            if ($onProperty) {
                return 'Restriction on '.$onProperty->getUri();
            }
        }

        // Handle unions - extract member URIs
        $unionMembers = $this->extractUnionMembers($resource);
        if (! empty($unionMembers)) {
            return 'Union of: '.implode(', ', $unionMembers);
        }

        return null;
    }

    /**
     * Extract members from an owl:unionOf list.
     */
    protected function extractUnionMembers(Resource $resource): array
    {
        $members = [];
        $unionOf = $resource->get('owl:unionOf');

        if (! $unionOf) {
            return [];
        }

        // Traverse RDF list
        $current = $unionOf;
        while ($current && ! $current->getUri() === 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil') {
            $first = $current->get('rdf:first');
            if ($first && ! $this->isBlankNode($first)) {
                $members[] = $first->getUri();
            }

            $current = $current->get('rdf:rest');
        }

        return $members;
    }

    /**
     * Get local name from a URI (part after # or last /).
     */
    protected function getLocalName(string $uri): string
    {
        if (str_contains($uri, '#')) {
            return substr($uri, strrpos($uri, '#') + 1);
        }

        return substr($uri, strrpos($uri, '/') + 1);
    }

    /**
     * Get namespace from a URI (part before # or last /).
     */
    protected function getNamespace(string $uri): string
    {
        if (str_contains($uri, '#')) {
            return substr($uri, 0, strrpos($uri, '#') + 1);
        }

        return substr($uri, 0, strrpos($uri, '/') + 1);
    }
}
