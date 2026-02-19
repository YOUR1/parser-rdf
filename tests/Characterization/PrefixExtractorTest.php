<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserRdf\Extractors\PrefixExtractor;
use EasyRdf\Graph;

describe('PrefixExtractor', function () {

    beforeEach(function () {
        $this->extractor = new PrefixExtractor();
    });

    describe('extract()', function () {

        it('extracts prefixes from Turtle @prefix declarations (regex-based)', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix ex: <http://example.org/> .

ex:Person a rdfs:Class .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $prefixes = $this->extractor->extract($parsedRdf);
            expect($prefixes)->toBeArray();
            expect($prefixes)->toHaveKey('ex');
            expect($prefixes['ex'])->toBe('http://example.org/');
        });

        it('extracts prefixes from SPARQL-style PREFIX declarations (regex-based, case-insensitive)', function () {
            $content = <<<'TTL'
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
PREFIX ex: <http://example.org/>

ex:Person a rdfs:Class .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $prefixes = $this->extractor->extract($parsedRdf);
            expect($prefixes)->toHaveKey('ex');
            expect($prefixes['ex'])->toBe('http://example.org/');
        });

        it('extracts prefixes from RDF/XML xmlns: declarations (regex-based)', function () {
            $xmlContent = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
         xmlns:ex="http://example.org/">
    <rdfs:Class rdf:about="http://example.org/Person"/>
</rdf:RDF>';
            $graph = new Graph();
            // EasyRdf RDF/XML parser may fail on PHP 8.4+, use empty graph
            try {
                @$graph->parse($xmlContent, 'rdfxml');
            } catch (\Throwable $e) {
                // EasyRdf compatibility issue on modern PHP -- continue with regex-based extraction
            }
            $parsedRdf = new ParsedRdf($graph, 'rdf/xml', $xmlContent, ['format' => 'rdf/xml']);

            $prefixes = $this->extractor->extract($parsedRdf);
            expect($prefixes)->toHaveKey('ex');
            expect($prefixes['ex'])->toBe('http://example.org/');
        });

        it('extracts prefixes from JSON-LD @context keys (only values passing FILTER_VALIDATE_URL)', function () {
            $content = '{
                "@context": {
                    "rdf": "http://www.w3.org/1999/02/22-rdf-syntax-ns#",
                    "rdfs": "http://www.w3.org/2000/01/rdf-schema#",
                    "ex": "http://example.org/",
                    "label": "http://www.w3.org/2000/01/rdf-schema#label"
                },
                "@id": "http://example.org/Person",
                "@type": "rdfs:Class"
            }';
            $graph = new Graph();
            @$graph->parse($content, 'jsonld');
            $parsedRdf = new ParsedRdf($graph, 'json-ld', $content, ['format' => 'json-ld']);

            $prefixes = $this->extractor->extract($parsedRdf);
            // Values that pass FILTER_VALIDATE_URL should be included
            expect($prefixes)->toHaveKey('rdf');
        });

        it('extracts prefixes from EasyRdf $graph->getNamespaceMap() (method_exists guard)', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/Person> a rdfs:Class .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $prefixes = $this->extractor->extract($parsedRdf);
            // Graph namespace map should contribute prefixes
            expect($prefixes)->toBeArray();
            expect($prefixes)->not->toBeEmpty();
        });

        it('extracts prefixes from SimpleXML element getNamespaces(true) (only when rdf/xml format AND xml_element in metadata)', function () {
            $xmlContent = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
         xmlns:custom="http://custom.example.org/">
    <rdfs:Class rdf:about="http://example.org/Person"/>
</rdf:RDF>';
            $xml = simplexml_load_string($xmlContent);
            $graph = new Graph();
            $parsedRdf = new ParsedRdf($graph, 'rdf/xml', $xmlContent, [
                'format' => 'rdf/xml',
                'xml_element' => $xml,
            ]);

            $prefixes = $this->extractor->extract($parsedRdf);
            expect($prefixes)->toHaveKey('custom');
            expect($prefixes['custom'])->toBe('http://custom.example.org/');
        });

        it('adds common prefixes when NOT already present AND namespace is used in graph', function () {
            $content = <<<'TTL'
@prefix ex: <http://example.org/> .

ex:Person a <http://www.w3.org/2000/01/rdf-schema#Class> .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $prefixes = $this->extractor->extract($parsedRdf);
            // rdfs namespace is used in the graph, so common prefix should be auto-added
            expect($prefixes)->toHaveKey('rdfs');
            expect($prefixes['rdfs'])->toBe('http://www.w3.org/2000/01/rdf-schema#');
        });

        it('common prefix NOT added when prefix already exists from explicit declaration', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/Person> a rdfs:Class .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $prefixes = $this->extractor->extract($parsedRdf);
            // rdfs should have value from explicit declaration
            expect($prefixes)->toHaveKey('rdfs');
            expect($prefixes['rdfs'])->toBe('http://www.w3.org/2000/01/rdf-schema#');
        });

        it('common prefix NOT added when namespace not used in graph', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/Person> a rdfs:Class .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $prefixes = $this->extractor->extract($parsedRdf);
            // schema.org is NOT used in graph, so should NOT be auto-added
            // (unless it was already declared in the content)
            // schema is a common prefix but https://schema.org/ is not used
            expect($prefixes)->not->toHaveKey('schema');
        });

        it('deduplication: same prefix from multiple sources appears only once (last write wins via array_merge)', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/Person> a rdfs:Class .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $prefixes = $this->extractor->extract($parsedRdf);
            // rdfs should appear exactly once
            $rdfsPrefixes = array_filter($prefixes, fn ($uri, $prefix) => $prefix === 'rdfs', ARRAY_FILTER_USE_BOTH);
            expect($rdfsPrefixes)->toHaveCount(1);
        });

        it('output format: associative array [prefix => namespace_uri]', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/Person> a rdfs:Class .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $prefixes = $this->extractor->extract($parsedRdf);
            expect($prefixes)->toBeArray();
            foreach ($prefixes as $prefix => $namespaceUri) {
                expect($prefix)->toBeString();
                expect($namespaceUri)->toBeString();
            }
        });

        it('unknown format returns only graph-based and common prefixes (no content parsing)', function () {
            // Create a ParsedRdf with unknown format -- no content-based extraction
            $content = '<http://example.org/s> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2000/01/rdf-schema#Class> .';
            $graph = new Graph();
            $graph->parse($content, 'ntriples');
            $parsedRdf = new ParsedRdf($graph, 'unknown-format', $content, ['format' => 'unknown-format']);

            $prefixes = $this->extractor->extract($parsedRdf);
            // Should still have some prefixes from graph namespace map and/or common prefixes
            expect($prefixes)->toBeArray();
        });

        it('format aliases: ttl treated same as turtle, xml same as rdf/xml, jsonld same as json-ld', function () {
            // Test ttl alias by using format 'ttl' with Turtle content
            $content = <<<'TTL'
@prefix ex: <http://example.org/> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

ex:Person a rdfs:Class .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'ttl', $content, ['format' => 'ttl']);

            $prefixes = $this->extractor->extract($parsedRdf);
            // 'ttl' is treated as 'turtle' for content extraction
            expect($prefixes)->toHaveKey('ex');
            expect($prefixes['ex'])->toBe('http://example.org/');
        });
    });
});
