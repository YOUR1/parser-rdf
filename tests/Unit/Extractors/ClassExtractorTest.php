<?php

declare(strict_types=1);

use EasyRdf\Graph;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserRdf\Extractors\ClassExtractor;

describe('ClassExtractor (new namespace)', function () {

    beforeEach(function () {
        $this->extractor = new ClassExtractor();
    });

    describe('extract() with Graph path', function () {

        it('extracts classes with rdfs:Class type from Turtle content', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

                <http://example.org/Person> a rdfs:Class ;
                    rdfs:label "Person"@en .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result)->toBeArray();
            expect($result)->toHaveCount(1);
            expect($result[0]['uri'])->toBe('http://example.org/Person');
        });

        it('extracts classes with owl:Class type from Turtle content', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix owl: <http://www.w3.org/2002/07/owl#> .

                <http://example.org/Animal> a owl:Class .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result)->toHaveCount(1);
            expect($result[0]['uri'])->toBe('http://example.org/Animal');
        });

        it('class output has all required keys', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

                <http://example.org/Person> a rdfs:Class .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0])->toHaveKeys(['uri', 'label', 'labels', 'description', 'descriptions', 'parent_classes', 'metadata']);
        });

        it('uri is always a full URI, never prefixed', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
                @prefix ex: <http://example.org/> .

                ex:Person a rdfs:Class .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['uri'])->toBe('http://example.org/Person');
            expect($result[0]['uri'])->not->toContain('ex:');
        });

        it('labels is a multilingual array keyed by language tag', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

                <http://example.org/Person> a rdfs:Class ;
                    rdfs:label "Person"@en ;
                    rdfs:label "Persoon"@nl .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['labels'])->toBeArray();
            expect($result[0]['labels'])->toHaveKey('en');
            expect($result[0]['labels'])->toHaveKey('nl');
            expect($result[0]['labels']['en'])->toBe('Person');
            expect($result[0]['labels']['nl'])->toBe('Persoon');
        });

        it('label is best-match string (English preferred)', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

                <http://example.org/Person> a rdfs:Class ;
                    rdfs:label "Person"@en ;
                    rdfs:label "Persoon"@nl .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['label'])->toBe('Person');
        });

        it('descriptions is a multilingual array keyed by language tag', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

                <http://example.org/Person> a rdfs:Class ;
                    rdfs:comment "A human being"@en ;
                    rdfs:comment "Een mens"@nl .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['descriptions'])->toBeArray();
            expect($result[0]['descriptions'])->toHaveKey('en');
            expect($result[0]['descriptions'])->toHaveKey('nl');
            expect($result[0]['descriptions']['en'])->toBe('A human being');
        });

        it('description is best-match string or null', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

                <http://example.org/Person> a rdfs:Class ;
                    rdfs:comment "A human being"@en .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['description'])->toBe('A human being');
        });

        it('parent_classes from rdfs:subClassOf returns array of full URI strings', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
                @prefix owl: <http://www.w3.org/2002/07/owl#> .

                <http://example.org/Person> a owl:Class .
                <http://example.org/Student> a owl:Class ;
                    rdfs:subClassOf <http://example.org/Person> .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            $student = null;
            foreach ($result as $class) {
                if ($class['uri'] === 'http://example.org/Student') {
                    $student = $class;
                    break;
                }
            }
            expect($student)->not->toBeNull();
            expect($student['parent_classes'])->toContain('http://example.org/Person');
        });

        it('subclass hierarchy -- Student subClassOf Person', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

                <http://example.org/LivingBeing> a rdfs:Class .
                <http://example.org/Person> a rdfs:Class ;
                    rdfs:subClassOf <http://example.org/LivingBeing> .
                <http://example.org/Student> a rdfs:Class ;
                    rdfs:subClassOf <http://example.org/Person> .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result)->toHaveCount(3);

            $byUri = array_column($result, null, 'uri');

            expect($byUri['http://example.org/Person']['parent_classes'])->toContain('http://example.org/LivingBeing');
            expect($byUri['http://example.org/Student']['parent_classes'])->toContain('http://example.org/Person');
        });

        it('blank nodes are skipped', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

                <http://example.org/Person> a rdfs:Class .
                _:blank1 a rdfs:Class .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result)->toHaveCount(1);
            expect($result[0]['uri'])->toBe('http://example.org/Person');

            foreach ($result as $class) {
                expect($class['uri'])->not->toStartWith('_:');
            }
        });

        it('anonymous OWL expressions are skipped', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
                @prefix owl: <http://www.w3.org/2002/07/owl#> .

                <http://example.org/Person> a owl:Class .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            foreach ($result as $class) {
                expect($class['uri'])->not->toStartWith('_:');
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

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['label'])->toBeNull();
            expect($result[0]['labels'])->toBe([]);
        });

        it('class without descriptions returns null for description and empty array for descriptions', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

                <http://example.org/Thing> a rdfs:Class .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['description'])->toBeNull();
            expect($result[0]['descriptions'])->toBe([]);
        });

        it('class without parent classes returns empty array', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

                <http://example.org/Thing> a rdfs:Class .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['parent_classes'])->toBe([]);
        });

        it('extracts multiple classes from same content', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

                <http://example.org/Person> a rdfs:Class ;
                    rdfs:label "Person"@en .
                <http://example.org/Organization> a rdfs:Class ;
                    rdfs:label "Organization"@en .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result)->toHaveCount(2);
            $uris = array_column($result, 'uri');
            expect($uris)->toContain('http://example.org/Person');
            expect($uris)->toContain('http://example.org/Organization');
        });

        it('metadata source is easyrdf for Graph extraction path', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

                <http://example.org/Person> a rdfs:Class .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['metadata']['source'])->toBe('easyrdf');
        });

        it('metadata types contains type URIs', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

                <http://example.org/Person> a rdfs:Class .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['metadata']['types'])->toBeArray();
            expect($result[0]['metadata']['types'])->toContain('http://www.w3.org/2000/01/rdf-schema#Class');
        });

        it('metadata annotations contains custom annotation properties', function () {
            $content = <<<'TTL'
                @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
                @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
                @prefix skos: <http://www.w3.org/2004/02/skos/core#> .

                <http://example.org/Person> a rdfs:Class ;
                    skos:prefLabel "Person"@en .
                TTL;

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: $content, metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result[0]['metadata']['annotations'])->toBeArray();
            expect($result[0]['metadata']['annotations'])->not->toBeEmpty();

            $properties = array_column($result[0]['metadata']['annotations'], 'property');
            expect($properties)->toContain('skos:prefLabel');
        });

        it('handles empty graph -- returns empty array', function () {
            $graph = new Graph();
            $parsedRdf = new ParsedRdf(graph: $graph, format: 'turtle', rawContent: '', metadata: []);
            $result = $this->extractor->extract($parsedRdf);

            expect($result)->toBe([]);
        });
    });

    describe('extract() with XML fallback path', function () {

        it('extracts classes from RDF/XML using SimpleXML', function () {
            $xmlContent = '<?xml version="1.0"?>
                <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
                         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#">
                    <rdfs:Class rdf:about="http://example.org/Person">
                        <rdfs:label>Person</rdfs:label>
                    </rdfs:Class>
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
            expect($result[0]['uri'])->toBe('http://example.org/Person');
        });

        it('XML path metadata source is fallback_rdf_xml', function () {
            $xmlContent = '<?xml version="1.0"?>
                <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
                         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#">
                    <rdfs:Class rdf:about="http://example.org/Person"/>
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

        it('XML path metadata element_name contains the element name', function () {
            $xmlContent = '<?xml version="1.0"?>
                <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
                         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#">
                    <rdfs:Class rdf:about="http://example.org/Person"/>
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

            expect($result[0]['metadata']['element_name'])->toBe('Class');
        });

        it('XML path extracts multilingual labels', function () {
            $xmlContent = '<?xml version="1.0"?>
                <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
                         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
                         xmlns:xml="http://www.w3.org/XML/1998/namespace">
                    <rdfs:Class rdf:about="http://example.org/Person">
                        <rdfs:label xml:lang="en">Person</rdfs:label>
                        <rdfs:label xml:lang="nl">Persoon</rdfs:label>
                    </rdfs:Class>
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

            expect($result[0]['labels'])->toHaveKey('en');
            expect($result[0]['labels'])->toHaveKey('nl');
            expect($result[0]['labels']['en'])->toBe('Person');
            expect($result[0]['labels']['nl'])->toBe('Persoon');
        });

        it('XML path extracts parent classes from rdfs:subClassOf rdf:resource', function () {
            $xmlContent = '<?xml version="1.0"?>
                <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
                         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#">
                    <rdfs:Class rdf:about="http://example.org/Student">
                        <rdfs:subClassOf rdf:resource="http://example.org/Person"/>
                    </rdfs:Class>
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

            expect($result[0]['parent_classes'])->toContain('http://example.org/Person');
        });

        it('XML path extracts owl:Class elements', function () {
            $xmlContent = '<?xml version="1.0"?>
                <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
                         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
                         xmlns:owl="http://www.w3.org/2002/07/owl#">
                    <owl:Class rdf:about="http://example.org/Animal">
                        <rdfs:label>Animal</rdfs:label>
                    </owl:Class>
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
            expect($result[0]['uri'])->toBe('http://example.org/Animal');
        });

        it('XML path has all required keys', function () {
            $xmlContent = '<?xml version="1.0"?>
                <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
                         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#">
                    <rdfs:Class rdf:about="http://example.org/Thing"/>
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

            expect($result[0])->toHaveKeys(['uri', 'label', 'labels', 'description', 'descriptions', 'parent_classes', 'metadata']);
        });
    });

    it('is a final class', function () {
        $reflection = new ReflectionClass(ClassExtractor::class);
        expect($reflection->isFinal())->toBeTrue();
    });

    it('integrates with RdfParser -- classes populated in ParsedOntology', function () {
        $parser = new \Youri\vandenBogert\Software\ParserRdf\RdfParser();
        $content = <<<'TTL'
            @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
            @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

            <http://example.org/Person> a rdfs:Class ;
                rdfs:label "Person"@en .
            TTL;

        $result = $parser->parse($content);

        expect($result->classes)->toBeArray();
        expect($result->classes)->toHaveKey('http://example.org/Person');
        expect($result->classes['http://example.org/Person']['label'])->toBe('Person');
    });
});
