<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\Contracts\OntologyParserInterface;
use Youri\vandenBogert\Software\ParserCore\Contracts\RdfFormatHandlerInterface;
use Youri\vandenBogert\Software\ParserCore\Exceptions\FormatDetectionException;
use Youri\vandenBogert\Software\ParserCore\Exceptions\ParseException;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedOntology;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserRdf\RdfParser;

describe('RdfParser (new namespace)', function () {

    beforeEach(function () {
        $this->parser = new RdfParser();
    });

    describe('class structure', function () {

        it('implements OntologyParserInterface from parser-core', function () {
            expect($this->parser)->toBeInstanceOf(OntologyParserInterface::class);
        });

        it('is not a final class', function () {
            $reflection = new ReflectionClass(RdfParser::class);
            expect($reflection->isFinal())->toBeFalse();
        });
    });

    describe('canParse()', function () {

        it('returns true for Turtle content (@prefix ...)', function () {
            $content = '@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .';
            expect($this->parser->canParse($content))->toBeTrue();
        });

        it('returns true for RDF/XML content (<?xml ...)', function () {
            $content = '<?xml version="1.0"?><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"></rdf:RDF>';
            expect($this->parser->canParse($content))->toBeTrue();
        });

        it('returns true for JSON-LD content ({"@context": ...})', function () {
            $content = '{"@context": {"rdf": "http://www.w3.org/1999/02/22-rdf-syntax-ns#"}, "@type": "Thing"}';
            expect($this->parser->canParse($content))->toBeTrue();
        });

        it('returns true for N-Triples content (<uri> <pred> <obj> .)', function () {
            $content = '<http://example.org/s> <http://example.org/p> <http://example.org/o> .';
            expect($this->parser->canParse($content))->toBeTrue();
        });

        it('returns false for plain text content', function () {
            expect($this->parser->canParse('This is just plain text.'))->toBeFalse();
        });

        it('returns false for empty string', function () {
            expect($this->parser->canParse(''))->toBeFalse();
        });

        it('MUST NOT throw exceptions', function () {
            // Even with bizarre content, canParse should return false, not throw
            expect($this->parser->canParse("\x00\x01\x02"))->toBeFalse();
            expect($this->parser->canParse(str_repeat('x', 100000)))->toBeFalse();
        });
    });

    describe('getSupportedFormats()', function () {

        it('returns array containing all 4 format names', function () {
            $formats = $this->parser->getSupportedFormats();
            expect($formats)->toContain('json-ld');
            expect($formats)->toContain('turtle');
            expect($formats)->toContain('n-triples');
            expect($formats)->toContain('rdf/xml');
        });

        it('does NOT contain rdfa (old anomaly removed)', function () {
            $formats = $this->parser->getSupportedFormats();
            expect($formats)->not->toContain('rdfa');
        });

        it('returns format names from registered handlers dynamically', function () {
            $mockHandler = new class implements RdfFormatHandlerInterface {
                public function canHandle(string $content): bool { return false; }
                public function parse(string $content): ParsedRdf {
                    return new ParsedRdf(graph: new \EasyRdf\Graph(), format: 'custom', rawContent: $content, metadata: []);
                }
                public function getFormatName(): string { return 'custom-format'; }
            };

            $this->parser->registerHandler($mockHandler);
            expect($this->parser->getSupportedFormats())->toContain('custom-format');
        });
    });

    describe('parse()', function () {

        it('with Turtle content auto-detects and returns ParsedOntology', function () {
            $content = "@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .\n"
                     . "<http://example.org/Person> rdf:type <http://www.w3.org/2000/01/rdf-schema#Class> .";
            $result = $this->parser->parse($content);
            expect($result)->toBeInstanceOf(ParsedOntology::class);
            expect($result->metadata['format'])->toBe('turtle');
        });

        it('with RDF/XML content auto-detects correctly', function () {
            $content = '<?xml version="1.0"?>'
                     . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"'
                     . ' xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#">'
                     . '<rdfs:Class rdf:about="http://example.org/Person">'
                     . '<rdfs:label>Person</rdfs:label>'
                     . '</rdfs:Class>'
                     . '</rdf:RDF>';
            $result = $this->parser->parse($content);
            expect($result)->toBeInstanceOf(ParsedOntology::class);
            expect($result->metadata['format'])->toBe('rdf/xml');
        });

        it('with JSON-LD content auto-detects correctly', function () {
            $content = '{"@context": {"rdf": "http://www.w3.org/1999/02/22-rdf-syntax-ns#"}, "@id": "http://example.org/s", "@type": "http://example.org/Thing"}';
            $result = $this->parser->parse($content);
            expect($result)->toBeInstanceOf(ParsedOntology::class);
            expect($result->metadata['format'])->toBe('json-ld');
        });

        it('with N-Triples content auto-detects correctly', function () {
            $content = '<http://example.org/s> <http://example.org/p> <http://example.org/o> .';
            $result = $this->parser->parse($content);
            expect($result)->toBeInstanceOf(ParsedOntology::class);
            expect($result->metadata['format'])->toBe('n-triples');
        });

        it('metadata includes format name and resource_count', function () {
            $content = '<http://example.org/s> <http://example.org/p> <http://example.org/o> .';
            $result = $this->parser->parse($content);
            expect($result->metadata)->toHaveKeys(['format', 'resource_count']);
            expect($result->metadata['format'])->toBeString();
            expect($result->metadata['resource_count'])->toBeInt();
        });

        it('rawContent preserves original input', function () {
            $content = '<http://example.org/s> <http://example.org/p> <http://example.org/o> .';
            $result = $this->parser->parse($content);
            expect($result->rawContent)->toBe($content);
        });

        it('with options[format] bypasses auto-detection', function () {
            $content = '<http://example.org/s> <http://example.org/p> <http://example.org/o> .';
            $result = $this->parser->parse($content, ['format' => 'n-triples']);
            expect($result->metadata['format'])->toBe('n-triples');
        });

        it('with options[format] for unknown format throws FormatDetectionException', function () {
            $content = '<http://example.org/s> <http://example.org/p> <http://example.org/o> .';
            expect(fn () => $this->parser->parse($content, ['format' => 'unknown']))
                ->toThrow(FormatDetectionException::class, 'No handler registered for format: unknown');
        });

        it('with empty content throws ParseException', function () {
            expect(fn () => $this->parser->parse(''))
                ->toThrow(ParseException::class, 'Cannot parse empty content');
        });

        it('with whitespace-only content throws ParseException', function () {
            expect(fn () => $this->parser->parse('   '))
                ->toThrow(ParseException::class, 'Cannot parse empty content');
        });

        it('with unrecognized content throws FormatDetectionException', function () {
            expect(fn () => $this->parser->parse('This is not RDF content at all.'))
                ->toThrow(FormatDetectionException::class, 'No handler could detect the format');
        });

        it('wraps handler failures in ParseException with $previous set', function () {
            // Use explicit format to force handler selection, then provide invalid content
            // RDF/XML handler will throw on invalid XML
            try {
                $this->parser->parse('<invalid xml that is not rdf>', ['format' => 'rdf/xml']);
                $this->fail('Expected exception not thrown');
            } catch (ParseException $e) {
                expect($e->getPrevious())->not->toBeNull();
            }
        });

        it('returns ParsedOntology instance (not array)', function () {
            $content = '<http://example.org/s> <http://example.org/p> <http://example.org/o> .';
            $result = $this->parser->parse($content);
            expect($result)->toBeInstanceOf(ParsedOntology::class);
        });

        it('returned ParsedOntology has all fields present', function () {
            $content = '<http://example.org/s> <http://example.org/p> <http://example.org/o> .';
            $result = $this->parser->parse($content);

            expect($result->classes)->toBeArray();
            expect($result->properties)->toBeArray();
            expect($result->prefixes)->toBeArray();
            expect($result->shapes)->toBeArray();
            expect($result->restrictions)->toBeArray();
            expect($result->metadata)->toBeArray();
            expect($result->rawContent)->toBeString();
        });

        it('returns empty extraction results for minimal N-Triples content', function () {
            $content = '<http://example.org/s> <http://example.org/p> <http://example.org/o> .';
            $result = $this->parser->parse($content);

            expect($result->classes)->toBeEmpty();
            expect($result->properties)->toBeEmpty();
            expect($result->prefixes)->toBeEmpty();
            expect($result->shapes)->toBeEmpty();
            expect($result->restrictions)->toBeEmpty();
        });
    });

    describe('handler priority', function () {

        it('Turtle wins over N-Triples for @prefix content', function () {
            $content = "@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .\n"
                     . "<http://example.org/s> rdf:type <http://example.org/Thing> .";
            $result = $this->parser->parse($content);
            expect($result->metadata['format'])->toBe('turtle');
        });

        it('JSON-LD wins first for {"@context": ...} content', function () {
            $content = '{"@context": {"rdf": "http://www.w3.org/1999/02/22-rdf-syntax-ns#"}, "@id": "http://example.org/s", "@type": "http://example.org/Thing"}';
            $result = $this->parser->parse($content);
            expect($result->metadata['format'])->toBe('json-ld');
        });
    });

    describe('registerHandler()', function () {

        it('prepends custom handler (checked first before defaults)', function () {
            $mockHandler = new class implements RdfFormatHandlerInterface {
                public function canHandle(string $content): bool {
                    return str_contains($content, 'CUSTOM_MARKER');
                }
                public function parse(string $content): ParsedRdf {
                    return new ParsedRdf(
                        graph: new \EasyRdf\Graph(),
                        format: 'custom',
                        rawContent: $content,
                        metadata: ['parser' => 'custom_handler'],
                    );
                }
                public function getFormatName(): string { return 'custom'; }
            };

            $this->parser->registerHandler($mockHandler);
            $formats = $this->parser->getSupportedFormats();
            // Custom handler should be first in the list
            expect($formats[0])->toBe('custom');
        });

        it('custom handler wins over default for matching content', function () {
            // Create a handler that claims to handle N-Triples content but returns custom format
            $mockHandler = new class implements RdfFormatHandlerInterface {
                public function canHandle(string $content): bool {
                    return str_contains($content, '<http://');
                }
                public function parse(string $content): ParsedRdf {
                    return new ParsedRdf(
                        graph: new \EasyRdf\Graph(),
                        format: 'custom-override',
                        rawContent: $content,
                        metadata: ['parser' => 'custom_override'],
                    );
                }
                public function getFormatName(): string { return 'custom-override'; }
            };

            $this->parser->registerHandler($mockHandler);
            $content = '<http://example.org/s> <http://example.org/p> <http://example.org/o> .';
            $result = $this->parser->parse($content);
            expect($result->metadata['format'])->toBe('custom-override');
        });

        it('custom format appears in getSupportedFormats()', function () {
            $mockHandler = new class implements RdfFormatHandlerInterface {
                public function canHandle(string $content): bool { return false; }
                public function parse(string $content): ParsedRdf {
                    return new ParsedRdf(graph: new \EasyRdf\Graph(), format: 'yaml-rdf', rawContent: $content, metadata: []);
                }
                public function getFormatName(): string { return 'yaml-rdf'; }
            };

            $this->parser->registerHandler($mockHandler);
            expect($this->parser->getSupportedFormats())->toContain('yaml-rdf');
        });

        it('canParse() uses all registered handlers including custom ones', function () {
            $mockHandler = new class implements RdfFormatHandlerInterface {
                public function canHandle(string $content): bool {
                    return str_contains($content, 'CUSTOM_MARKER');
                }
                public function parse(string $content): ParsedRdf {
                    return new ParsedRdf(graph: new \EasyRdf\Graph(), format: 'custom', rawContent: $content, metadata: []);
                }
                public function getFormatName(): string { return 'custom'; }
            };

            $this->parser->registerHandler($mockHandler);
            expect($this->parser->canParse('some CUSTOM_MARKER content'))->toBeTrue();
        });
    });
});
