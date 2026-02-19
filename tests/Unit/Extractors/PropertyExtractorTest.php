<?php

declare(strict_types=1);

use EasyRdf\Graph;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserRdf\Extractors\PropertyExtractor;

describe('PropertyExtractor (new namespace)', function () {

    beforeEach(function () {
        $this->extractor = new PropertyExtractor();
    });

    describe('extract() with Graph path -- property type detection', function () {

        it('extracts property with rdf:Property type', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

                <http://example.org/name> a rdf:Property ;
                    rdfs:label "name"@en .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result)->toHaveCount(1);
            expect($result[0]['uri'])->toBe('http://example.org/name');
        });

        it('extracts owl:DatatypeProperty with property_type datatype', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix owl: <http://www.w3.org/2002/07/owl#> .

                <http://example.org/age> a owl:DatatypeProperty .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['property_type'])->toBe('datatype');
        });

        it('extracts owl:ObjectProperty with property_type object', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix owl: <http://www.w3.org/2002/07/owl#> .

                <http://example.org/knows> a owl:ObjectProperty .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['property_type'])->toBe('object');
        });

        it('extracts owl:AnnotationProperty with property_type annotation', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix owl: <http://www.w3.org/2002/07/owl#> .

                <http://example.org/note> a owl:AnnotationProperty .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['property_type'])->toBe('annotation');
        });

        it('default property_type is datatype when type is rdf:Property only', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .

                <http://example.org/title> a rdf:Property .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['property_type'])->toBe('datatype');
        });
    });

    describe('extract() with Graph path -- functional property detection', function () {

        it('is_functional is true when resource has owl:FunctionalProperty type', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix owl: <http://www.w3.org/2002/07/owl#> .

                <http://example.org/ssn> a owl:DatatypeProperty, owl:FunctionalProperty .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['is_functional'])->toBeTrue();
        });

        it('is_functional is false for non-functional properties', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix owl: <http://www.w3.org/2002/07/owl#> .

                <http://example.org/name> a owl:DatatypeProperty .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['is_functional'])->toBeFalse();
        });
    });

    describe('extract() with Graph path -- domain/range extraction', function () {

        it('domain is array of URI strings from rdfs:domain', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

                <http://example.org/name> a rdf:Property ;
                    rdfs:domain <http://example.org/Person> .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['domain'])->toContain('http://example.org/Person');
        });

        it('range is array of URI strings from rdfs:range', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
                @prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

                <http://example.org/name> a rdf:Property ;
                    rdfs:range xsd:string .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['range'])->toContain('http://www.w3.org/2001/XMLSchema#string');
        });

        it('property without domain/range returns empty arrays', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .

                <http://example.org/custom> a rdf:Property .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['domain'])->toBe([]);
            expect($result[0]['range'])->toBe([]);
        });
    });

    describe('extract() with Graph path -- range-from-comments fallback', function () {

        it('range-from-comment plain literal -> rdf:langString', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

                <http://example.org/label> a rdf:Property ;
                    rdfs:comment "The range of this property is a plain literal" .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['range'])->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#langString');
        });

        it('range-from-comment rdfs:Literal -> xsd:string', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

                <http://example.org/desc> a rdf:Property ;
                    rdfs:comment "The range is a rdfs:Literal" .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['range'])->toContain('http://www.w3.org/2001/XMLSchema#string');
        });

        it('range-from-comment xsd:string -> xsd:string', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

                <http://example.org/title> a rdf:Property ;
                    rdfs:comment "The range of this is xsd:string" .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['range'])->toContain('http://www.w3.org/2001/XMLSchema#string');
        });

        it('range-from-comment dateTime -> xsd:dateTime', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

                <http://example.org/created> a rdf:Property ;
                    rdfs:comment "The range of this property is dateTime" .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['range'])->toContain('http://www.w3.org/2001/XMLSchema#dateTime');
        });

        it('range-from-comment boolean -> xsd:boolean', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

                <http://example.org/active> a rdf:Property ;
                    rdfs:comment "The range of this is a boolean" .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['range'])->toContain('http://www.w3.org/2001/XMLSchema#boolean');
        });

        it('range-from-comment integer -> xsd:integer', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

                <http://example.org/age> a rdf:Property ;
                    rdfs:comment "The range of this property is integer" .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['range'])->toContain('http://www.w3.org/2001/XMLSchema#integer');
        });

        it('range-from-comment only used when rdfs:range is empty', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
                @prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

                <http://example.org/name> a rdf:Property ;
                    rdfs:range xsd:string ;
                    rdfs:comment "The range of this is a plain literal" .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            // Formal range exists -> comment fallback NOT used
            expect($result[0]['range'])->toContain('http://www.w3.org/2001/XMLSchema#string');
            expect($result[0]['range'])->not->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#langString');
        });
    });

    describe('extract() with Graph path -- parent properties and inverse', function () {

        it('parent_properties from rdfs:subPropertyOf is array of URI strings', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

                <http://example.org/firstName> a rdf:Property ;
                    rdfs:subPropertyOf <http://example.org/name> .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['parent_properties'])->toContain('http://example.org/name');
        });

        it('inverse_of from owl:inverseOf is array of URI strings', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix owl: <http://www.w3.org/2002/07/owl#> .

                <http://example.org/hasParent> a owl:ObjectProperty ;
                    owl:inverseOf <http://example.org/hasChild> .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['inverse_of'])->toContain('http://example.org/hasChild');
        });
    });

    describe('extract() with Graph path -- labels and output structure', function () {

        it('multilingual labels from rdfs:label', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

                <http://example.org/name> a rdf:Property ;
                    rdfs:label "name"@en ;
                    rdfs:label "naam"@nl .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['labels']['en'])->toBe('name');
            expect($result[0]['labels']['nl'])->toBe('naam');
        });

        it('label is best-match (English fallback)', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

                <http://example.org/name> a rdf:Property ;
                    rdfs:label "name"@en ;
                    rdfs:label "naam"@nl .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['label'])->toBe('name');
        });

        it('output has all required keys', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .

                <http://example.org/prop> a rdf:Property .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0])->toHaveKeys([
                'uri', 'label', 'labels', 'description', 'descriptions',
                'property_type', 'domain', 'range', 'parent_properties',
                'inverse_of', 'is_functional', 'metadata',
            ]);
        });

        it('metadata source is easyrdf for Graph path', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .

                <http://example.org/prop> a rdf:Property .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['metadata']['source'])->toBe('easyrdf');
        });

        it('metadata types is array of type URI strings', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix owl: <http://www.w3.org/2002/07/owl#> .

                <http://example.org/knows> a owl:ObjectProperty .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['metadata']['types'])->toContain('http://www.w3.org/2002/07/owl#ObjectProperty');
        });

        it('blank nodes are skipped', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .

                <http://example.org/name> a rdf:Property .
                _:blank a rdf:Property .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result)->toHaveCount(1);
            expect($result[0]['uri'])->toBe('http://example.org/name');
        });

        it('all URIs are full URIs, never prefixed', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
                @prefix ex: <http://example.org/> .

                ex:name a rdf:Property ;
                    rdfs:domain ex:Person ;
                    rdfs:range <http://www.w3.org/2001/XMLSchema#string> .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['uri'])->toBe('http://example.org/name');
            expect($result[0]['domain'][0])->toBe('http://example.org/Person');
        });

        it('handles empty graph -- returns empty array', function () {
            $graph = new Graph();
            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: '', metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result)->toBe([]);
        });

        it('all optional array fields default to empty arrays', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .

                <http://example.org/custom> a rdf:Property .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['domain'])->toBe([]);
            expect($result[0]['range'])->toBe([]);
            expect($result[0]['parent_properties'])->toBe([]);
            expect($result[0]['inverse_of'])->toBe([]);
        });
    });

    describe('extract() with XML fallback path', function () {

        it('extracts properties from RDF/XML when xml_element is set', function () {
            $xmlContent = '<?xml version="1.0"?>
                <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
                         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#">
                    <rdf:Property rdf:about="http://example.org/name">
                        <rdfs:label>name</rdfs:label>
                        <rdfs:domain rdf:resource="http://example.org/Person"/>
                        <rdfs:range rdf:resource="http://www.w3.org/2001/XMLSchema#string"/>
                    </rdf:Property>
                </rdf:RDF>';

            $xml = simplexml_load_string($xmlContent);
            $graph = new Graph();

            $parsedRdf = new ParsedRdf(
                graph: $graph,
                format: 'rdf/xml',
                rawContent: $xmlContent,
                metadata: ['xml_element' => $xml],
            );
            $result = $this->extractor->extract($parsedRdf);

            expect($result)->toHaveCount(1);
            expect($result[0]['uri'])->toBe('http://example.org/name');
            expect($result[0]['domain'])->toContain('http://example.org/Person');
        });

        it('XML path metadata source is fallback_rdf_xml', function () {
            $xmlContent = '<?xml version="1.0"?>
                <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
                    <rdf:Property rdf:about="http://example.org/name"/>
                </rdf:RDF>';

            $xml = simplexml_load_string($xmlContent);
            $graph = new Graph();

            $parsedRdf = new ParsedRdf(
                graph: $graph,
                format: 'rdf/xml',
                rawContent: $xmlContent,
                metadata: ['xml_element' => $xml],
            );
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['metadata']['source'])->toBe('fallback_rdf_xml');
        });

        it('XML path metadata element_name contains XML element name', function () {
            $xmlContent = '<?xml version="1.0"?>
                <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
                         xmlns:owl="http://www.w3.org/2002/07/owl#">
                    <owl:ObjectProperty rdf:about="http://example.org/knows"/>
                </rdf:RDF>';

            $xml = simplexml_load_string($xmlContent);
            $graph = new Graph();

            $parsedRdf = new ParsedRdf(
                graph: $graph,
                format: 'rdf/xml',
                rawContent: $xmlContent,
                metadata: ['xml_element' => $xml],
            );
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['metadata']['element_name'])->toBe('ObjectProperty');
        });

        it('XML path determines property type from element name', function () {
            $xmlContent = '<?xml version="1.0"?>
                <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
                         xmlns:owl="http://www.w3.org/2002/07/owl#">
                    <owl:ObjectProperty rdf:about="http://example.org/knows"/>
                </rdf:RDF>';

            $xml = simplexml_load_string($xmlContent);
            $graph = new Graph();

            $parsedRdf = new ParsedRdf(
                graph: $graph,
                format: 'rdf/xml',
                rawContent: $xmlContent,
                metadata: ['xml_element' => $xml],
            );
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['property_type'])->toBe('object');
        });

        it('XML path detects functional property from element name', function () {
            $xmlContent = '<?xml version="1.0"?>
                <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
                         xmlns:owl="http://www.w3.org/2002/07/owl#">
                    <owl:FunctionalProperty rdf:about="http://example.org/ssn"/>
                </rdf:RDF>';

            $xml = simplexml_load_string($xmlContent);
            $graph = new Graph();

            $parsedRdf = new ParsedRdf(
                graph: $graph,
                format: 'rdf/xml',
                rawContent: $xmlContent,
                metadata: ['xml_element' => $xml],
            );
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['is_functional'])->toBeTrue();
        });

        it('XML path extracts range from rdfs:comment text', function () {
            $xmlContent = '<?xml version="1.0"?>
                <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
                         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#">
                    <rdf:Property rdf:about="http://example.org/label">
                        <rdfs:comment>The range of this is a plain literal</rdfs:comment>
                    </rdf:Property>
                </rdf:RDF>';

            $xml = simplexml_load_string($xmlContent);
            $graph = new Graph();

            $parsedRdf = new ParsedRdf(
                graph: $graph,
                format: 'rdf/xml',
                rawContent: $xmlContent,
                metadata: ['xml_element' => $xml],
            );
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['range'])->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#langString');
        });

        it('XML path has all required keys', function () {
            $xmlContent = '<?xml version="1.0"?>
                <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
                    <rdf:Property rdf:about="http://example.org/prop"/>
                </rdf:RDF>';

            $xml = simplexml_load_string($xmlContent);
            $graph = new Graph();

            $parsedRdf = new ParsedRdf(
                graph: $graph,
                format: 'rdf/xml',
                rawContent: $xmlContent,
                metadata: ['xml_element' => $xml],
            );
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0])->toHaveKeys([
                'uri', 'label', 'labels', 'description', 'descriptions',
                'property_type', 'domain', 'range', 'parent_properties',
                'inverse_of', 'is_functional', 'metadata',
            ]);
        });
    });

    it('is a final class', function () {
        $reflection = new ReflectionClass(PropertyExtractor::class);
        expect($reflection->isFinal())->toBeTrue();
    });

    it('integrates with RdfParser -- properties populated in ParsedOntology', function () {
        $parser = new \Youri\vandenBogert\Software\ParserRdf\RdfParser();
        $content = <<<'TTL'
            @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
            @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
            @prefix owl: <http://www.w3.org/2002/07/owl#> .

            <http://example.org/knows> a owl:ObjectProperty ;
                rdfs:label "knows"@en ;
                rdfs:domain <http://example.org/Person> ;
                rdfs:range <http://example.org/Person> .
            TTL;

        $result = $parser->parse($content);

        expect($result->properties)->toBeArray();
        expect($result->properties)->toHaveKey('http://example.org/knows');
        expect($result->properties['http://example.org/knows']['property_type'])->toBe('object');
    });
});
