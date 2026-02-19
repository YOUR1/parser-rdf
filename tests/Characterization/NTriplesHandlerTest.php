<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\Exceptions\ParseException;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserRdf\Handlers\NTriplesHandler;

describe('NTriplesHandler', function () {

    beforeEach(function () {
        $this->handler = new NTriplesHandler();
    });

    describe('canHandle()', function () {

        it('returns true for single valid N-Triples line', function () {
            $content = '<http://example.org/s> <http://example.org/p> <http://example.org/o> .';
            expect($this->handler->canHandle($content))->toBeTrue();
        });

        it('returns true for multiple valid N-Triples lines', function () {
            $content = '<http://example.org/s> <http://example.org/p> <http://example.org/o> .' . "\n"
                     . '<http://example.org/s2> <http://example.org/p2> <http://example.org/o2> .';
            expect($this->handler->canHandle($content))->toBeTrue();
        });

        it('returns true for N-Triples with comments (# comment lines skipped)', function () {
            $content = "# This is a comment\n"
                     . '<http://example.org/s> <http://example.org/p> <http://example.org/o> .';
            expect($this->handler->canHandle($content))->toBeTrue();
        });

        it('returns true for N-Triples with blank lines (empty lines skipped)', function () {
            $content = "\n\n"
                     . '<http://example.org/s> <http://example.org/p> <http://example.org/o> .';
            expect($this->handler->canHandle($content))->toBeTrue();
        });

        it('returns true for N-Triples with leading whitespace before <', function () {
            $content = '  <http://example.org/s> <http://example.org/p> <http://example.org/o> .';
            expect($this->handler->canHandle($content))->toBeTrue();
        });

        it('returns false for empty string', function () {
            expect($this->handler->canHandle(''))->toBeFalse();
        });

        it('returns false for Turtle content (@prefix ...)', function () {
            $content = '@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .';
            expect($this->handler->canHandle($content))->toBeFalse();
        });

        it('returns false for JSON-LD content ({"@context": ...})', function () {
            $content = '{"@context": {"rdf": "http://www.w3.org/1999/02/22-rdf-syntax-ns#"}}';
            expect($this->handler->canHandle($content))->toBeFalse();
        });

        it('returns false for RDF/XML content (<?xml ...)', function () {
            $content = '<?xml version="1.0"?><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"></rdf:RDF>';
            expect($this->handler->canHandle($content))->toBeFalse();
        });

        it('returns false for plain text content', function () {
            $content = 'This is just some plain text content.';
            expect($this->handler->canHandle($content))->toBeFalse();
        });

        it('returns false for comment-only content (# just comments)', function () {
            $content = "# This is a comment\n# Another comment\n# Nothing else";
            expect($this->handler->canHandle($content))->toBeFalse();
        });

        it('does not check beyond first 10 raw lines -- valid N-Triple on line 11 is never checked', function () {
            // Build 10 lines of empty/comment content, then a valid N-Triple on line 11
            $lines = [];
            for ($i = 0; $i < 5; $i++) {
                $lines[] = '# comment line ' . $i;
            }
            for ($i = 0; $i < 5; $i++) {
                $lines[] = '';
            }
            $lines[] = '<http://example.org/s> <http://example.org/p> <http://example.org/o> .';
            $content = implode("\n", $lines);

            // The valid line is beyond the 10-line window, so canHandle returns false
            expect($this->handler->canHandle($content))->toBeFalse();
        });

        it('returns true for N-Triples with string literals ("value")', function () {
            $content = '<http://example.org/s> <http://example.org/p> "some value" .';
            expect($this->handler->canHandle($content))->toBeTrue();
        });

        it('returns true for N-Triples with language-tagged literals ("value"@en)', function () {
            $content = '<http://example.org/s> <http://example.org/p> "some value"@en .';
            expect($this->handler->canHandle($content))->toBeTrue();
        });

        it('returns true for N-Triples with typed literals ("25"^^<xsd:integer>)', function () {
            $content = '<http://example.org/s> <http://example.org/p> "25"^^<http://www.w3.org/2001/XMLSchema#integer> .';
            expect($this->handler->canHandle($content))->toBeTrue();
        });
    });

    describe('parse()', function () {

        it('returns ParsedRdf instance for valid N-Triples', function () {
            $content = '<http://example.org/s> <http://example.org/p> <http://example.org/o> .';
            $result = $this->handler->parse($content);
            expect($result)->toBeInstanceOf(ParsedRdf::class);
        });

        it('has correct format property (n-triples)', function () {
            $content = '<http://example.org/s> <http://example.org/p> <http://example.org/o> .';
            $result = $this->handler->parse($content);
            expect($result->format)->toBe('n-triples');
        });

        it('has correct rawContent property (original input preserved)', function () {
            $content = '<http://example.org/s> <http://example.org/p> <http://example.org/o> .';
            $result = $this->handler->parse($content);
            expect($result->rawContent)->toBe($content);
        });

        it('metadata contains parser, format, and resource_count keys', function () {
            $content = '<http://example.org/s> <http://example.org/p> <http://example.org/o> .';
            $result = $this->handler->parse($content);

            expect($result->metadata)->toHaveKeys(['parser', 'format', 'resource_count']);
            expect($result->metadata['parser'])->toBe('ntriples_handler');
            expect($result->metadata['format'])->toBe('n-triples');
            expect($result->metadata['resource_count'])->toBeInt();
        });

        it('correctly parses N-Triples with class declaration -- graph contains the resource', function () {
            $content = '<http://example.org/Person> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2000/01/rdf-schema#Class> .';
            $result = $this->handler->parse($content);

            $resources = $result->graph->resources();
            $resourceUris = array_map(fn ($r) => $r->getUri(), $resources);
            expect($resourceUris)->toContain('http://example.org/Person');
        });

        it('correctly parses N-Triples with language-tagged literals', function () {
            $content = '<http://example.org/Person> <http://www.w3.org/2000/01/rdf-schema#label> "Person"@en .';
            $result = $this->handler->parse($content);

            $person = $result->graph->resource('http://example.org/Person');
            $label = $person->getLiteral('rdfs:label');
            expect((string) $label)->toBe('Person');
            expect($label->getLang())->toBe('en');
        });

        it('correctly parses N-Triples with typed literals', function () {
            $content = '<http://example.org/s> <http://example.org/age> "25"^^<http://www.w3.org/2001/XMLSchema#integer> .';
            $result = $this->handler->parse($content);

            // Verify the resource exists in the graph
            $resources = $result->graph->resources();
            $resourceUris = array_map(fn ($r) => $r->getUri(), $resources);
            expect($resourceUris)->toContain('http://example.org/s');

            // EasyRdf stores the literal -- verify via get() with full URI
            $resource = $result->graph->resource('http://example.org/s');
            $age = $resource->get('<http://example.org/age>');
            expect($age)->not->toBeNull();
            expect((string) $age)->toBe('25');
        });

        it('correctly parses multiple triples about same subject', function () {
            $content = '<http://example.org/Person> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2000/01/rdf-schema#Class> .' . "\n"
                     . '<http://example.org/Person> <http://www.w3.org/2000/01/rdf-schema#label> "Person" .' . "\n"
                     . '<http://example.org/Person> <http://www.w3.org/2000/01/rdf-schema#comment> "A human being" .';
            $result = $this->handler->parse($content);

            $person = $result->graph->resource('http://example.org/Person');
            expect((string) $person->getLiteral('rdfs:label'))->toBe('Person');
            expect((string) $person->getLiteral('rdfs:comment'))->toBe('A human being');
        });

        it('throws ParseException with N-Triples parsing failed prefix for invalid content', function () {
            $content = '<invalid syntax here';
            expect(fn () => $this->handler->parse($content))
                ->toThrow(ParseException::class, 'N-Triples parsing failed: ');
        });

        it('exception has $previous set (Throwable catch wraps with $previous)', function () {
            $content = '<invalid syntax here';
            try {
                $this->handler->parse($content);
                $this->fail('Expected exception not thrown');
            } catch (ParseException $e) {
                expect($e->getPrevious())->not->toBeNull();
            }
        });
    });

    describe('getFormatName()', function () {

        it('returns n-triples', function () {
            expect($this->handler->getFormatName())->toBe('n-triples');
        });

        it('return value is a string', function () {
            expect($this->handler->getFormatName())->toBeString();
        });
    });
});
