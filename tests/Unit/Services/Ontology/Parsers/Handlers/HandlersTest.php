<?php

use App\Services\Ontology\Exceptions\OntologyImportException;
use App\Services\Ontology\Parsers\Handlers\JsonLdHandler;
use App\Services\Ontology\Parsers\Handlers\NTriplesHandler;
use App\Services\Ontology\Parsers\Handlers\TurtleHandler;
use App\Services\Ontology\Parsers\ValueObjects\ParsedRdf;

describe('TurtleHandler', function () {
    beforeEach(function () {
        $this->handler = new TurtleHandler;
    });

    it('detects Turtle format with @prefix', function () {
        $content = '@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .';

        expect($this->handler->canHandle($content))->toBeTrue();
    });

    it('detects Turtle format with SPARQL PREFIX', function () {
        $content = 'PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>';

        expect($this->handler->canHandle($content))->toBeTrue();
    });

    it('detects Turtle format with @prefix not at start', function () {
        $content = "# Comment\n@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .";

        expect($this->handler->canHandle($content))->toBeTrue();
    });

    it('returns correct format name', function () {
        expect($this->handler->getFormatName())->toBe('turtle');
    });

    it('does not detect non-Turtle content', function () {
        $content = '{"@context": {}}';

        expect($this->handler->canHandle($content))->toBeFalse();
    });

    it('does not detect XML content', function () {
        $content = '<?xml version="1.0"?><rdf:RDF></rdf:RDF>';

        expect($this->handler->canHandle($content))->toBeFalse();
    });

    it('parses valid Turtle content', function () {
        $content = '
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

<http://example.org/Person> a rdfs:Class ;
    rdfs:label "Person" ;
    rdfs:comment "A human being" .
        ';

        $result = $this->handler->parse($content);

        expect($result)->toBeInstanceOf(ParsedRdf::class);
        expect($result->format)->toBe('turtle');
        expect($result->rawContent)->toBe($content);
    });

    it('stores parser metadata', function () {
        $content = '@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .';

        $result = $this->handler->parse($content);

        expect($result->metadata)->toHaveKey('parser');
        expect($result->metadata['parser'])->toBe('turtle_handler');
        expect($result->metadata)->toHaveKey('format');
        expect($result->metadata['format'])->toBe('turtle');
    });

    it('throws exception on invalid Turtle syntax', function () {
        $content = '@prefix invalid syntax here';

        expect(fn () => $this->handler->parse($content))
            ->toThrow(OntologyImportException::class, 'Turtle parsing failed');
    });

    it('handles empty Turtle document', function () {
        $content = '@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .';

        $result = $this->handler->parse($content);

        expect($result)->toBeInstanceOf(ParsedRdf::class);
    });
});

describe('JsonLdHandler', function () {
    beforeEach(function () {
        $this->handler = new JsonLdHandler;
    });

    it('detects JSON-LD format with @context', function () {
        $content = '{"@context": {"rdf": "http://www.w3.org/1999/02/22-rdf-syntax-ns#"}}';

        expect($this->handler->canHandle($content))->toBeTrue();
    });

    it('detects JSON-LD format with whitespace', function () {
        $content = "  \n  {\"@context\": {}}";

        expect($this->handler->canHandle($content))->toBeTrue();
    });

    it('returns correct format name', function () {
        expect($this->handler->getFormatName())->toBe('json-ld');
    });

    it('does not detect non-JSON content', function () {
        $content = '@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .';

        expect($this->handler->canHandle($content))->toBeFalse();
    });

    it('does not detect JSON without @context', function () {
        $content = '{"name": "test", "value": 123}';

        expect($this->handler->canHandle($content))->toBeFalse();
    });

    it('does not detect content not starting with {', function () {
        $content = 'invalid {"@context": {}}';

        expect($this->handler->canHandle($content))->toBeFalse();
    });

    it('parses valid JSON-LD content', function () {
        $content = '{
            "@context": {
                "rdf": "http://www.w3.org/1999/02/22-rdf-syntax-ns#",
                "rdfs": "http://www.w3.org/2000/01/rdf-schema#"
            },
            "@id": "http://example.org/Person",
            "@type": "rdfs:Class",
            "rdfs:label": "Person",
            "rdfs:comment": "A human being"
        }';

        $result = $this->handler->parse($content);

        expect($result)->toBeInstanceOf(ParsedRdf::class);
        expect($result->format)->toBe('json-ld');
        expect($result->rawContent)->toBe($content);
    });

    it('stores @context in metadata', function () {
        $content = '{
            "@context": {
                "rdf": "http://www.w3.org/1999/02/22-rdf-syntax-ns#"
            }
        }';

        $result = $this->handler->parse($content);

        expect($result->metadata)->toHaveKey('context');
        expect($result->metadata['context'])->toBeArray();
        expect($result->metadata['context'])->toHaveKey('rdf');
    });

    it('stores parser metadata', function () {
        $content = '{"@context": {}}';

        $result = $this->handler->parse($content);

        expect($result->metadata)->toHaveKey('parser');
        expect($result->metadata['parser'])->toBe('jsonld_handler');
        expect($result->metadata)->toHaveKey('format');
        expect($result->metadata['format'])->toBe('json-ld');
    });

    it('throws exception on invalid JSON', function () {
        $content = '{"@context": invalid json}';

        expect(fn () => $this->handler->parse($content))
            ->toThrow(OntologyImportException::class, 'Invalid JSON');
    });

    it('throws exception on missing @context', function () {
        $content = '{"name": "test"}';

        expect(fn () => $this->handler->parse($content))
            ->toThrow(OntologyImportException::class, 'Missing @context');
    });

    it('handles minimal JSON-LD document', function () {
        $content = '{"@context": {}}';

        $result = $this->handler->parse($content);

        expect($result)->toBeInstanceOf(ParsedRdf::class);
    });
});

describe('NTriplesHandler', function () {
    beforeEach(function () {
        $this->handler = new NTriplesHandler;
    });

    it('detects N-Triples format', function () {
        $content = '<http://example.org/Person> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2000/01/rdf-schema#Class> .';

        expect($this->handler->canHandle($content))->toBeTrue();
    });

    it('detects N-Triples format with multiple lines', function () {
        $content = '
<http://example.org/Person> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2000/01/rdf-schema#Class> .
<http://example.org/Person> <http://www.w3.org/2000/01/rdf-schema#label> "Person" .
        ';

        expect($this->handler->canHandle($content))->toBeTrue();
    });

    it('detects N-Triples format with comments', function () {
        $content = '
# This is a comment
<http://example.org/Person> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2000/01/rdf-schema#Class> .
        ';

        expect($this->handler->canHandle($content))->toBeTrue();
    });

    it('returns correct format name', function () {
        expect($this->handler->getFormatName())->toBe('n-triples');
    });

    it('does not detect non-N-Triples content', function () {
        $content = '@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .';

        expect($this->handler->canHandle($content))->toBeFalse();
    });

    it('does not detect JSON content', function () {
        $content = '{"@context": {}}';

        expect($this->handler->canHandle($content))->toBeFalse();
    });

    it('parses valid N-Triples content', function () {
        $content = '
<http://example.org/Person> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2000/01/rdf-schema#Class> .
<http://example.org/Person> <http://www.w3.org/2000/01/rdf-schema#label> "Person" .
<http://example.org/Person> <http://www.w3.org/2000/01/rdf-schema#comment> "A human being" .
        ';

        $result = $this->handler->parse($content);

        expect($result)->toBeInstanceOf(ParsedRdf::class);
        expect($result->format)->toBe('n-triples');
        expect($result->rawContent)->toBe($content);
    });

    it('stores parser metadata', function () {
        $content = '<http://example.org/s> <http://example.org/p> <http://example.org/o> .';

        $result = $this->handler->parse($content);

        expect($result->metadata)->toHaveKey('parser');
        expect($result->metadata['parser'])->toBe('ntriples_handler');
        expect($result->metadata)->toHaveKey('format');
        expect($result->metadata['format'])->toBe('n-triples');
    });

    it('throws exception on invalid N-Triples syntax', function () {
        $content = '<invalid syntax here';

        expect(fn () => $this->handler->parse($content))
            ->toThrow(OntologyImportException::class, 'N-Triples parsing failed');
    });

    it('handles N-Triples with literals', function () {
        $content = '<http://example.org/Person> <http://www.w3.org/2000/01/rdf-schema#label> "Person"@en .';

        $result = $this->handler->parse($content);

        expect($result)->toBeInstanceOf(ParsedRdf::class);
    });

    it('handles N-Triples with typed literals', function () {
        $content = '<http://example.org/age> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> "25"^^<http://www.w3.org/2001/XMLSchema#integer> .';

        $result = $this->handler->parse($content);

        expect($result)->toBeInstanceOf(ParsedRdf::class);
    });
});
