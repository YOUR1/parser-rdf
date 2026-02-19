<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserRdf\Extractors\ShapeExtractor;
use EasyRdf\Graph;
use EasyRdf\RdfNamespace;

describe('ShapeExtractor', function () {

    beforeEach(function () {
        $this->extractor = new ShapeExtractor();
    });

    describe('extract()', function () {

        it('extracts shapes from Turtle content with sh:NodeShape declaration', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    rdfs:label "Person Shape" .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $shapes = $this->extractor->extract($parsedRdf);
            expect($shapes)->not->toBeEmpty();

            $uris = array_column($shapes, 'uri');
            expect($uris)->toContain('http://example.org/PersonShape');
        });

        it('extracts shapes from Turtle content with sh:PropertyShape declaration', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:NameShape a sh:PropertyShape ;
    sh:path ex:name ;
    sh:minCount 1 .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $shapes = $this->extractor->extract($parsedRdf);
            $uris = array_column($shapes, 'uri');
            expect($uris)->toContain('http://example.org/NameShape');
        });

        it('shape output has required keys', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    rdfs:label "Person Shape" ;
    rdfs:comment "Shape for Person" .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $shapes = $this->extractor->extract($parsedRdf);
            $shape = $shapes[0];
            expect($shape)->toHaveKeys([
                'uri', 'label', 'description', 'target_class', 'target_node',
                'target_subjects_of', 'target_objects_of', 'target_property',
                'property_shapes', 'constraints', 'metadata',
            ]);
        });

        it('shape target_class from sh:targetClass (URI string or null)', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $shapes = $this->extractor->extract($parsedRdf);
            expect($shapes[0]['target_class'])->toBe('http://example.org/Person');
        });

        it('shape target_node from sh:targetNode (URI string or null)', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:SpecificShape a sh:NodeShape ;
    sh:targetNode ex:SpecificInstance .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $shapes = $this->extractor->extract($parsedRdf);
            expect($shapes[0]['target_node'])->toBe('http://example.org/SpecificInstance');
        });

        it('shape target_subjects_of from sh:targetSubjectsOf', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:SubjectShape a sh:NodeShape ;
    sh:targetSubjectsOf ex:knows .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $shapes = $this->extractor->extract($parsedRdf);
            expect($shapes[0]['target_subjects_of'])->toBe('http://example.org/knows');
        });

        it('shape target_objects_of from sh:targetObjectsOf', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:ObjectShape a sh:NodeShape ;
    sh:targetObjectsOf ex:knows .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $shapes = $this->extractor->extract($parsedRdf);
            expect($shapes[0]['target_objects_of'])->toBe('http://example.org/knows');
        });

        it('shape target_property from sh:path', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:NameShape a sh:PropertyShape ;
    sh:path ex:name .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $shapes = $this->extractor->extract($parsedRdf);
            expect($shapes[0]['target_property'])->toBe('http://example.org/name');
        });

        it('extracts property_shapes with required keys from sh:property', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
@prefix ex: <http://example.org/> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:datatype xsd:string ;
        sh:minCount 1 ;
        sh:maxCount 1 ;
        rdfs:label "Name"@en ;
        sh:name "name" ;
        sh:description "The name of the person" ;
    ] .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $shapes = $this->extractor->extract($parsedRdf);
            $personShape = null;
            foreach ($shapes as $s) {
                if ($s['uri'] === 'http://example.org/PersonShape') {
                    $personShape = $s;
                }
            }
            expect($personShape)->not->toBeNull();
            expect($personShape['property_shapes'])->not->toBeEmpty();

            $propShape = $personShape['property_shapes'][0];
            expect($propShape)->toHaveKey('path');
            expect($propShape['path'])->toBe('http://example.org/name');
        });

        it('property shape requires non-empty path (shapes without path are filtered out)', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:minCount 1 ;
    ] ;
    sh:property [
        sh:minCount 1 ;
    ] .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $shapes = $this->extractor->extract($parsedRdf);
            $personShape = null;
            foreach ($shapes as $s) {
                if ($s['uri'] === 'http://example.org/PersonShape') {
                    $personShape = $s;
                }
            }
            expect($personShape)->not->toBeNull();
            // Only the property shape with a path should be present
            foreach ($personShape['property_shapes'] as $propShape) {
                expect($propShape)->toHaveKey('path');
                expect($propShape['path'])->not->toBeEmpty();
            }
        });

        it('property shape array_filter removes null values from output', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:minCount 1 ;
    ] .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $shapes = $this->extractor->extract($parsedRdf);
            $personShape = null;
            foreach ($shapes as $s) {
                if ($s['uri'] === 'http://example.org/PersonShape') {
                    $personShape = $s;
                }
            }
            $propShape = $personShape['property_shapes'][0];
            // array_filter removes null/empty values -- keys not set should not appear
            foreach ($propShape as $key => $value) {
                expect($value)->not->toBeNull();
            }
        });

        it('shape constraints extraction: keys are CONSTRAINT_PROPERTIES without sh: prefix', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
@prefix ex: <http://example.org/> .

ex:NameShape a sh:PropertyShape ;
    sh:path ex:name ;
    sh:minCount 1 ;
    sh:maxCount 5 ;
    sh:datatype xsd:string .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $shapes = $this->extractor->extract($parsedRdf);
            $nameShape = null;
            foreach ($shapes as $s) {
                if ($s['uri'] === 'http://example.org/NameShape') {
                    $nameShape = $s;
                }
            }
            expect($nameShape)->not->toBeNull();
            expect($nameShape['constraints'])->toBeArray();
            // Constraints should use keys without 'sh:' prefix
            if (!empty($nameShape['constraints'])) {
                foreach (array_keys($nameShape['constraints']) as $key) {
                    expect($key)->not->toStartWith('sh:');
                }
            }
        });

        it('returns empty array for rdf/xml format (early return, no graph processing)', function () {
            $xmlContent = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:sh="http://www.w3.org/ns/shacl#"
         xmlns:ex="http://example.org/">
    <sh:NodeShape rdf:about="http://example.org/PersonShape">
        <sh:targetClass rdf:resource="http://example.org/Person"/>
    </sh:NodeShape>
</rdf:RDF>';
            $graph = new Graph();
            $parsedRdf = new ParsedRdf($graph, 'rdf/xml', $xmlContent, ['format' => 'rdf/xml']);

            $shapes = $this->extractor->extract($parsedRdf);
            expect($shapes)->toBeArray()->toBeEmpty();
        });

        it('global side effect: RdfNamespace::set(sh, ...) and RdfNamespace::set(dct, ...) called during extraction', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $this->extractor->extract($parsedRdf);

            // After extraction, global namespaces should have 'sh' and 'dct'
            $namespaces = RdfNamespace::namespaces();
            expect($namespaces)->toHaveKey('sh');
            expect($namespaces['sh'])->toBe('http://www.w3.org/ns/shacl#');
            expect($namespaces)->toHaveKey('dct');
            expect($namespaces['dct'])->toBe('http://purl.org/dc/terms/');
        });

        it('returns empty shapes array for content without SHACL shapes', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/Person> a rdfs:Class ;
    rdfs:label "Person" .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $shapes = $this->extractor->extract($parsedRdf);
            expect($shapes)->toBeArray()->toBeEmpty();
        });

        it('blank node shape URIs are skipped (uri must be non-null)', function () {
            // Blank node shapes inline within sh:property are not extracted as top-level shapes
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:minCount 1 ;
    ] .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $shapes = $this->extractor->extract($parsedRdf);
            // No shape should have a blank node URI
            foreach ($shapes as $shape) {
                expect($shape['uri'])->not->toStartWith('_:');
                expect($shape['uri'])->not->toBeNull();
            }
        });

        it('shape metadata[source] = easyrdf', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $shapes = $this->extractor->extract($parsedRdf);
            expect($shapes[0]['metadata']['source'])->toBe('easyrdf');
        });

        it('shape metadata[types] contains shape type URIs', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $shapes = $this->extractor->extract($parsedRdf);
            expect($shapes[0]['metadata']['types'])->toBeArray();
            expect($shapes[0]['metadata']['types'])->toContain('http://www.w3.org/ns/shacl#NodeShape');
        });
    });
});
