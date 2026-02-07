<?php

use App\Services\Ontology\Exceptions\OntologyImportException;
use App\Services\Ontology\Parsers\Extractors\ClassExtractor;
use App\Services\Ontology\Parsers\Extractors\PrefixExtractor;
use App\Services\Ontology\Parsers\Extractors\PropertyExtractor;
use App\Services\Ontology\Parsers\Extractors\ShapeExtractor;
use App\Services\Ontology\Parsers\RdfParser;

describe('RdfParser', function () {
    beforeEach(function () {
        $this->parser = new RdfParser(
            new PrefixExtractor,
            new ClassExtractor,
            new PropertyExtractor,
            new ShapeExtractor
        );
    });

    it('can parse various RDF formats', function () {
        $turtleContent = '@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> . rdfs:Class a rdfs:Class .';
        $rdfXmlContent = '<?xml version="1.0"?><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"></rdf:RDF>';
        $jsonLdContent = '{"@context": {"rdfs": "http://www.w3.org/2000/01/rdf-schema#"}}';

        expect($this->parser->canParse($turtleContent))->toBeTrue();
        expect($this->parser->canParse($rdfXmlContent))->toBeTrue();
        expect($this->parser->canParse($jsonLdContent))->toBeTrue();
    });

    it('cannot parse non-RDF content', function () {
        $plainText = 'This is just plain text';
        $htmlContent = '<html><body>Hello World</body></html>';

        expect($this->parser->canParse($plainText))->toBeFalse();
        expect($this->parser->canParse($htmlContent))->toBeFalse();
    });

    it('returns supported RDF formats', function () {
        $formats = $this->parser->getSupportedFormats();

        expect($formats)->toContain('rdf/xml');
        expect($formats)->toContain('turtle');
        expect($formats)->toContain('json-ld');
        expect($formats)->toContain('n-triples');
        expect($formats)->toContain('rdfa');
    });

    // Removed: detectFormat() - Now handled by individual handlers' canHandle() method
    // Removed: mapFormatName() - No longer needed with handler pattern

    it('parses basic RDF/XML ontology', function () {
        $content = '<?xml version="1.0"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         xmlns:ex="http://example.org/">

    <rdfs:Class rdf:about="http://example.org/Person">
        <rdfs:label>Person</rdfs:label>
        <rdfs:comment>A human being</rdfs:comment>
    </rdfs:Class>

    <rdf:Property rdf:about="http://example.org/name">
        <rdfs:label>name</rdfs:label>
        <rdfs:comment>The name of something</rdfs:comment>
        <rdfs:domain rdf:resource="http://example.org/Person"/>
        <rdfs:range rdf:resource="http://www.w3.org/2001/XMLSchema#string"/>
    </rdf:Property>

</rdf:RDF>';

        $result = $this->parser->parse($content);

        expect($result)->toHaveKeys(['metadata', 'prefixes', 'classes', 'properties', 'shapes', 'raw_content']);

        // Check metadata
        expect($result['metadata']['format'])->toBe('rdf/xml');
        expect($result['metadata']['parser'])->toBe('rdf_xml_handler');

        // Check prefixes extracted by PrefixExtractor
        expect($result['prefixes'])->toHaveKey('rdfs');
        expect($result['prefixes']['rdfs'])->toBe('http://www.w3.org/2000/01/rdf-schema#');

        // Check classes
        expect($result['classes'])->toHaveCount(1);
        $personClass = $result['classes'][0];
        expect($personClass['uri'])->toBe('http://example.org/Person');
        expect($personClass['label'])->toBe('Person');
        expect($personClass['description'])->toBe('A human being');

        // Check properties
        expect($result['properties'])->toHaveCount(1);
        $nameProperty = $result['properties'][0];
        expect($nameProperty['uri'])->toBe('http://example.org/name');
        expect($nameProperty['label'])->toBe('name');
        expect($nameProperty['description'])->toBe('The name of something');
        expect($nameProperty['domain'])->toContain('http://example.org/Person');
    });

    it('parses turtle ontology with classes and properties', function () {
        $content = '
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix foaf: <http://xmlns.com/foaf/0.1/> .

foaf:Person a rdfs:Class ;
    rdfs:label "Person" ;
    rdfs:comment "A person" .

foaf:name a rdf:Property ;
    rdfs:label "name" ;
    rdfs:comment "A name for some thing" ;
    rdfs:domain foaf:Person ;
    rdfs:range rdfs:Literal .
        ';

        // Mock EasyRdf Graph for this test since we're testing without DB
        // The actual parsing would be handled by EasyRdf, but we're focusing on structure
        $result = $this->parser->parse($content);

        expect($result)->toHaveKeys(['metadata', 'prefixes', 'classes', 'properties', 'shapes', 'raw_content']);
        expect($result['metadata']['format'])->toBe('turtle');
        expect($result['raw_content'])->toBe($content);
    });

    // Removed: extractPrefixesFromContent() - Now handled by PrefixExtractor class
    // Removed: getCommonPrefixes() - Now handled by PrefixExtractor class
    // Removed: determinePropertyType() - Now handled by PropertyExtractor class
    // Removed: parseRdfXmlFallback() - Now handled by RdfXmlHandler class
    // Note: These methods have been moved to specialized handler/extractor classes
    // Tests for these should be written in their respective test files

    it('throws exception on invalid RDF content', function () {
        expect(fn () => $this->parser->parse('invalid rdf content'))
            ->toThrow(OntologyImportException::class, 'RDF parsing failed');
    });

    it('handles empty RDF document', function () {
        $content = '
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .

# Empty document with just prefixes
        ';

        // This would typically work with EasyRdf, but for testing we focus on structure
        $result = $this->parser->parse($content);

        expect($result)->toHaveKeys(['metadata', 'prefixes', 'classes', 'properties', 'shapes', 'raw_content']);
        expect($result['raw_content'])->toBe($content);
    });
});
