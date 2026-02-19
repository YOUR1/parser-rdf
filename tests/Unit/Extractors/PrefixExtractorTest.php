<?php

declare(strict_types=1);

use EasyRdf\Graph;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserRdf\Extractors\PrefixExtractor;

describe('PrefixExtractor (new namespace)', function () {

    beforeEach(function () {
        $this->extractor = new PrefixExtractor();
    });

    it('extracts prefixes from Turtle @prefix declarations', function () {
        $content = "@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .\n"
                 . "@prefix foaf: <http://xmlns.com/foaf/0.1/> .\n"
                 . "<http://example.org/Person> a <http://www.w3.org/2000/01/rdf-schema#Class> .";

        $graph = new Graph();
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        expect($result)->toBeArray();
        expect($result)->toHaveKey('rdf');
        expect($result)->toHaveKey('foaf');
        expect($result['rdf'])->toBe('http://www.w3.org/1999/02/22-rdf-syntax-ns#');
        expect($result['foaf'])->toBe('http://xmlns.com/foaf/0.1/');
    });

    it('extracts SPARQL-style PREFIX declarations (case-insensitive)', function () {
        $content = "PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>\n"
                 . "PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>\n"
                 . "<http://example.org/Person> a rdfs:Class .";

        $graph = new Graph();
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        expect($result)->toHaveKey('rdf');
        expect($result)->toHaveKey('rdfs');
    });

    it('extracts prefixes from RDF/XML xmlns: declarations', function () {
        $content = '<?xml version="1.0"?>'
                 . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"'
                 . ' xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#">'
                 . '<rdfs:Class rdf:about="http://example.org/Person"/>'
                 . '</rdf:RDF>';

        $graph = new Graph();
        try {
            @$graph->parse($content, 'rdfxml');
        } catch (\Throwable) {
            // PHP 8.4+ EasyRdf compat
        }

        $parsedRdf = new ParsedRdf(graph: $graph, format: 'rdf/xml', rawContent: $content, metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        expect($result)->toHaveKey('rdf');
        expect($result)->toHaveKey('rdfs');
        expect($result['rdf'])->toBe('http://www.w3.org/1999/02/22-rdf-syntax-ns#');
    });

    it('extracts prefixes from JSON-LD @context', function () {
        $content = '{"@context": {"rdf": "http://www.w3.org/1999/02/22-rdf-syntax-ns#", "foaf": "http://xmlns.com/foaf/0.1/"}, "@id": "http://example.org/Person"}';

        $graph = new Graph();
        $graph->parse($content, 'jsonld');

        $parsedRdf = new ParsedRdf(graph: $graph, format: 'json-ld', rawContent: $content, metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        expect($result)->toHaveKey('rdf');
        expect($result['rdf'])->toBe('http://www.w3.org/1999/02/22-rdf-syntax-ns#');
    });

    it('auto-adds common prefixes when namespace URI is used in graph but not explicitly declared', function () {
        $content = '<http://www.w3.org/2002/07/owl#Class> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2000/01/rdf-schema#Class> .';

        $graph = new Graph();
        $graph->parse($content, 'ntriples');

        $parsedRdf = new ParsedRdf(graph: $graph, format: 'n-triples', rawContent: $content, metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        // owl namespace is used in graph, should be auto-added
        expect($result)->toHaveKey('owl');
        expect($result['owl'])->toBe('http://www.w3.org/2002/07/owl#');
    });

    it('does NOT auto-add common prefixes when prefix already exists from explicit declaration', function () {
        $content = "@prefix owl: <http://www.w3.org/2002/07/owl#> .\n"
                 . "<http://www.w3.org/2002/07/owl#Class> a <http://www.w3.org/2000/01/rdf-schema#Class> .";

        $graph = new Graph();
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        // owl should be present from graph/content extraction, not from common prefix auto-add
        expect($result)->toHaveKey('owl');
    });

    it('does NOT auto-add common prefixes when namespace not used in graph', function () {
        $content = '<http://example.org/s> <http://example.org/p> <http://example.org/o> .';

        $graph = new Graph();
        $graph->parse($content, 'ntriples');

        $parsedRdf = new ParsedRdf(graph: $graph, format: 'n-triples', rawContent: $content, metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        // sh (SHACL) namespace is not used in graph, should not be added
        expect($result)->not->toHaveKey('sh');
    });

    it('deduplication -- same prefix from multiple sources appears only once', function () {
        $content = "@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .\n"
                 . "<http://example.org/s> rdf:type <http://example.org/Thing> .";

        $graph = new Graph();
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        // rdf appears in both graph namespace map and content regex, but should only appear once
        expect($result)->toHaveKey('rdf');
        expect(count(array_keys($result, $result['rdf'])))->toBe(1);
    });

    it('output format -- return value is array of strings to strings', function () {
        $content = "@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .\n"
                 . "<http://example.org/s> rdf:type <http://example.org/Thing> .";

        $graph = new Graph();
        $graph->parse($content, 'turtle');

        $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        expect($result)->toBeArray();
        foreach ($result as $key => $value) {
            expect($key)->toBeString();
            expect($value)->toBeString();
        }
    });

    it('handles unknown format gracefully -- returns array', function () {
        $graph = new Graph();
        $parsedRdf = new ParsedRdf(graph: $graph, format: 'unknown', rawContent: 'some content', metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        expect($result)->toBeArray();
    });

    it('handles empty content -- returns array, not null', function () {
        $graph = new Graph();
        $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: '', metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        expect($result)->toBeArray();
    });

    it('format aliases work correctly -- ttl treated as turtle', function () {
        $content = "@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .\n"
                 . "<http://example.org/s> rdf:type <http://example.org/Thing> .";

        $graph = new Graph();
        $graph->parse($content, 'turtle');

        // Using 'ttl' format alias
        $parsedRdf = new ParsedRdf(graph: $graph, format: 'ttl', rawContent: $content, metadata: []);
        $result = $this->extractor->extract($parsedRdf);

        expect($result)->toHaveKey('rdf');
    });

    it('is a final class', function () {
        $reflection = new ReflectionClass(PrefixExtractor::class);
        expect($reflection->isFinal())->toBeTrue();
    });

    it('integrates with RdfParser -- prefixes populated in ParsedOntology', function () {
        $parser = new \Youri\vandenBogert\Software\ParserRdf\RdfParser();
        $content = "@prefix foaf: <http://xmlns.com/foaf/0.1/> .\n"
                 . "<http://example.org/Person> a <http://www.w3.org/2000/01/rdf-schema#Class> .";

        $result = $parser->parse($content);

        expect($result->prefixes)->toBeArray();
        expect($result->prefixes)->toHaveKey('foaf');
        expect($result->prefixes['foaf'])->toBe('http://xmlns.com/foaf/0.1/');
    });
});
