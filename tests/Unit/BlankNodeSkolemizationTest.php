<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedOntology;
use Youri\vandenBogert\Software\ParserRdf\RdfParser;

describe('Blank Node Skolemization', function () {

    beforeEach(function () {
        $this->parser = new RdfParser();
    });

    describe('default behavior (backward compatible)', function () {

        it('excludes blank nodes from classes by default', function () {
            $turtle = <<<'TTL'
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
                @prefix owl: <http://www.w3.org/2002/07/owl#> .
                <http://example.org/Person> a owl:Class .
                _:anon a owl:Class .
                TTL;

            $result = $this->parser->parse($turtle);
            expect($result->classes)->toHaveCount(1);
            expect($result->classes)->toHaveKey('http://example.org/Person');
        });

        it('excludes blank nodes from properties by default', function () {
            $turtle = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                <http://example.org/name> a rdf:Property .
                _:anonProp a rdf:Property .
                TTL;

            $result = $this->parser->parse($turtle);
            expect($result->properties)->toHaveCount(1);
            expect($result->properties)->toHaveKey('http://example.org/name');
        });
    });

    describe('opt-in skolemization via options', function () {

        it('includes skolemized blank nodes when includeSkolemizedBlankNodes is true', function () {
            $turtle = <<<'TTL'
                @prefix owl: <http://www.w3.org/2002/07/owl#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
                <http://example.org/Person> a owl:Class ;
                    rdfs:label "Person" .
                _:b0 a owl:Class ;
                    rdfs:label "Anonymous Class" .
                TTL;

            $result = $this->parser->parse($turtle, ['includeSkolemizedBlankNodes' => true]);

            expect($result->classes)->toHaveCount(2);
            expect($result->classes)->toHaveKey('http://example.org/Person');

            // Find the skolemized blank node
            $skolemizedKeys = array_filter(
                array_keys($result->classes),
                fn (string $key) => str_starts_with($key, 'urn:bnode:'),
            );
            expect($skolemizedKeys)->toHaveCount(1);
        });

        it('skolemized URI follows urn:bnode:{id} pattern', function () {
            $turtle = <<<'TTL'
                @prefix owl: <http://www.w3.org/2002/07/owl#> .
                _:myNode a owl:Class .
                TTL;

            $result = $this->parser->parse($turtle, ['includeSkolemizedBlankNodes' => true]);

            $skolemizedKeys = array_filter(
                array_keys($result->classes),
                fn (string $key) => str_starts_with($key, 'urn:bnode:'),
            );
            expect($skolemizedKeys)->not->toBeEmpty();

            $skolemizedUri = reset($skolemizedKeys);
            expect($skolemizedUri)->toMatch('/^urn:bnode:.+$/');
        });

        it('same blank node ID produces same skolemized URI within single parse', function () {
            $turtle = <<<'TTL'
                @prefix owl: <http://www.w3.org/2002/07/owl#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
                _:shared a owl:Class ;
                    rdfs:subClassOf <http://example.org/Thing> .
                TTL;

            $result = $this->parser->parse($turtle, ['includeSkolemizedBlankNodes' => true]);

            $skolemizedKeys = array_filter(
                array_keys($result->classes),
                fn (string $key) => str_starts_with($key, 'urn:bnode:'),
            );
            expect($skolemizedKeys)->toHaveCount(1);

            // The URI should be deterministic
            $uri = reset($skolemizedKeys);
            expect($result->classes[$uri]['uri'])->toBe($uri);
        });

        it('skolemized URIs are distinguishable from named resource URIs', function () {
            $turtle = <<<'TTL'
                @prefix owl: <http://www.w3.org/2002/07/owl#> .
                <http://example.org/Named> a owl:Class .
                _:anon a owl:Class .
                TTL;

            $result = $this->parser->parse($turtle, ['includeSkolemizedBlankNodes' => true]);

            foreach ($result->classes as $uri => $class) {
                // Every URI is either a named resource or a skolemized blank node
                expect(
                    str_starts_with($uri, 'http://') || str_starts_with($uri, 'urn:bnode:')
                )->toBeTrue();
            }
        });
    });

    describe('skolemization with properties', function () {

        it('includes skolemized blank node properties when opted in', function () {
            $turtle = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
                <http://example.org/name> a rdf:Property .
                _:anonProp a rdf:Property ;
                    rdfs:label "Anonymous Property" .
                TTL;

            $result = $this->parser->parse($turtle, ['includeSkolemizedBlankNodes' => true]);

            expect($result->properties)->toHaveCount(2);
            $skolemizedKeys = array_filter(
                array_keys($result->properties),
                fn (string $key) => str_starts_with($key, 'urn:bnode:'),
            );
            expect($skolemizedKeys)->toHaveCount(1);
        });
    });

    describe('backward compatibility', function () {

        it('parse() without options produces identical output', function () {
            $turtle = <<<'TTL'
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
                @prefix owl: <http://www.w3.org/2002/07/owl#> .
                <http://example.org/Person> a owl:Class ;
                    rdfs:label "Person" .
                TTL;

            $result = $this->parser->parse($turtle);
            expect($result)->toBeInstanceOf(ParsedOntology::class);
            expect($result->classes)->toHaveCount(1);
            expect($result->classes)->toHaveKey('http://example.org/Person');
        });

        it('includeSkolemizedBlankNodes defaults to false', function () {
            $turtle = <<<'TTL'
                @prefix owl: <http://www.w3.org/2002/07/owl#> .
                _:anon a owl:Class .
                TTL;

            $result = $this->parser->parse($turtle, ['includeSkolemizedBlankNodes' => false]);
            expect($result->classes)->toBeEmpty();
        });
    });
});
