<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedOntology;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserRdf\RdfParser;

describe('Named Graph / RDF Dataset Support', function () {

    beforeEach(function () {
        $this->parser = new RdfParser();
    });

    describe('ParsedOntology graph-aware data model', function () {

        it('has a graphs property that defaults to empty array', function () {
            $ontology = new ParsedOntology();
            expect($ontology->graphs)->toBeArray()->toBeEmpty();
        });

        it('accepts graphs parameter with ParsedRdf values keyed by graph URI', function () {
            $graph = new \EasyRdf\Graph('http://example.org/graph1');
            $parsedRdf = new ParsedRdf(
                graph: $graph,
                format: 'n-triples',
                rawContent: '',
                metadata: [],
            );

            $ontology = new ParsedOntology(
                graphs: ['http://example.org/graph1' => $parsedRdf],
            );

            expect($ontology->graphs)->toHaveCount(1);
            expect($ontology->graphs)->toHaveKey('http://example.org/graph1');
            expect($ontology->graphs['http://example.org/graph1'])->toBeInstanceOf(ParsedRdf::class);
        });

        it('graphs property is readonly', function () {
            $ontology = new ParsedOntology();
            $reflection = new ReflectionProperty(ParsedOntology::class, 'graphs');
            expect($reflection->isReadOnly())->toBeTrue();
        });
    });

    describe('default graph preservation', function () {

        it('single default graph content produces identical output to current behavior', function () {
            $content = '<http://example.org/s> <http://example.org/p> <http://example.org/o> .';
            $result = $this->parser->parse($content);

            expect($result)->toBeInstanceOf(ParsedOntology::class);
            expect($result->metadata['format'])->toBe('n-triples');
            expect($result->classes)->toBeArray();
            expect($result->properties)->toBeArray();
            expect($result->prefixes)->toBeArray();
        });

        it('parsed content populates graphs with default graph', function () {
            $content = '<http://example.org/s> <http://example.org/p> <http://example.org/o> .';
            $result = $this->parser->parse($content);

            expect($result->graphs)->not->toBeEmpty();
            expect($result->graphs)->toHaveKey('_:default');
            expect($result->graphs['_:default'])->toBeInstanceOf(ParsedRdf::class);
        });

        it('Turtle content with no named graphs continues to work unchanged', function () {
            $content = "@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .\n"
                     . "<http://example.org/Person> rdf:type <http://www.w3.org/2000/01/rdf-schema#Class> .";
            $result = $this->parser->parse($content);

            expect($result)->toBeInstanceOf(ParsedOntology::class);
            expect($result->metadata['format'])->toBe('turtle');
            expect($result->graphs)->toHaveKey('_:default');
        });
    });

    describe('backward compatibility with OwlParser and ShaclParser construction', function () {

        it('ParsedOntology can be constructed with all existing named params without graphs', function () {
            $ontology = new ParsedOntology(
                classes: ['http://example.org/Foo' => ['uri' => 'http://example.org/Foo']],
                properties: [],
                prefixes: ['ex' => 'http://example.org/'],
                shapes: [],
                restrictions: [],
                metadata: ['format' => 'turtle'],
                rawContent: 'test content',
            );

            expect($ontology->classes)->toHaveCount(1);
            expect($ontology->graphs)->toBeEmpty();
        });

        it('ParsedOntology can be constructed with graphs alongside existing params', function () {
            $graph = new \EasyRdf\Graph('http://example.org/g1');
            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: '', metadata: []);

            $ontology = new ParsedOntology(
                classes: [],
                properties: [],
                prefixes: [],
                shapes: [],
                restrictions: [],
                metadata: ['format' => 'turtle'],
                rawContent: 'test content',
                graphs: ['http://example.org/g1' => $parsedRdf],
            );

            expect($ontology->graphs)->toHaveCount(1);
            expect($ontology->rawContent)->toBe('test content');
        });
    });

    describe('accessing triples by graph', function () {

        it('graphs keyed by URI provide access to individual ParsedRdf instances', function () {
            $graph1 = new \EasyRdf\Graph('http://example.org/graph1');
            $graph1->addResource('http://example.org/s1', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type', 'http://example.org/ClassA');

            $graph2 = new \EasyRdf\Graph('http://example.org/graph2');
            $graph2->addResource('http://example.org/s2', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type', 'http://example.org/ClassB');

            $parsedRdf1 = new ParsedRdf(graph: $graph1, format: 'n-triples', rawContent: '', metadata: []);
            $parsedRdf2 = new ParsedRdf(graph: $graph2, format: 'n-triples', rawContent: '', metadata: []);

            $ontology = new ParsedOntology(
                graphs: [
                    'http://example.org/graph1' => $parsedRdf1,
                    'http://example.org/graph2' => $parsedRdf2,
                ],
            );

            expect($ontology->graphs)->toHaveCount(2);

            $g1Resources = $ontology->graphs['http://example.org/graph1']->getResources();
            expect($g1Resources)->not->toBeEmpty();

            $g2Resources = $ontology->graphs['http://example.org/graph2']->getResources();
            expect($g2Resources)->not->toBeEmpty();
        });

        it('default graph uses _:default sentinel key', function () {
            $defaultGraph = new \EasyRdf\Graph();
            $defaultGraph->addResource('http://example.org/s', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type', 'http://example.org/Thing');

            $parsedRdf = new ParsedRdf(graph: $defaultGraph, format: 'n-triples', rawContent: '', metadata: []);

            $ontology = new ParsedOntology(
                graphs: ['_:default' => $parsedRdf],
            );

            expect($ontology->graphs)->toHaveKey('_:default');
            expect($ontology->graphs['_:default']->getResources())->not->toBeEmpty();
        });
    });

    describe('mixed named and default graph content', function () {

        it('supports both default and named graphs simultaneously', function () {
            $defaultGraph = new \EasyRdf\Graph();
            $defaultGraph->addResource('http://example.org/s', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type', 'http://example.org/DefaultThing');

            $namedGraph = new \EasyRdf\Graph('http://example.org/named');
            $namedGraph->addResource('http://example.org/s2', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type', 'http://example.org/NamedThing');

            $defaultParsedRdf = new ParsedRdf(graph: $defaultGraph, format: 'turtle', rawContent: '', metadata: []);
            $namedParsedRdf = new ParsedRdf(graph: $namedGraph, format: 'turtle', rawContent: '', metadata: []);

            $ontology = new ParsedOntology(
                graphs: [
                    '_:default' => $defaultParsedRdf,
                    'http://example.org/named' => $namedParsedRdf,
                ],
            );

            expect($ontology->graphs)->toHaveCount(2);
            expect($ontology->graphs)->toHaveKey('_:default');
            expect($ontology->graphs)->toHaveKey('http://example.org/named');
        });
    });
});
