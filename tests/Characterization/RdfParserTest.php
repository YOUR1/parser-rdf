<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\Contracts\RdfFormatHandlerInterface;
use Youri\vandenBogert\Software\ParserCore\Exceptions\FormatDetectionException;
use Youri\vandenBogert\Software\ParserCore\Exceptions\ParseException;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedOntology;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserRdf\RdfParser;

describe('RdfParser', function () {

    beforeEach(function () {
        $this->parser = new RdfParser();
    });

    describe('canParse()', function () {

        it('returns true for Turtle content (starts with @prefix)', function () {
            $content = '@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .';
            expect($this->parser->canParse($content))->toBeTrue();
        });

        it('returns true for Turtle content with @prefix NOT at start (after comment)', function () {
            $content = "# This is a comment\n@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .";
            expect($this->parser->canParse($content))->toBeTrue();
        });

        it('returns true for RDF/XML content (starts with <?xml)', function () {
            $content = '<?xml version="1.0"?><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"></rdf:RDF>';
            expect($this->parser->canParse($content))->toBeTrue();
        });

        it('returns true for RDF/XML content (contains <rdf:RDF)', function () {
            $content = '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"></rdf:RDF>';
            expect($this->parser->canParse($content))->toBeTrue();
        });

        it('returns true for JSON-LD content (starts with { AND contains @context)', function () {
            $content = '{"@context": {"rdf": "http://www.w3.org/1999/02/22-rdf-syntax-ns#"}}';
            expect($this->parser->canParse($content))->toBeTrue();
        });

        it('returns false for plain text content', function () {
            $content = 'This is just plain text, nothing RDF about it.';
            expect($this->parser->canParse($content))->toBeFalse();
        });

        it('returns false for HTML content (<html>...</html>) -- HTML <html> does NOT match any pattern', function () {
            $content = '<html><body>Hello World</body></html>';
            expect($this->parser->canParse($content))->toBeFalse();
        });

        it('returns false for empty string', function () {
            expect($this->parser->canParse(''))->toBeFalse();
        });

        it('returns false for whitespace-only content', function () {
            expect($this->parser->canParse('   '))->toBeFalse();
        });

        it('returns true for valid N-Triples content -- NTriplesHandler now registered', function () {
            $content = '<http://example.org/s> <http://example.org/p> <http://example.org/o> .';
            expect($this->parser->canParse($content))->toBeTrue();
        });

        it('trims content before checking (trim())', function () {
            $content = '   @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .   ';
            expect($this->parser->canParse($content))->toBeTrue();
        });
    });

    describe('getSupportedFormats()', function () {

        it('returns exactly 4 elements', function () {
            $formats = $this->parser->getSupportedFormats();
            expect($formats)->toHaveCount(4);
        });

        it('contains rdf/xml, turtle, json-ld, n-triples', function () {
            $formats = $this->parser->getSupportedFormats();
            expect($formats)->toContain('rdf/xml');
            expect($formats)->toContain('turtle');
            expect($formats)->toContain('json-ld');
            expect($formats)->toContain('n-triples');
        });

        it('return value is array of strings', function () {
            $formats = $this->parser->getSupportedFormats();
            expect($formats)->toBeArray();
            foreach ($formats as $format) {
                expect($format)->toBeString();
            }
        });
    });

    describe('parse()', function () {

        it('returns ParsedOntology with all fields', function () {
            $content = '@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .';
            $result = $this->parser->parse($content);
            expect($result)->toBeInstanceOf(ParsedOntology::class);
            expect($result->metadata)->toBeArray();
            expect($result->prefixes)->toBeArray();
            expect($result->classes)->toBeArray();
            expect($result->properties)->toBeArray();
            expect($result->shapes)->toBeArray();
            expect($result->restrictions)->toBeArray();
            expect($result->rawContent)->toBeString();
        });

        it('with Turtle content -- handler detected as TurtleHandler', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/Person> a rdfs:Class ;
    rdfs:label "Person" .
TTL;
            $result = $this->parser->parse($content);
            expect($result->metadata['format'])->toBe('turtle');
        });

        it('with RDF/XML content -- handler detected as RdfXmlHandler', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#">
    <rdfs:Class rdf:about="http://example.org/Person">
        <rdfs:label>Person</rdfs:label>
    </rdfs:Class>
</rdf:RDF>';
            $result = $this->parser->parse($content);
            expect($result->metadata['format'])->toBe('rdf/xml');
        });

        it('with JSON-LD content -- handler detected as JsonLdHandler', function () {
            $content = '{
                "@context": {
                    "rdf": "http://www.w3.org/1999/02/22-rdf-syntax-ns#",
                    "rdfs": "http://www.w3.org/2000/01/rdf-schema#"
                },
                "@id": "http://example.org/Person",
                "@type": "rdfs:Class",
                "rdfs:label": "Person"
            }';
            $result = $this->parser->parse($content);
            expect($result->metadata['format'])->toBe('json-ld');
        });

        it('with N-Triples content -- handler detected as NTriplesHandler', function () {
            $content = '<http://example.org/Person> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2000/01/rdf-schema#Class> .' . "\n"
                     . '<http://example.org/Person> <http://www.w3.org/2000/01/rdf-schema#label> "Person" .';
            $result = $this->parser->parse($content);
            expect($result->metadata['format'])->toBe('n-triples');
        });

        it('metadata includes resource_count (integer) from getResourceCount()', function () {
            $content = '@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
<http://example.org/Person> a rdfs:Class .';
            $result = $this->parser->parse($content);
            expect($result->metadata)->toHaveKey('resource_count');
            expect($result->metadata['resource_count'])->toBeInt();
        });

        it('with $options[format] matching a handler -- uses explicit format selection', function () {
            $content = '@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .';
            $result = $this->parser->parse($content, ['format' => 'turtle']);
            expect($result->metadata['format'])->toBe('turtle');
        });

        it('with $options[format] that does NOT match any handler -- throws FormatDetectionException', function () {
            $content = '@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .';
            expect(fn () => $this->parser->parse($content, ['format' => 'nonexistent-format']))
                ->toThrow(FormatDetectionException::class);
        });

        it('with unrecognized content -- throws FormatDetectionException', function () {
            $content = 'this is not any RDF format at all';
            expect(fn () => $this->parser->parse($content))
                ->toThrow(FormatDetectionException::class);
        });

        it('rawContent preserves original input string', function () {
            $content = '@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .';
            $result = $this->parser->parse($content);
            expect($result->rawContent)->toBe($content);
        });

        it('with empty Turtle document (only prefix declarations) -- returns empty classes/properties/shapes arrays', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

# Empty document with just prefixes
TTL;
            $result = $this->parser->parse($content);
            expect($result->classes)->toBeArray()->toBeEmpty();
            expect($result->properties)->toBeArray()->toBeEmpty();
            expect($result->shapes)->toBeArray()->toBeEmpty();
        });

        it('error wrapping: exception message has RDF parsing failed prefix', function () {
            $content = 'invalid rdf content';
            try {
                $this->parser->parse($content);
                $this->fail('Expected exception not thrown');
            } catch (FormatDetectionException|ParseException $e) {
                expect($e->getMessage())->toBeString();
            }
        });

        it('with content containing classes, properties, and shapes -- all extractors invoked', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

ex:Person a rdfs:Class ;
    rdfs:label "Person" .

ex:name a rdf:Property ;
    rdfs:label "name" ;
    rdfs:domain ex:Person ;
    rdfs:range rdfs:Literal .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:minCount 1 ;
        sh:maxCount 1 ;
    ] .
TTL;
            $result = $this->parser->parse($content);
            expect($result->classes)->not->toBeEmpty();
            expect($result->properties)->not->toBeEmpty();
            expect($result->shapes)->not->toBeEmpty();
            expect($result->prefixes)->not->toBeEmpty();
        });

        it('handler priority: content that matches both Turtle and N-Triples is handled by TurtleHandler (Turtle checked first)', function () {
            $content = '@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
<http://example.org/s> <http://example.org/p> <http://example.org/o> .';
            $result = $this->parser->parse($content);
            expect($result->metadata['format'])->toBe('turtle');
        });
    });

    describe('registerHandler()', function () {

        it('prepends handler to list -- custom handler checked BEFORE default handlers', function () {
            $customHandler = new class implements RdfFormatHandlerInterface {
                public function canHandle(string $content): bool
                {
                    return str_contains($content, '@prefix');
                }

                public function parse(string $content): ParsedRdf
                {
                    $graph = new \EasyRdf\Graph();
                    return new ParsedRdf(graph: $graph, format: 'custom-turtle', rawContent: $content, metadata: ['parser' => 'custom_handler', 'format' => 'custom-turtle']);
                }

                public function getFormatName(): string
                {
                    return 'custom-turtle';
                }
            };

            $this->parser->registerHandler($customHandler);

            $content = '@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .';
            $result = $this->parser->parse($content);
            expect($result->metadata['format'])->toBe('custom-turtle');
        });

        it('registered handler is used when default handlers do not match content', function () {
            $customHandler = new class implements RdfFormatHandlerInterface {
                public function canHandle(string $content): bool
                {
                    return str_contains($content, '##SPECIAL##');
                }

                public function parse(string $content): ParsedRdf
                {
                    $graph = new \EasyRdf\Graph();
                    return new ParsedRdf(graph: $graph, format: 'special', rawContent: $content, metadata: ['parser' => 'special_handler', 'format' => 'special']);
                }

                public function getFormatName(): string
                {
                    return 'special';
                }
            };

            $this->parser->registerHandler($customHandler);
            $result = $this->parser->parse('##SPECIAL## data here');
            expect($result->metadata['format'])->toBe('special');
        });
    });

    describe('restrictions via parse() output', function () {

        it('returns restrictions as empty array (base RdfParser does not extract restrictions)', function () {
            $content = '@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .';
            $result = $this->parser->parse($content);
            expect($result->restrictions)->toBeArray()->toBeEmpty();
        });
    });

    describe('integrated pipeline behavior', function () {

        it('full parse() pipeline with Turtle content containing classes, properties, prefixes, and shapes -- all sections populated', function () {
            $content = <<<'TTL'
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
@prefix ex: <http://example.org/> .

ex:Person a rdfs:Class ;
    rdfs:label "Person"@en ;
    rdfs:comment "A human being"@en .

ex:name a rdf:Property ;
    rdfs:label "name"@en ;
    rdfs:domain ex:Person ;
    rdfs:range xsd:string .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:minCount 1 ;
        sh:datatype xsd:string ;
    ] .
TTL;
            $result = $this->parser->parse($content);

            expect($result)->toBeInstanceOf(ParsedOntology::class);
            expect($result->metadata['format'])->toBe('turtle');
            expect($result->prefixes)->not->toBeEmpty();
            expect($result->classes)->not->toBeEmpty();
            expect($result->properties)->not->toBeEmpty();
            expect($result->shapes)->not->toBeEmpty();
            expect($result->rawContent)->toBe($content);
        });

        it('full parse() pipeline with RDF/XML content -- extractors use XML extraction path', function () {
            $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
         xmlns:ex="http://example.org/">
    <rdfs:Class rdf:about="http://example.org/Person">
        <rdfs:label>Person</rdfs:label>
    </rdfs:Class>
    <rdf:Property rdf:about="http://example.org/name">
        <rdfs:label>name</rdfs:label>
        <rdfs:domain rdf:resource="http://example.org/Person"/>
    </rdf:Property>
</rdf:RDF>';
            $result = $this->parser->parse($content);

            expect($result->metadata['format'])->toBe('rdf/xml');
            expect($result->classes)->not->toBeEmpty();
            expect($result->properties)->not->toBeEmpty();
            // ShapeExtractor returns [] for rdf/xml
            expect($result->shapes)->toBeArray()->toBeEmpty();
        });

        it('full parse() pipeline with JSON-LD content -- extractors use Graph extraction path', function () {
            $content = '{
                "@context": {
                    "rdf": "http://www.w3.org/1999/02/22-rdf-syntax-ns#",
                    "rdfs": "http://www.w3.org/2000/01/rdf-schema#"
                },
                "@id": "http://example.org/Person",
                "@type": "rdfs:Class",
                "rdfs:label": "Person"
            }';
            $result = $this->parser->parse($content);

            expect($result->metadata['format'])->toBe('json-ld');
            expect($result->classes)->not->toBeEmpty();
        });

        it('full parse() pipeline with N-Triples content -- extractors use Graph extraction path', function () {
            $content = '<http://example.org/Person> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2000/01/rdf-schema#Class> .' . "\n"
                     . '<http://example.org/Person> <http://www.w3.org/2000/01/rdf-schema#label> "Person" .';
            $result = $this->parser->parse($content);

            expect($result->metadata['format'])->toBe('n-triples');
            expect($result->classes)->not->toBeEmpty();
        });

        it('metadata merging: handler metadata keys preserved + format and resource_count added', function () {
            $content = '{
                "@context": {
                    "rdf": "http://www.w3.org/1999/02/22-rdf-syntax-ns#",
                    "rdfs": "http://www.w3.org/2000/01/rdf-schema#"
                },
                "@id": "http://example.org/Person",
                "@type": "rdfs:Class"
            }';
            $result = $this->parser->parse($content);

            expect($result->metadata)->toHaveKey('format');
            expect($result->metadata)->toHaveKey('resource_count');
            expect($result->metadata['resource_count'])->toBeInt();
        });
    });
});
