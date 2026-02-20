<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\Exceptions\ParseException;
use Youri\vandenBogert\Software\ParserRdf\Handlers\NTriplesHandler;

describe('N-Triples Strict Validation', function () {

    beforeEach(function () {
        $this->handler = new NTriplesHandler();
    });

    describe('IRI validation', function () {

        it('rejects IRIs containing spaces', function () {
            $content = '<http://example/ space> <http://example/p> <http://example/o> .' . "\n";
            expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
        });

        it('rejects invalid 4-char unicode escape in IRI', function () {
            $content = '<http://example/\u00ZZ11> <http://example/p> <http://example/o> .' . "\n";
            expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
        });

        it('rejects invalid 8-char unicode escape in IRI', function () {
            $content = '<http://example/\U00ZZ1111> <http://example/p> <http://example/o> .' . "\n";
            expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
        });

        it('rejects non-unicode escapes in IRI', function () {
            $content = "<http://example/\\n> <http://example/p> <http://example/o> .\n";
            expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
        });

        it('rejects relative IRI in subject', function () {
            $content = '<s> <http://example/p> <http://example/o> .' . "\n";
            expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
        });

        it('rejects relative IRI in predicate', function () {
            $content = '<http://example/s> <p> <http://example/o> .' . "\n";
            expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
        });

        it('rejects relative IRI in object', function () {
            $content = '<http://example/s> <http://example/p> <o> .' . "\n";
            expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
        });

        it('rejects relative IRI in datatype', function () {
            $content = '<http://example/s> <http://example/p> "foo"^^<dt> .' . "\n";
            expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
        });
    });

    describe('blank node validation', function () {

        it('rejects blank node label starting with colon', function () {
            $content = '_::a <http://example/p> <http://example/o> .' . "\n";
            expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
        });

        it('rejects blank node label containing colon', function () {
            $content = '_:abc:def <http://example/p> <http://example/o> .' . "\n";
            expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
        });
    });

    describe('structure validation', function () {

        it('rejects object lists (comma syntax)', function () {
            $content = '<http://example/s> <http://example/p> <http://example/o>, <http://example/o2> .' . "\n";
            expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
        });

        it('rejects predicate-object lists (semicolon syntax)', function () {
            $content = '<http://example/s> <http://example/p> <http://example/o>; <http://example/p2> <http://example/o2> .' . "\n";
            expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
        });

        it('rejects triple-quoted strings', function () {
            $content = '<http://example/s> <http://example/p> """abc""" .' . "\n";
            expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
        });
    });

    describe('language tag validation', function () {

        it('rejects language tag starting with digit', function () {
            $content = '<http://example/s> <http://example/p> "string"@1 .' . "\n";
            expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
        });
    });

    describe('string escape validation', function () {

        it('rejects invalid escape sequence in string literal', function () {
            $content = '<http://example/s> <http://example/p> "a\zb" .' . "\n";
            expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
        });

        it('rejects invalid 4-char unicode escape in string literal', function () {
            $content = '<http://example/s> <http://example/p> "\uWXYZ" .' . "\n";
            expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
        });

        it('rejects invalid 8-char unicode escape in string literal', function () {
            $content = '<http://example/s> <http://example/p> "\U0000WXYZ" .' . "\n";
            expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
        });
    });

    describe('line number in error messages', function () {

        it('includes line number in ParseException message', function () {
            $content = "<http://example/s> <http://example/p> <http://example/o> .\n<http://example/ space> <http://example/p> <http://example/o> .\n";
            try {
                $this->handler->parse($content);
                $this->fail('Expected ParseException');
            } catch (ParseException $e) {
                expect($e->getMessage())->toContain('line 2');
            }
        });
    });

    describe('NFR: security and reliability', function () {

        it('rejects extremely long lines (over 1MB)', function () {
            $longIri = '<http://example/' . str_repeat('a', 1_048_576) . '>';
            $content = $longIri . ' <http://example/p> <http://example/o> .' . "\n";
            expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
        });
    });

    describe('valid N-Triples still parses correctly', function () {

        it('accepts valid triples', function () {
            $content = '<http://example/s> <http://example/p> <http://example/o> .' . "\n";
            $result = $this->handler->parse($content);
            expect($result->format)->toBe('n-triples');
        });

        it('accepts blank node subjects', function () {
            $content = '_:b0 <http://example/p> <http://example/o> .' . "\n";
            $result = $this->handler->parse($content);
            expect($result->format)->toBe('n-triples');
        });

        it('accepts language-tagged literals', function () {
            $content = '<http://example/s> <http://example/p> "hello"@en .' . "\n";
            $result = $this->handler->parse($content);
            expect($result->format)->toBe('n-triples');
        });

        it('accepts typed literals', function () {
            $content = '<http://example/s> <http://example/p> "42"^^<http://www.w3.org/2001/XMLSchema#integer> .' . "\n";
            $result = $this->handler->parse($content);
            expect($result->format)->toBe('n-triples');
        });

        it('accepts valid escape sequences in strings', function () {
            $content = '<http://example/s> <http://example/p> "line1\nline2\ttab" .' . "\n";
            $result = $this->handler->parse($content);
            expect($result->format)->toBe('n-triples');
        });

        it('accepts comment lines', function () {
            $content = "# This is a comment\n<http://example/s> <http://example/p> <http://example/o> .\n";
            $result = $this->handler->parse($content);
            expect($result->format)->toBe('n-triples');
        });

        it('accepts trailing comments after triple', function () {
            $content = '<http://example/s> <http://example/p> <http://example/o> . # comment' . "\n";
            $result = $this->handler->parse($content);
            expect($result->format)->toBe('n-triples');
        });
    });
});
