<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserRdf\Extractors\PropertyExtractor;
use EasyRdf\Graph;

describe('PropertyExtractor', function () {

    beforeEach(function () {
        $this->extractor = new PropertyExtractor();
    });

    describe('extract() with Graph path', function () {

        it('extracts properties with rdf:Property type from Turtle content', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/name> a rdf:Property ;
    rdfs:label "name" .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $properties = $this->extractor->extract($parsedRdf);
            $uris = array_column($properties, 'uri');
            expect($uris)->toContain('http://example.org/name');
        });

        it('extracts properties with owl:DatatypeProperty type from Turtle content', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .

<http://example.org/age> a owl:DatatypeProperty ;
    rdfs:label "age" .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $properties = $this->extractor->extract($parsedRdf);
            $uris = array_column($properties, 'uri');
            expect($uris)->toContain('http://example.org/age');
        });

        it('extracts properties with owl:ObjectProperty type from Turtle content', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .

<http://example.org/knows> a owl:ObjectProperty ;
    rdfs:label "knows" .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $properties = $this->extractor->extract($parsedRdf);
            $uris = array_column($properties, 'uri');
            expect($uris)->toContain('http://example.org/knows');
        });

        it('extracts properties with owl:AnnotationProperty type from Turtle content', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .

<http://example.org/note> a owl:AnnotationProperty ;
    rdfs:label "note" .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $properties = $this->extractor->extract($parsedRdf);
            $uris = array_column($properties, 'uri');
            expect($uris)->toContain('http://example.org/note');
        });

        it('property output has required keys', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/name> a rdf:Property ;
    rdfs:label "name"@en ;
    rdfs:comment "The name"@en .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $properties = $this->extractor->extract($parsedRdf);
            $property = $properties[0];
            expect($property)->toHaveKeys([
                'uri', 'label', 'labels', 'description', 'descriptions',
                'property_type', 'domain', 'range', 'parent_properties',
                'inverse_of', 'is_functional', 'metadata',
            ]);
        });

        it('property_type = datatype for owl:DatatypeProperty (and default)', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .

<http://example.org/age> a owl:DatatypeProperty .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $properties = $this->extractor->extract($parsedRdf);
            $age = null;
            foreach ($properties as $p) {
                if ($p['uri'] === 'http://example.org/age') {
                    $age = $p;
                }
            }
            expect($age)->not->toBeNull();
            expect($age['property_type'])->toBe('datatype');
        });

        it('property_type = object for owl:ObjectProperty', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .

<http://example.org/knows> a owl:ObjectProperty .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $properties = $this->extractor->extract($parsedRdf);
            $knows = null;
            foreach ($properties as $p) {
                if ($p['uri'] === 'http://example.org/knows') {
                    $knows = $p;
                }
            }
            expect($knows)->not->toBeNull();
            expect($knows['property_type'])->toBe('object');
        });

        it('property_type = annotation for owl:AnnotationProperty', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .

<http://example.org/note> a owl:AnnotationProperty .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $properties = $this->extractor->extract($parsedRdf);
            $note = null;
            foreach ($properties as $p) {
                if ($p['uri'] === 'http://example.org/note') {
                    $note = $p;
                }
            }
            expect($note)->not->toBeNull();
            expect($note['property_type'])->toBe('annotation');
        });

        it('is_functional = true for owl:FunctionalProperty', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .

<http://example.org/id> a owl:DatatypeProperty, owl:FunctionalProperty .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $properties = $this->extractor->extract($parsedRdf);
            $id = null;
            foreach ($properties as $p) {
                if ($p['uri'] === 'http://example.org/id') {
                    $id = $p;
                }
            }
            expect($id)->not->toBeNull();
            expect($id['is_functional'])->toBeTrue();
        });

        it('is_functional = false for non-functional properties', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .

<http://example.org/name> a owl:DatatypeProperty .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $properties = $this->extractor->extract($parsedRdf);
            $name = null;
            foreach ($properties as $p) {
                if ($p['uri'] === 'http://example.org/name') {
                    $name = $p;
                }
            }
            expect($name)->not->toBeNull();
            expect($name['is_functional'])->toBeFalse();
        });

        it('property domain is array of URI strings from rdfs:domain', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/name> a rdf:Property ;
    rdfs:domain <http://example.org/Person> .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $properties = $this->extractor->extract($parsedRdf);
            expect($properties[0]['domain'])->toBeArray();
            expect($properties[0]['domain'])->toContain('http://example.org/Person');
        });

        it('property range is array of URI strings from rdfs:range', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

<http://example.org/name> a rdf:Property ;
    rdfs:range xsd:string .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $properties = $this->extractor->extract($parsedRdf);
            expect($properties[0]['range'])->toBeArray();
            expect($properties[0]['range'])->toContain('http://www.w3.org/2001/XMLSchema#string');
        });

        it('property with multiple domains', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/name> a rdf:Property ;
    rdfs:domain <http://example.org/Person> ;
    rdfs:domain <http://example.org/Organization> .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $properties = $this->extractor->extract($parsedRdf);
            expect($properties[0]['domain'])->toContain('http://example.org/Person');
            expect($properties[0]['domain'])->toContain('http://example.org/Organization');
        });

        it('property with multiple ranges', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

<http://example.org/value> a rdf:Property ;
    rdfs:range xsd:string ;
    rdfs:range xsd:integer .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $properties = $this->extractor->extract($parsedRdf);
            expect($properties[0]['range'])->toContain('http://www.w3.org/2001/XMLSchema#string');
            expect($properties[0]['range'])->toContain('http://www.w3.org/2001/XMLSchema#integer');
        });

        it('property without domain/range returns empty arrays', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/thing> a rdf:Property .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $properties = $this->extractor->extract($parsedRdf);
            expect($properties[0]['domain'])->toBeArray()->toBeEmpty();
            expect($properties[0]['range'])->toBeArray()->toBeEmpty();
        });

        it('property parent_properties from rdfs:subPropertyOf (array of URI strings)', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/fullName> a rdf:Property ;
    rdfs:subPropertyOf <http://example.org/name> .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $properties = $this->extractor->extract($parsedRdf);
            $fullName = null;
            foreach ($properties as $p) {
                if ($p['uri'] === 'http://example.org/fullName') {
                    $fullName = $p;
                }
            }
            expect($fullName)->not->toBeNull();
            expect($fullName['parent_properties'])->toContain('http://example.org/name');
        });

        it('property inverse_of from owl:inverseOf (array of URI strings)', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .

<http://example.org/isKnownBy> a owl:ObjectProperty ;
    owl:inverseOf <http://example.org/knows> .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $properties = $this->extractor->extract($parsedRdf);
            $isKnownBy = null;
            foreach ($properties as $p) {
                if ($p['uri'] === 'http://example.org/isKnownBy') {
                    $isKnownBy = $p;
                }
            }
            expect($isKnownBy)->not->toBeNull();
            expect($isKnownBy['inverse_of'])->toContain('http://example.org/knows');
        });

        it('complex domain with union class (owl:unionOf) -- union members extracted as individual URIs', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .

<http://example.org/name> a rdf:Property ;
    rdfs:domain [
        a owl:Class ;
        owl:unionOf (<http://example.org/Person> <http://example.org/Organization>)
    ] .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $properties = $this->extractor->extract($parsedRdf);
            $name = null;
            foreach ($properties as $p) {
                if ($p['uri'] === 'http://example.org/name') {
                    $name = $p;
                }
            }
            expect($name)->not->toBeNull();
            expect($name['domain'])->toContain('http://example.org/Person');
            expect($name['domain'])->toContain('http://example.org/Organization');
        });

        it('range-from-comment fallback: when rdfs:range is empty, attempts regex extraction from rdfs:comment text', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/title> a rdf:Property ;
    rdfs:comment "The range of this property is a plain literal." .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $properties = $this->extractor->extract($parsedRdf);
            $title = null;
            foreach ($properties as $p) {
                if ($p['uri'] === 'http://example.org/title') {
                    $title = $p;
                }
            }
            expect($title)->not->toBeNull();
            expect($title['range'])->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#langString');
        });

        it('range-from-comment patterns: range...Literal produces xsd:string', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/desc> a rdf:Property ;
    rdfs:comment "The range is a Literal value." .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $properties = $this->extractor->extract($parsedRdf);
            $desc = null;
            foreach ($properties as $p) {
                if ($p['uri'] === 'http://example.org/desc') {
                    $desc = $p;
                }
            }
            expect($desc)->not->toBeNull();
            // "range...is...literal" matches -> xsd:string
            expect($desc['range'])->toContain('http://www.w3.org/2001/XMLSchema#string');
        });

        it('range-from-comment patterns: range...dateTime produces xsd:dateTime', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/created> a rdf:Property ;
    rdfs:comment "The range of this property is a dateTime value." .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $properties = $this->extractor->extract($parsedRdf);
            $created = null;
            foreach ($properties as $p) {
                if ($p['uri'] === 'http://example.org/created') {
                    $created = $p;
                }
            }
            expect($created)->not->toBeNull();
            expect($created['range'])->toContain('http://www.w3.org/2001/XMLSchema#dateTime');
        });

        it('range-from-comment patterns: range...boolean produces xsd:boolean', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/active> a rdf:Property ;
    rdfs:comment "The range of this property is a boolean value." .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $properties = $this->extractor->extract($parsedRdf);
            $active = null;
            foreach ($properties as $p) {
                if ($p['uri'] === 'http://example.org/active') {
                    $active = $p;
                }
            }
            expect($active)->not->toBeNull();
            expect($active['range'])->toContain('http://www.w3.org/2001/XMLSchema#boolean');
        });

        it('range-from-comment patterns: range...integer produces xsd:integer', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/count> a rdf:Property ;
    rdfs:comment "The range of this property is an integer value." .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $properties = $this->extractor->extract($parsedRdf);
            $count = null;
            foreach ($properties as $p) {
                if ($p['uri'] === 'http://example.org/count') {
                    $count = $p;
                }
            }
            expect($count)->not->toBeNull();
            expect($count['range'])->toContain('http://www.w3.org/2001/XMLSchema#integer');
        });

        it('range-from-comment patterns: range...xsd:string produces xsd:string', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/label> a rdf:Property ;
    rdfs:comment "The range of this property is xsd:string." .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $properties = $this->extractor->extract($parsedRdf);
            $label = null;
            foreach ($properties as $p) {
                if ($p['uri'] === 'http://example.org/label') {
                    $label = $p;
                }
            }
            expect($label)->not->toBeNull();
            expect($label['range'])->toContain('http://www.w3.org/2001/XMLSchema#string');
        });

        it('property metadata[source] = easyrdf for Graph extraction path', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/name> a rdf:Property .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $properties = $this->extractor->extract($parsedRdf);
            expect($properties[0]['metadata']['source'])->toBe('easyrdf');
        });

        it('blank nodes and anonymous OWL expressions are skipped', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .

<http://example.org/name> a rdf:Property .

_:anon a owl:Restriction ;
    owl:onProperty <http://example.org/something> .
TTL;
            $graph = new Graph();
            $graph->parse($content, 'turtle');
            $parsedRdf = new ParsedRdf($graph, 'turtle', $content, ['format' => 'turtle']);

            $properties = $this->extractor->extract($parsedRdf);
            foreach ($properties as $property) {
                expect($property['uri'])->not->toStartWith('_:');
            }
        });
    });

    describe('extract() with XML path', function () {

        it('extracts properties from RDF/XML when format is rdf/xml and xml_element is set', function () {
            $xmlContent = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
         xmlns:owl="http://www.w3.org/2002/07/owl#">
    <rdf:Property rdf:about="http://example.org/name">
        <rdfs:label>name</rdfs:label>
        <rdfs:domain rdf:resource="http://example.org/Person"/>
        <rdfs:range rdf:resource="http://www.w3.org/2001/XMLSchema#string"/>
    </rdf:Property>
</rdf:RDF>';
            $xml = simplexml_load_string($xmlContent);
            $graph = new Graph();
            $parsedRdf = new ParsedRdf($graph, 'rdf/xml', $xmlContent, [
                'format' => 'rdf/xml',
                'xml_element' => $xml,
            ]);

            $properties = $this->extractor->extract($parsedRdf);
            expect($properties)->not->toBeEmpty();
            expect($properties[0]['uri'])->toBe('http://example.org/name');
        });

        it('property metadata[source] = fallback_rdf_xml for XML extraction path', function () {
            $xmlContent = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
         xmlns:owl="http://www.w3.org/2002/07/owl#">
    <rdf:Property rdf:about="http://example.org/name">
        <rdfs:label>name</rdfs:label>
    </rdf:Property>
</rdf:RDF>';
            $xml = simplexml_load_string($xmlContent);
            $graph = new Graph();
            $parsedRdf = new ParsedRdf($graph, 'rdf/xml', $xmlContent, [
                'format' => 'rdf/xml',
                'xml_element' => $xml,
            ]);

            $properties = $this->extractor->extract($parsedRdf);
            expect($properties[0]['metadata']['source'])->toBe('fallback_rdf_xml');
        });

        it('property metadata[element_name] contains XML element name', function () {
            $xmlContent = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
         xmlns:owl="http://www.w3.org/2002/07/owl#">
    <rdf:Property rdf:about="http://example.org/name">
        <rdfs:label>name</rdfs:label>
    </rdf:Property>
</rdf:RDF>';
            $xml = simplexml_load_string($xmlContent);
            $graph = new Graph();
            $parsedRdf = new ParsedRdf($graph, 'rdf/xml', $xmlContent, [
                'format' => 'rdf/xml',
                'xml_element' => $xml,
            ]);

            $properties = $this->extractor->extract($parsedRdf);
            expect($properties[0]['metadata'])->toHaveKey('element_name');
            expect($properties[0]['metadata']['element_name'])->toBe('Property');
        });

        it('properties found by rdf:type attribute (Dublin Core pattern)', function () {
            $xmlContent = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
         xmlns:owl="http://www.w3.org/2002/07/owl#">
    <rdf:Description rdf:about="http://example.org/title">
        <rdf:type rdf:resource="http://www.w3.org/1999/02/22-rdf-syntax-ns#Property"/>
        <rdfs:label>title</rdfs:label>
    </rdf:Description>
</rdf:RDF>';
            $xml = simplexml_load_string($xmlContent);
            $graph = new Graph();
            $parsedRdf = new ParsedRdf($graph, 'rdf/xml', $xmlContent, [
                'format' => 'rdf/xml',
                'xml_element' => $xml,
            ]);

            $properties = $this->extractor->extract($parsedRdf);
            $uris = array_column($properties, 'uri');
            expect($uris)->toContain('http://example.org/title');
        });

        it('determinePropertyTypeFromXml() checks element name first, then rdf:type child elements', function () {
            $xmlContent = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
         xmlns:owl="http://www.w3.org/2002/07/owl#">
    <owl:ObjectProperty rdf:about="http://example.org/knows">
        <rdfs:label>knows</rdfs:label>
    </owl:ObjectProperty>
</rdf:RDF>';
            $xml = simplexml_load_string($xmlContent);
            $graph = new Graph();
            $parsedRdf = new ParsedRdf($graph, 'rdf/xml', $xmlContent, [
                'format' => 'rdf/xml',
                'xml_element' => $xml,
            ]);

            $properties = $this->extractor->extract($parsedRdf);
            $knows = null;
            foreach ($properties as $p) {
                if ($p['uri'] === 'http://example.org/knows') {
                    $knows = $p;
                }
            }
            expect($knows)->not->toBeNull();
            expect($knows['property_type'])->toBe('object');
        });

        it('isXmlPropertyFunctional() detects functional properties from element name', function () {
            $xmlContent = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
         xmlns:owl="http://www.w3.org/2002/07/owl#">
    <owl:FunctionalProperty rdf:about="http://example.org/id">
        <rdfs:label>id</rdfs:label>
    </owl:FunctionalProperty>
</rdf:RDF>';
            $xml = simplexml_load_string($xmlContent);
            $graph = new Graph();
            $parsedRdf = new ParsedRdf($graph, 'rdf/xml', $xmlContent, [
                'format' => 'rdf/xml',
                'xml_element' => $xml,
            ]);

            $properties = $this->extractor->extract($parsedRdf);
            $id = null;
            foreach ($properties as $p) {
                if ($p['uri'] === 'http://example.org/id') {
                    $id = $p;
                }
            }
            expect($id)->not->toBeNull();
            expect($id['is_functional'])->toBeTrue();
        });

        it('extractRangeFromXmlComments works for XML path', function () {
            $xmlContent = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
         xmlns:owl="http://www.w3.org/2002/07/owl#">
    <rdf:Property rdf:about="http://example.org/desc">
        <rdfs:label>desc</rdfs:label>
        <rdfs:comment>The range of this is a plain literal value.</rdfs:comment>
    </rdf:Property>
</rdf:RDF>';
            $xml = simplexml_load_string($xmlContent);
            $graph = new Graph();
            $parsedRdf = new ParsedRdf($graph, 'rdf/xml', $xmlContent, [
                'format' => 'rdf/xml',
                'xml_element' => $xml,
            ]);

            $properties = $this->extractor->extract($parsedRdf);
            $desc = null;
            foreach ($properties as $p) {
                if ($p['uri'] === 'http://example.org/desc') {
                    $desc = $p;
                }
            }
            expect($desc)->not->toBeNull();
            expect($desc['range'])->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#langString');
        });
    });
});
