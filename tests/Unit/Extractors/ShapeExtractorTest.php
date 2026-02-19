<?php

declare(strict_types=1);

use EasyRdf\Graph;
use EasyRdf\RdfNamespace;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserRdf\Extractors\ShapeExtractor;

describe('ShapeExtractor (new namespace)', function () {

    beforeEach(function () {
        $this->extractor = new ShapeExtractor();
    });

    it('extracts sh:NodeShape from Turtle content', function () {
        $content = <<<'TTL'
            @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
            @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
            @prefix sh: <http://www.w3.org/ns/shacl#> .

            <http://example.org/PersonShape> a sh:NodeShape ;
                sh:targetClass <http://example.org/Person> ;
                rdfs:label "Person Shape" ;
                rdfs:comment "Shape for Person" .
            TTL;

        $graph = new Graph();
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        expect($result)->toHaveCount(1);
        expect($result[0]['uri'])->toBe('http://example.org/PersonShape');
    });

    it('extracts sh:PropertyShape from Turtle content', function () {
        $content = <<<'TTL'
            @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
            @prefix sh: <http://www.w3.org/ns/shacl#> .

            <http://example.org/NameShape> a sh:PropertyShape ;
                sh:path <http://example.org/name> .
            TTL;

        $graph = new Graph();
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        expect($result)->toHaveCount(1);
        expect($result[0]['target_property'])->toBe('http://example.org/name');
    });

    it('shape output has all required keys', function () {
        $content = <<<'TTL'
            @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
            @prefix sh: <http://www.w3.org/ns/shacl#> .

            <http://example.org/PersonShape> a sh:NodeShape .
            TTL;

        $graph = new Graph();
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        expect($result[0])->toHaveKeys([
            'uri', 'label', 'description', 'target_class', 'target_node',
            'target_subjects_of', 'target_objects_of', 'target_property',
            'property_shapes', 'constraints', 'metadata',
        ]);
    });

    it('target_class from sh:targetClass returns full URI', function () {
        $content = <<<'TTL'
            @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
            @prefix sh: <http://www.w3.org/ns/shacl#> .

            <http://example.org/PersonShape> a sh:NodeShape ;
                sh:targetClass <http://example.org/Person> .
            TTL;

        $graph = new Graph();
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        expect($result[0]['target_class'])->toBe('http://example.org/Person');
    });

    it('property shapes extracted via sh:property', function () {
        $content = <<<'TTL'
            @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
            @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
            @prefix sh: <http://www.w3.org/ns/shacl#> .
            @prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

            <http://example.org/PersonShape> a sh:NodeShape ;
                sh:targetClass <http://example.org/Person> ;
                sh:property [
                    sh:path <http://example.org/name> ;
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

        $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        expect($result[0]['property_shapes'])->not->toBeEmpty();
        $propShape = $result[0]['property_shapes'][0];
        expect($propShape['path'])->toBe('http://example.org/name');
        expect($propShape)->toHaveKey('datatype');
        expect($propShape)->toHaveKey('minCount');
    });

    it('property shapes without sh:path are filtered out', function () {
        $content = <<<'TTL'
            @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
            @prefix sh: <http://www.w3.org/ns/shacl#> .

            <http://example.org/PersonShape> a sh:NodeShape ;
                sh:property [
                    sh:minCount 1 ;
                ] .
            TTL;

        $graph = new Graph();
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        expect($result[0]['property_shapes'])->toBe([]);
    });

    it('property shape array_filter removes null values', function () {
        $content = <<<'TTL'
            @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
            @prefix sh: <http://www.w3.org/ns/shacl#> .

            <http://example.org/PersonShape> a sh:NodeShape ;
                sh:property [
                    sh:path <http://example.org/name> ;
                ] .
            TTL;

        $graph = new Graph();
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        $propShape = $result[0]['property_shapes'][0];
        // null values should have been filtered out by array_filter
        expect($propShape)->toHaveKey('path');
        expect($propShape)->not->toHaveKey('datatype');
        expect($propShape)->not->toHaveKey('nodeKind');
    });

    it('shape constraints extracted with sh: prefix stripped', function () {
        $content = <<<'TTL'
            @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
            @prefix sh: <http://www.w3.org/ns/shacl#> .
            @prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

            <http://example.org/NameShape> a sh:PropertyShape ;
                sh:path <http://example.org/name> ;
                sh:minCount 1 ;
                sh:maxCount 5 ;
                sh:datatype xsd:string .
            TTL;

        $graph = new Graph();
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        expect($result[0]['constraints'])->toHaveKey('minCount');
        expect($result[0]['constraints'])->toHaveKey('maxCount');
        expect($result[0]['constraints'])->toHaveKey('datatype');
        // Keys should NOT have sh: prefix
        expect($result[0]['constraints'])->not->toHaveKey('sh:minCount');
    });

    it('returns empty array for rdf/xml format', function () {
        $graph = new Graph();
        $parsedRdf = new ParsedRdf(graph: $graph, format: 'rdf/xml', rawContent: '', metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        expect($result)->toBe([]);
    });

    it('returns empty array for content without SHACL shapes', function () {
        $content = '<http://example.org/s> <http://example.org/p> <http://example.org/o> .';

        $graph = new Graph();
        $graph->parse($content, 'ntriples');

        $parsedRdf = new ParsedRdf(graph: $graph, format: 'n-triples', rawContent: $content, metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        expect($result)->toBe([]);
    });

    it('metadata source is easyrdf', function () {
        $content = <<<'TTL'
            @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
            @prefix sh: <http://www.w3.org/ns/shacl#> .

            <http://example.org/PersonShape> a sh:NodeShape .
            TTL;

        $graph = new Graph();
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        expect($result[0]['metadata']['source'])->toBe('easyrdf');
    });

    it('metadata types contains shape type URIs', function () {
        $content = <<<'TTL'
            @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
            @prefix sh: <http://www.w3.org/ns/shacl#> .

            <http://example.org/PersonShape> a sh:NodeShape .
            TTL;

        $graph = new Graph();
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        expect($result[0]['metadata']['types'])->toContain('http://www.w3.org/ns/shacl#NodeShape');
    });

    it('metadata annotations is an array', function () {
        $content = <<<'TTL'
            @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
            @prefix sh: <http://www.w3.org/ns/shacl#> .

            <http://example.org/PersonShape> a sh:NodeShape .
            TTL;

        $graph = new Graph();
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        expect($result[0]['metadata']['annotations'])->toBeArray();
    });

    it('RdfNamespace sh and dct are registered after extraction', function () {
        $content = <<<'TTL'
            @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
            @prefix sh: <http://www.w3.org/ns/shacl#> .

            <http://example.org/PersonShape> a sh:NodeShape .
            TTL;

        $graph = new Graph();
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
        $this->extractor->extract($parsedRdf);

        $namespaces = RdfNamespace::namespaces();
        expect($namespaces)->toHaveKey('sh');
        expect($namespaces)->toHaveKey('dct');
    });

    it('extracts multiple shapes from content', function () {
        $content = <<<'TTL'
            @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
            @prefix sh: <http://www.w3.org/ns/shacl#> .

            <http://example.org/PersonShape> a sh:NodeShape ;
                sh:targetClass <http://example.org/Person> .
            <http://example.org/NameShape> a sh:PropertyShape ;
                sh:path <http://example.org/name> .
            TTL;

        $graph = new Graph();
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        expect($result)->toHaveCount(2);
    });

    it('shape with no label/description returns null', function () {
        $content = <<<'TTL'
            @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
            @prefix sh: <http://www.w3.org/ns/shacl#> .

            <http://example.org/PersonShape> a sh:NodeShape .
            TTL;

        $graph = new Graph();
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        expect($result[0]['label'])->toBeNull();
        expect($result[0]['description'])->toBeNull();
    });

    it('property_shapes defaults to empty array when no sh:property present', function () {
        $content = <<<'TTL'
            @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
            @prefix sh: <http://www.w3.org/ns/shacl#> .

            <http://example.org/PersonShape> a sh:NodeShape .
            TTL;

        $graph = new Graph();
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        expect($result[0]['property_shapes'])->toBe([]);
    });

    it('constraints defaults to empty array when no constraint properties present', function () {
        $content = <<<'TTL'
            @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
            @prefix sh: <http://www.w3.org/ns/shacl#> .

            <http://example.org/PersonShape> a sh:NodeShape .
            TTL;

        $graph = new Graph();
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        expect($result[0]['constraints'])->toBe([]);
    });

    it('is a final class', function () {
        $reflection = new ReflectionClass(ShapeExtractor::class);
        expect($reflection->isFinal())->toBeTrue();
    });

    it('integrates with RdfParser -- shapes populated in ParsedOntology', function () {
        $parser = new \Youri\vandenBogert\Software\ParserRdf\RdfParser();
        $content = <<<'TTL'
            @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
            @prefix sh: <http://www.w3.org/ns/shacl#> .

            <http://example.org/PersonShape> a sh:NodeShape ;
                sh:targetClass <http://example.org/Person> .
            TTL;

        $result = $parser->parse($content);

        expect($result->shapes)->toBeArray();
        expect($result->shapes)->toHaveKey('http://example.org/PersonShape');
    });
});
