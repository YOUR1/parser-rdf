<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedOntology;
use Youri\vandenBogert\Software\ParserRdf\RdfParser;

function integrationFixture(string $filename): string
{
    $path = __DIR__ . '/../Fixtures/Integration/' . $filename;
    if (! file_exists($path)) {
        throw new \RuntimeException("Integration fixture not found: {$path}");
    }

    $content = file_get_contents($path);
    if ($content === false) {
        throw new \RuntimeException("Failed to read integration fixture: {$path}");
    }

    return $content;
}

describe('RdfParser Full Pipeline Integration Tests', function () {

    beforeEach(function () {
        $this->parser = new RdfParser();
    });

    describe('N-Triples pipeline', function () {
        it('auto-detects N-Triples format and produces ParsedOntology', function () {
            $content = integrationFixture('sample.nt');
            $result = $this->parser->parse($content);

            expect($result)->toBeInstanceOf(ParsedOntology::class);
            expect($result->metadata['format'])->toBe('n-triples');
        });

        it('extracts classes from N-Triples content', function () {
            $content = integrationFixture('sample.nt');
            $result = $this->parser->parse($content);

            expect($result->classes)->toHaveKey('http://example.org/Person');
        });

        it('extracts properties from N-Triples content', function () {
            $content = integrationFixture('sample.nt');
            $result = $this->parser->parse($content);

            expect($result->properties)->toHaveKey('http://example.org/name');
        });
    });

    describe('Turtle pipeline', function () {
        it('auto-detects Turtle format and produces ParsedOntology', function () {
            $content = integrationFixture('sample.ttl');
            $result = $this->parser->parse($content);

            expect($result)->toBeInstanceOf(ParsedOntology::class);
            expect($result->metadata['format'])->toBe('turtle');
        });

        it('extracts classes from Turtle content', function () {
            $content = integrationFixture('sample.ttl');
            $result = $this->parser->parse($content);

            expect($result->classes)->toHaveKey('http://example.org/Person');
        });

        it('extracts properties from Turtle content', function () {
            $content = integrationFixture('sample.ttl');
            $result = $this->parser->parse($content);

            expect($result->properties)->toHaveKey('http://example.org/name');
        });

        it('extracts prefixes from Turtle content', function () {
            $content = integrationFixture('sample.ttl');
            $result = $this->parser->parse($content);

            expect($result->prefixes)->toHaveKey('ex');
            expect($result->prefixes['ex'])->toBe('http://example.org/');
        });
    });

    describe('RDF/XML pipeline', function () {
        it('auto-detects RDF/XML format and produces ParsedOntology', function () {
            $content = integrationFixture('sample.rdf');
            $result = $this->parser->parse($content);

            expect($result)->toBeInstanceOf(ParsedOntology::class);
            expect($result->metadata['format'])->toBe('rdf/xml');
        });

        it('extracts classes from RDF/XML content', function () {
            $content = integrationFixture('sample.rdf');
            $result = $this->parser->parse($content);

            expect($result->classes)->toHaveKey('http://example.org/Person');
        });

        it('extracts properties from RDF/XML content', function () {
            $content = integrationFixture('sample.rdf');
            $result = $this->parser->parse($content);

            expect($result->properties)->toHaveKey('http://example.org/name');
        });
    });

    describe('JSON-LD pipeline', function () {
        it('auto-detects JSON-LD format and produces ParsedOntology', function () {
            $content = integrationFixture('sample.jsonld');
            $result = $this->parser->parse($content);

            expect($result)->toBeInstanceOf(ParsedOntology::class);
            expect($result->metadata['format'])->toBe('json-ld');
        });

        it('extracts classes from JSON-LD content', function () {
            $content = integrationFixture('sample.jsonld');
            $result = $this->parser->parse($content);

            expect($result->classes)->toHaveKey('http://example.org/Person');
        });

        it('extracts properties from JSON-LD content', function () {
            $content = integrationFixture('sample.jsonld');
            $result = $this->parser->parse($content);

            expect($result->properties)->toHaveKey('http://example.org/name');
        });
    });

    describe('canParse detection', function () {
        it('returns true for N-Triples content', function () {
            $content = integrationFixture('sample.nt');
            expect($this->parser->canParse($content))->toBeTrue();
        });

        it('returns true for Turtle content', function () {
            $content = integrationFixture('sample.ttl');
            expect($this->parser->canParse($content))->toBeTrue();
        });

        it('returns true for RDF/XML content', function () {
            $content = integrationFixture('sample.rdf');
            expect($this->parser->canParse($content))->toBeTrue();
        });

        it('returns true for JSON-LD content', function () {
            $content = integrationFixture('sample.jsonld');
            expect($this->parser->canParse($content))->toBeTrue();
        });
    });

    describe('getSupportedFormats', function () {
        it('returns all expected format names', function () {
            $formats = $this->parser->getSupportedFormats();

            expect($formats)->toContain('json-ld');
            expect($formats)->toContain('turtle');
            expect($formats)->toContain('n-triples');
            expect($formats)->toContain('rdf/xml');
        });
    });
});
