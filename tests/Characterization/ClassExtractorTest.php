<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserRdf\Extractors\ClassExtractor;
use EasyRdf\Graph;

describe('ClassExtractor', function () {

    beforeEach(function () {
        $this->extractor = new ClassExtractor();
    });

    describe('extract() with Graph path', function () {

        it('extracts classes with rdfs:Class type from Turtle content', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/Person> a rdfs:Class ;
    rdfs:label "Person" .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $classes = $this->extractor->extract($parsedRdf);
            expect($classes)->not->toBeEmpty();

            $uris = array_column($classes, 'uri');
            expect($uris)->toContain('http://example.org/Person');
        });

        it('extracts classes with owl:Class type from Turtle content', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .

<http://example.org/Person> a owl:Class ;
    rdfs:label "Person" .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $classes = $this->extractor->extract($parsedRdf);
            $uris = array_column($classes, 'uri');
            expect($uris)->toContain('http://example.org/Person');
        });

        it('class output has required keys: uri, label, labels, description, descriptions, parent_classes, metadata', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/Person> a rdfs:Class ;
    rdfs:label "Person"@en ;
    rdfs:comment "A human being"@en .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $classes = $this->extractor->extract($parsedRdf);
            $class = $classes[0];

            expect($class)->toHaveKeys(['uri', 'label', 'labels', 'description', 'descriptions', 'parent_classes', 'metadata']);
        });

        it('class uri is full URI (never prefixed)', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix ex: <http://example.org/> .

ex:Person a rdfs:Class .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $classes = $this->extractor->extract($parsedRdf);
            $class = $classes[0];
            expect($class['uri'])->toBe('http://example.org/Person');
            expect($class['uri'])->toStartWith('http://');
        });

        it('class label is string (best-match: preferred lang -> en -> first available) or null', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/Person> a rdfs:Class ;
    rdfs:label "Person"@en ;
    rdfs:label "Persoon"@nl .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $classes = $this->extractor->extract($parsedRdf);
            $class = $classes[0];
            // Label should be "Person" (English preferred)
            expect($class['label'])->toBe('Person');
            expect($class['label'])->toBeString();
        });

        it('class labels is array keyed by language tag', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/Person> a rdfs:Class ;
    rdfs:label "Person"@en ;
    rdfs:label "Persoon"@nl .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $classes = $this->extractor->extract($parsedRdf);
            $class = $classes[0];
            expect($class['labels'])->toBeArray();
            expect($class['labels'])->toHaveKey('en');
            expect($class['labels'])->toHaveKey('nl');
            expect($class['labels']['en'])->toBe('Person');
            expect($class['labels']['nl'])->toBe('Persoon');
        });

        it('class description from rdfs:comment (single string or null)', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/Person> a rdfs:Class ;
    rdfs:comment "A human being"@en .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $classes = $this->extractor->extract($parsedRdf);
            $class = $classes[0];
            expect($class['description'])->toBe('A human being');
        });

        it('class descriptions from rdfs:comment (multilingual array)', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/Person> a rdfs:Class ;
    rdfs:comment "A human being"@en ;
    rdfs:comment "Een mens"@nl .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $classes = $this->extractor->extract($parsedRdf);
            $class = $classes[0];
            expect($class['descriptions'])->toBeArray();
            expect($class['descriptions'])->toHaveKey('en');
            expect($class['descriptions'])->toHaveKey('nl');
        });

        it('class parent_classes from rdfs:subClassOf (array of URI strings)', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/Person> a rdfs:Class .
<http://example.org/Student> a rdfs:Class ;
    rdfs:subClassOf <http://example.org/Person> .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $classes = $this->extractor->extract($parsedRdf);
            $student = null;
            foreach ($classes as $class) {
                if ($class['uri'] === 'http://example.org/Student') {
                    $student = $class;
                    break;
                }
            }
            expect($student)->not->toBeNull();
            expect($student['parent_classes'])->toBeArray();
            expect($student['parent_classes'])->toContain('http://example.org/Person');
        });

        it('class metadata[source] = easyrdf for Graph extraction path', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/Person> a rdfs:Class .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $classes = $this->extractor->extract($parsedRdf);
            expect($classes[0]['metadata']['source'])->toBe('easyrdf');
        });

        it('class metadata[types] contains type URIs', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/Person> a rdfs:Class .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $classes = $this->extractor->extract($parsedRdf);
            expect($classes[0]['metadata']['types'])->toBeArray();
            expect($classes[0]['metadata']['types'])->toContain('http://www.w3.org/2000/01/rdf-schema#Class');
        });

        it('class metadata[annotations] contains custom annotation properties', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix skos: <http://www.w3.org/2004/02/skos/core#> .

<http://example.org/Person> a rdfs:Class ;
    skos:prefLabel "Person"@en .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $classes = $this->extractor->extract($parsedRdf);
            expect($classes[0]['metadata']['annotations'])->toBeArray();
            // skos:prefLabel is NOT a standard property, so it should appear as annotation
            expect($classes[0]['metadata']['annotations'])->not->toBeEmpty();
        });

        it('blank nodes are skipped (not included in class output)', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .

<http://example.org/Person> a rdfs:Class .

# A restriction is an anonymous class (blank node)
_:restriction a owl:Restriction ;
    owl:onProperty <http://example.org/prop> .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $classes = $this->extractor->extract($parsedRdf);
            foreach ($classes as $class) {
                expect($class['uri'])->not->toStartWith('_:');
            }
        });

        it('anonymous OWL expressions (owl:Restriction blank nodes) are skipped', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .

<http://example.org/Person> a owl:Class .

<http://example.org/Person> rdfs:subClassOf [
    a owl:Restriction ;
    owl:onProperty <http://example.org/name> ;
    owl:someValuesFrom <http://www.w3.org/2001/XMLSchema#string>
] .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $classes = $this->extractor->extract($parsedRdf);
            // Only named classes should be in output
            foreach ($classes as $class) {
                expect($class['uri'])->toStartWith('http://');
            }
        });

        it('class without labels returns null for label and empty array for labels', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/Thing> a rdfs:Class .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $classes = $this->extractor->extract($parsedRdf);
            $thing = null;
            foreach ($classes as $class) {
                if ($class['uri'] === 'http://example.org/Thing') {
                    $thing = $class;
                    break;
                }
            }
            expect($thing)->not->toBeNull();
            expect($thing['label'])->toBeNull();
            expect($thing['labels'])->toBeArray()->toBeEmpty();
        });

        it('extracts multiple classes from same content', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/Person> a rdfs:Class ;
    rdfs:label "Person" .

<http://example.org/Organization> a rdfs:Class ;
    rdfs:label "Organization" .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $classes = $this->extractor->extract($parsedRdf);
            $uris = array_column($classes, 'uri');
            expect($uris)->toContain('http://example.org/Person');
            expect($uris)->toContain('http://example.org/Organization');
        });
    });

    describe('extract() with XML path', function () {

        it('extracts classes from RDF/XML using SimpleXML xpath when format is rdf/xml and xml_element is set', function () {
            $xmlContent = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
         xmlns:owl="http://www.w3.org/2002/07/owl#">
    <rdfs:Class rdf:about="http://example.org/Person">
        <rdfs:label>Person</rdfs:label>
        <rdfs:comment>A human being</rdfs:comment>
    </rdfs:Class>
</rdf:RDF>';
            $xml = simplexml_load_string($xmlContent);
            $graph = new Graph();
            $parsedRdf = new ParsedRdf($graph, 'rdf/xml', $xmlContent, [
                'format' => 'rdf/xml',
                'xml_element' => $xml,
            ]);

            $classes = $this->extractor->extract($parsedRdf);
            expect($classes)->not->toBeEmpty();
            expect($classes[0]['uri'])->toBe('http://example.org/Person');
            expect($classes[0]['label'])->toBe('Person');
        });

        it('class metadata[source] = fallback_rdf_xml for XML extraction path', function () {
            $xmlContent = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
         xmlns:owl="http://www.w3.org/2002/07/owl#">
    <rdfs:Class rdf:about="http://example.org/Person">
        <rdfs:label>Person</rdfs:label>
    </rdfs:Class>
</rdf:RDF>';
            $xml = simplexml_load_string($xmlContent);
            $graph = new Graph();
            $parsedRdf = new ParsedRdf($graph, 'rdf/xml', $xmlContent, [
                'format' => 'rdf/xml',
                'xml_element' => $xml,
            ]);

            $classes = $this->extractor->extract($parsedRdf);
            expect($classes[0]['metadata']['source'])->toBe('fallback_rdf_xml');
        });

        it('class metadata[element_name] contains XML element name (e.g., Class)', function () {
            $xmlContent = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
         xmlns:owl="http://www.w3.org/2002/07/owl#">
    <rdfs:Class rdf:about="http://example.org/Person">
        <rdfs:label>Person</rdfs:label>
    </rdfs:Class>
</rdf:RDF>';
            $xml = simplexml_load_string($xmlContent);
            $graph = new Graph();
            $parsedRdf = new ParsedRdf($graph, 'rdf/xml', $xmlContent, [
                'format' => 'rdf/xml',
                'xml_element' => $xml,
            ]);

            $classes = $this->extractor->extract($parsedRdf);
            expect($classes[0]['metadata'])->toHaveKey('element_name');
            expect($classes[0]['metadata']['element_name'])->toBe('Class');
        });
    });
});
