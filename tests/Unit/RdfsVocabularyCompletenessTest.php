<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserRdf\Extractors\ClassExtractor;
use Youri\vandenBogert\Software\ParserRdf\Extractors\PropertyExtractor;

function createParsedRdfFromTurtle(string $turtle): ParsedRdf
{
    $graph = new \EasyRdf\Graph();
    $graph->parse($turtle, 'turtle');

    return new ParsedRdf(
        graph: $graph,
        format: 'turtle',
        rawContent: $turtle,
        metadata: [],
    );
}

describe('RDFS Vocabulary Completeness', function () {

    describe('rdfs:seeAlso on classes', function () {

        it('extracts rdfs:seeAlso URI array in class metadata', function () {
            $turtle = <<<'TTL'
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
                @prefix owl: <http://www.w3.org/2002/07/owl#> .
                <http://example.org/Person> a owl:Class ;
                    rdfs:label "Person" ;
                    rdfs:seeAlso <http://schema.org/Person> .
                TTL;

            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $extractor = new ClassExtractor();
            $classes = $extractor->extract($parsedRdf);

            expect($classes)->toHaveCount(1);
            expect($classes[0]['metadata'])->toHaveKey('see_also');
            expect($classes[0]['metadata']['see_also'])->toBe(['http://schema.org/Person']);
        });

        it('aggregates multiple rdfs:seeAlso values', function () {
            $turtle = <<<'TTL'
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
                @prefix owl: <http://www.w3.org/2002/07/owl#> .
                <http://example.org/Person> a owl:Class ;
                    rdfs:seeAlso <http://schema.org/Person> ;
                    rdfs:seeAlso <http://dbpedia.org/ontology/Person> .
                TTL;

            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $extractor = new ClassExtractor();
            $classes = $extractor->extract($parsedRdf);

            expect($classes[0]['metadata']['see_also'])->toHaveCount(2);
            expect($classes[0]['metadata']['see_also'])->toContain('http://schema.org/Person');
            expect($classes[0]['metadata']['see_also'])->toContain('http://dbpedia.org/ontology/Person');
        });

        it('returns empty array when no rdfs:seeAlso present', function () {
            $turtle = <<<'TTL'
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
                @prefix owl: <http://www.w3.org/2002/07/owl#> .
                <http://example.org/Thing> a owl:Class ;
                    rdfs:label "Thing" .
                TTL;

            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $extractor = new ClassExtractor();
            $classes = $extractor->extract($parsedRdf);

            expect($classes[0]['metadata'])->toHaveKey('see_also');
            expect($classes[0]['metadata']['see_also'])->toBeEmpty();
        });
    });

    describe('rdfs:seeAlso on properties', function () {

        it('extracts rdfs:seeAlso URI array in property metadata', function () {
            $turtle = <<<'TTL'
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                <http://example.org/name> a rdf:Property ;
                    rdfs:label "name" ;
                    rdfs:seeAlso <http://schema.org/name> .
                TTL;

            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $extractor = new PropertyExtractor();
            $properties = $extractor->extract($parsedRdf);

            expect($properties)->toHaveCount(1);
            expect($properties[0]['metadata'])->toHaveKey('see_also');
            expect($properties[0]['metadata']['see_also'])->toBe(['http://schema.org/name']);
        });

        it('returns empty array when no rdfs:seeAlso present on property', function () {
            $turtle = <<<'TTL'
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                <http://example.org/name> a rdf:Property ;
                    rdfs:label "name" .
                TTL;

            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $extractor = new PropertyExtractor();
            $properties = $extractor->extract($parsedRdf);

            expect($properties[0]['metadata'])->toHaveKey('see_also');
            expect($properties[0]['metadata']['see_also'])->toBeEmpty();
        });
    });

    describe('rdfs:isDefinedBy on classes', function () {

        it('extracts rdfs:isDefinedBy URI in class metadata', function () {
            $turtle = <<<'TTL'
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
                @prefix owl: <http://www.w3.org/2002/07/owl#> .
                <http://example.org/Person> a owl:Class ;
                    rdfs:isDefinedBy <http://example.org/ontology> .
                TTL;

            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $extractor = new ClassExtractor();
            $classes = $extractor->extract($parsedRdf);

            expect($classes[0]['metadata'])->toHaveKey('is_defined_by');
            expect($classes[0]['metadata']['is_defined_by'])->toBe(['http://example.org/ontology']);
        });

        it('returns empty array when no rdfs:isDefinedBy present', function () {
            $turtle = <<<'TTL'
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
                @prefix owl: <http://www.w3.org/2002/07/owl#> .
                <http://example.org/Thing> a owl:Class .
                TTL;

            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $extractor = new ClassExtractor();
            $classes = $extractor->extract($parsedRdf);

            expect($classes[0]['metadata'])->toHaveKey('is_defined_by');
            expect($classes[0]['metadata']['is_defined_by'])->toBeEmpty();
        });
    });

    describe('rdfs:isDefinedBy on properties', function () {

        it('extracts rdfs:isDefinedBy URI in property metadata', function () {
            $turtle = <<<'TTL'
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                <http://example.org/name> a rdf:Property ;
                    rdfs:isDefinedBy <http://example.org/ontology> .
                TTL;

            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $extractor = new PropertyExtractor();
            $properties = $extractor->extract($parsedRdf);

            expect($properties[0]['metadata'])->toHaveKey('is_defined_by');
            expect($properties[0]['metadata']['is_defined_by'])->toBe(['http://example.org/ontology']);
        });
    });

    describe('rdfs:Datatype recognition', function () {

        it('recognizes rdfs:Datatype as a class type', function () {
            $turtle = <<<'TTL'
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
                <http://example.org/PositiveInteger> a rdfs:Datatype ;
                    rdfs:label "Positive Integer" .
                TTL;

            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $extractor = new ClassExtractor();
            $classes = $extractor->extract($parsedRdf);

            expect($classes)->toHaveCount(1);
            expect($classes[0]['uri'])->toBe('http://example.org/PositiveInteger');
            expect($classes[0]['label'])->toBe('Positive Integer');
        });

        it('extracts rdfs:Datatype alongside rdfs:Class and owl:Class', function () {
            $turtle = <<<'TTL'
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
                @prefix owl: <http://www.w3.org/2002/07/owl#> .
                <http://example.org/Person> a owl:Class .
                <http://example.org/Animal> a rdfs:Class .
                <http://example.org/PositiveInt> a rdfs:Datatype .
                TTL;

            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $extractor = new ClassExtractor();
            $classes = $extractor->extract($parsedRdf);

            $uris = array_column($classes, 'uri');
            expect($uris)->toContain('http://example.org/Person');
            expect($uris)->toContain('http://example.org/Animal');
            expect($uris)->toContain('http://example.org/PositiveInt');
        });
    });

    describe('rdfs:Container and rdfs:Literal handling', function () {

        it('recognizes rdfs:Container declarations as class types', function () {
            $turtle = <<<'TTL'
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
                <http://example.org/MyContainer> a rdfs:Container ;
                    rdfs:label "My Container" .
                TTL;

            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $extractor = new ClassExtractor();
            $classes = $extractor->extract($parsedRdf);

            expect($classes)->toHaveCount(1);
            expect($classes[0]['uri'])->toBe('http://example.org/MyContainer');
        });

        it('recognizes rdfs:Literal declarations as class types', function () {
            $turtle = <<<'TTL'
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
                <http://example.org/MyLiteral> a rdfs:Literal ;
                    rdfs:label "My Literal" .
                TTL;

            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $extractor = new ClassExtractor();
            $classes = $extractor->extract($parsedRdf);

            expect($classes)->toHaveCount(1);
            expect($classes[0]['uri'])->toBe('http://example.org/MyLiteral');
        });
    });

    describe('full URI identifiers', function () {

        it('all extracted URIs are full URIs, never prefixed', function () {
            $turtle = <<<'TTL'
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
                @prefix owl: <http://www.w3.org/2002/07/owl#> .
                <http://example.org/Person> a owl:Class ;
                    rdfs:seeAlso <http://schema.org/Person> ;
                    rdfs:isDefinedBy <http://example.org/ontology> .
                TTL;

            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $extractor = new ClassExtractor();
            $classes = $extractor->extract($parsedRdf);

            expect($classes[0]['uri'])->toStartWith('http://');
            foreach ($classes[0]['metadata']['see_also'] as $uri) {
                expect($uri)->toStartWith('http://');
            }
            foreach ($classes[0]['metadata']['is_defined_by'] as $uri) {
                expect($uri)->toStartWith('http://');
            }
        });
    });

    describe('backward compatibility', function () {

        it('existing class extraction behavior unchanged', function () {
            $turtle = <<<'TTL'
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
                @prefix owl: <http://www.w3.org/2002/07/owl#> .
                <http://example.org/Person> a owl:Class ;
                    rdfs:label "Person"@en ;
                    rdfs:comment "A human being"@en ;
                    rdfs:subClassOf <http://example.org/LivingThing> .
                TTL;

            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $extractor = new ClassExtractor();
            $classes = $extractor->extract($parsedRdf);

            expect($classes)->toHaveCount(1);
            expect($classes[0]['uri'])->toBe('http://example.org/Person');
            expect($classes[0]['label'])->toBe('Person');
            expect($classes[0]['description'])->toBe('A human being');
            expect($classes[0]['parent_classes'])->toContain('http://example.org/LivingThing');
            expect($classes[0]['metadata']['source'])->toBe('easyrdf');
        });

        it('existing property extraction behavior unchanged', function () {
            $turtle = <<<'TTL'
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
                @prefix owl: <http://www.w3.org/2002/07/owl#> .
                <http://example.org/name> a owl:DatatypeProperty ;
                    rdfs:label "name"@en ;
                    rdfs:domain <http://example.org/Person> ;
                    rdfs:range <http://www.w3.org/2001/XMLSchema#string> .
                TTL;

            $parsedRdf = createParsedRdfFromTurtle($turtle);
            $extractor = new PropertyExtractor();
            $properties = $extractor->extract($parsedRdf);

            expect($properties)->toHaveCount(1);
            expect($properties[0]['uri'])->toBe('http://example.org/name');
            expect($properties[0]['property_type'])->toBe('datatype');
            expect($properties[0]['domain'])->toContain('http://example.org/Person');
            expect($properties[0]['metadata']['source'])->toBe('easyrdf');
        });
    });
});
