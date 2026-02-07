<?php

use App\Services\Ontology\Parsers\Extractors\PrefixExtractor;
use App\Services\Ontology\Parsers\ValueObjects\ParsedRdf;
use EasyRdf\Graph;

describe('PrefixExtractor', function () {
    beforeEach(function () {
        $this->extractor = new PrefixExtractor;
    });

    it('extracts prefixes from Turtle content', function () {
        $content = '
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix foaf: <http://xmlns.com/foaf/0.1/> .
@prefix dc: <http://purl.org/dc/elements/1.1/> .

foaf:Person a rdfs:Class .
        ';

        $graph = new Graph;
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);
        $prefixes = $this->extractor->extract($parsedRdf);

        expect($prefixes)->toHaveKey('rdf');
        expect($prefixes['rdf'])->toBe('http://www.w3.org/1999/02/22-rdf-syntax-ns#');
        expect($prefixes)->toHaveKey('rdfs');
        expect($prefixes['rdfs'])->toBe('http://www.w3.org/2000/01/rdf-schema#');
        expect($prefixes)->toHaveKey('foaf');
        expect($prefixes['foaf'])->toBe('http://xmlns.com/foaf/0.1/');
        expect($prefixes)->toHaveKey('dc');
        expect($prefixes['dc'])->toBe('http://purl.org/dc/elements/1.1/');
    });

    it('extracts SPARQL-style PREFIX declarations', function () {
        $content = '
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
PREFIX foaf: <http://xmlns.com/foaf/0.1/>

foaf:Person a rdfs:Class .
        ';

        $graph = new Graph;
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);
        $prefixes = $this->extractor->extract($parsedRdf);

        expect($prefixes)->toHaveKey('rdf');
        expect($prefixes)->toHaveKey('rdfs');
        expect($prefixes)->toHaveKey('foaf');
    });

    it('extracts prefixes from RDF/XML xmlns declarations', function () {
        $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
         xmlns:foaf="http://xmlns.com/foaf/0.1/"
         xmlns:dc="http://purl.org/dc/elements/1.1/">
    <rdfs:Class rdf:about="http://example.org/Person"/>
</rdf:RDF>';

        $graph = new Graph;
        // Note: EasyRdf might fail on PHP 8.4+, but SimpleXML will work
        // Suppress all errors including exceptions from EasyRdf
        try {
            @$graph->parse($content, 'rdfxml');
        } catch (\Throwable $e) {
            // Ignore EasyRdf errors - we're testing SimpleXML extraction
        }

        $xml = simplexml_load_string($content);
        $metadata = ['format' => 'rdf/xml', 'xml_element' => $xml];

        $parsedRdf = new ParsedRdf($graph, 'rdf/xml', $content, $metadata);
        $prefixes = $this->extractor->extract($parsedRdf);

        expect($prefixes)->toHaveKey('rdf');
        expect($prefixes['rdf'])->toBe('http://www.w3.org/1999/02/22-rdf-syntax-ns#');
        expect($prefixes)->toHaveKey('rdfs');
        expect($prefixes['rdfs'])->toBe('http://www.w3.org/2000/01/rdf-schema#');
        expect($prefixes)->toHaveKey('foaf');
        expect($prefixes['foaf'])->toBe('http://xmlns.com/foaf/0.1/');
        expect($prefixes)->toHaveKey('dc');
        expect($prefixes['dc'])->toBe('http://purl.org/dc/elements/1.1/');
    });

    it('extracts prefixes from JSON-LD @context', function () {
        $content = '{
            "@context": {
                "rdf": "http://www.w3.org/1999/02/22-rdf-syntax-ns#",
                "rdfs": "http://www.w3.org/2000/01/rdf-schema#",
                "foaf": "http://xmlns.com/foaf/0.1/",
                "dc": "http://purl.org/dc/elements/1.1/"
            },
            "@id": "http://example.org/Person",
            "@type": "rdfs:Class"
        }';

        $graph = new Graph;
        $graph->parse($content, 'jsonld');

        $parsedRdf = new ParsedRdf($graph, 'json-ld', $content, ['format' => 'json-ld']);
        $prefixes = $this->extractor->extract($parsedRdf);

        expect($prefixes)->toHaveKey('rdf');
        expect($prefixes['rdf'])->toBe('http://www.w3.org/1999/02/22-rdf-syntax-ns#');
        expect($prefixes)->toHaveKey('rdfs');
        expect($prefixes['rdfs'])->toBe('http://www.w3.org/2000/01/rdf-schema#');
        expect($prefixes)->toHaveKey('foaf');
        expect($prefixes['foaf'])->toBe('http://xmlns.com/foaf/0.1/');
        expect($prefixes)->toHaveKey('dc');
        expect($prefixes['dc'])->toBe('http://purl.org/dc/elements/1.1/');
    });

    it('adds common RDF prefixes when used in graph', function () {
        $content = '
@prefix ex: <http://example.org/> .

ex:Person a <http://www.w3.org/2000/01/rdf-schema#Class> .
ex:Person <http://www.w3.org/2000/01/rdf-schema#label> "Person" .
        ';

        $graph = new Graph;
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);
        $prefixes = $this->extractor->extract($parsedRdf);

        // Should auto-add rdfs prefix since it's used in the graph
        expect($prefixes)->toHaveKey('rdfs');
        expect($prefixes['rdfs'])->toBe('http://www.w3.org/2000/01/rdf-schema#');
    });

    it('does not add common prefixes if already present', function () {
        $content = '
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/Person> a rdfs:Class .
        ';

        $graph = new Graph;
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);
        $prefixes = $this->extractor->extract($parsedRdf);

        // Should have rdfs from explicit @prefix, not duplicated from common prefixes
        expect($prefixes)->toHaveKey('rdfs');
        expect(array_keys(array_filter($prefixes, fn ($v, $k) => $k === 'rdfs', ARRAY_FILTER_USE_BOTH)))->toHaveCount(1);
    });

    it('does not add common prefixes if not used in graph', function () {
        $content = '
@prefix ex: <http://example.org/> .

ex:Person ex:hasName "John" .
        ';

        $graph = new Graph;
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);
        $prefixes = $this->extractor->extract($parsedRdf);

        // Should NOT add owl prefix since it's not used
        expect($prefixes)->not->toHaveKey('owl');
        // Should NOT add foaf prefix since it's not used
        expect($prefixes)->not->toHaveKey('foaf');
    });

    it('extracts prefixes from EasyRdf graph namespace map', function () {
        $content = '
@prefix custom: <http://custom.example.org/> .

custom:Thing a custom:Class .
        ';

        $graph = new Graph;
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);
        $prefixes = $this->extractor->extract($parsedRdf);

        // Should extract custom prefix from graph
        expect($prefixes)->toHaveKey('custom');
        expect($prefixes['custom'])->toBe('http://custom.example.org/');
    });

    it('handles empty content gracefully', function () {
        $content = '@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .';
        $graph = new Graph;
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);
        $prefixes = $this->extractor->extract($parsedRdf);

        expect($prefixes)->toBeArray();
        expect($prefixes)->toHaveKey('rdf');
    });

    it('handles unknown format gracefully', function () {
        $content = 'unknown format content';
        $graph = new Graph;

        $parsedRdf = new ParsedRdf($graph, 'unknown', $content, ['format' => 'unknown']);
        $prefixes = $this->extractor->extract($parsedRdf);

        expect($prefixes)->toBeArray();
        // Should only have prefixes from graph or common prefixes
    });

    it('deduplicates prefixes from multiple sources', function () {
        $content = '
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/Person> a <http://www.w3.org/1999/02/22-rdf-syntax-ns#Property> .
        ';

        $graph = new Graph;
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);
        $prefixes = $this->extractor->extract($parsedRdf);

        // rdf prefix appears in: @prefix declaration, common prefixes, and used in graph
        // Should only appear once
        expect($prefixes)->toHaveKey('rdf');
        $rdfCount = count(array_filter(array_keys($prefixes), fn ($k) => $k === 'rdf'));
        expect($rdfCount)->toBe(1);
    });

    it('extracts prefixes with mixed case correctly', function () {
        $content = '
@PREFIX RDF: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix RDFS: <http://www.w3.org/2000/01/rdf-schema#> .

RDF:type a RDFS:Class .
        ';

        $graph = new Graph;
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);
        $prefixes = $this->extractor->extract($parsedRdf);

        // Case-insensitive PREFIX should work
        expect($prefixes)->toHaveKey('RDF');
        expect($prefixes)->toHaveKey('RDFS');
    });

    it('handles multiple namespace URIs for same prefix', function () {
        $content = '
@prefix dc: <http://purl.org/dc/elements/1.1/> .

dc:title a <http://www.w3.org/1999/02/22-rdf-syntax-ns#Property> .
        ';

        $graph = new Graph;
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);
        $prefixes = $this->extractor->extract($parsedRdf);

        // dc can be either dc/elements/1.1 or dc/terms in common prefixes
        expect($prefixes)->toHaveKey('dc');
        expect($prefixes['dc'])->toBeString();
    });

    it('extracts all common RDF prefixes when they exist', function () {
        $commonPrefixes = [
            'rdf', 'rdfs', 'owl', 'xsd', 'dc', 'dcterms', 'dct', 'foaf', 'skos', 'sh', 'schema',
        ];

        $content = '
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
@prefix dc: <http://purl.org/dc/elements/1.1/> .
@prefix dcterms: <http://purl.org/dc/terms/> .
@prefix foaf: <http://xmlns.com/foaf/0.1/> .
@prefix skos: <http://www.w3.org/2004/02/skos/core#> .
@prefix sh: <http://www.w3.org/ns/shacl#> .

rdf:type a rdfs:Class .
        ';

        $graph = new Graph;
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);
        $prefixes = $this->extractor->extract($parsedRdf);

        // Should have most common prefixes
        expect($prefixes)->toHaveKey('rdf');
        expect($prefixes)->toHaveKey('rdfs');
        expect($prefixes)->toHaveKey('owl');
        expect($prefixes)->toHaveKey('xsd');
    });
});
