<?php

use App\Services\Ontology\Parsers\Extractors\ClassExtractor;
use App\Services\Ontology\Parsers\Extractors\PrefixExtractor;
use App\Services\Ontology\Parsers\Extractors\PropertyExtractor;
use App\Services\Ontology\Parsers\Extractors\ShapeExtractor;
use App\Services\Ontology\Parsers\RdfParser;

describe('Extractors Integration', function () {
    beforeEach(function () {
        $this->parser = new RdfParser(
            new PrefixExtractor,
            new ClassExtractor,
            new PropertyExtractor,
            new ShapeExtractor
        );
    });

    it('extracts classes from Turtle ontology', function () {
        $content = '
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .
@prefix ex: <http://example.org/> .

ex:Person a rdfs:Class, owl:Class ;
    rdfs:label "Person"@en ;
    rdfs:label "Persoon"@nl ;
    rdfs:comment "A human being"@en ;
    rdfs:subClassOf ex:LivingThing .

ex:Organization a rdfs:Class ;
    rdfs:label "Organization" ;
    rdfs:comment "An organized group of people" .
        ';

        $result = $this->parser->parse($content);

        expect($result['classes'])->toHaveCount(2);

        $personClass = collect($result['classes'])->firstWhere('uri', 'http://example.org/Person');
        expect($personClass)->not->toBeNull();
        expect($personClass['label'])->toBeString();
        expect($personClass['parent_classes'])->toContain('http://example.org/LivingThing');

        $orgClass = collect($result['classes'])->firstWhere('uri', 'http://example.org/Organization');
        expect($orgClass)->not->toBeNull();
    });

    it('extracts properties from Turtle ontology', function () {
        $content = '
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
@prefix ex: <http://example.org/> .

ex:name a owl:DatatypeProperty ;
    rdfs:label "name" ;
    rdfs:comment "The name of something" ;
    rdfs:domain ex:Person ;
    rdfs:range xsd:string .

ex:knows a owl:ObjectProperty ;
    rdfs:label "knows" ;
    rdfs:comment "Indicates acquaintance" ;
    rdfs:domain ex:Person ;
    rdfs:range ex:Person .

ex:age a owl:FunctionalProperty, owl:DatatypeProperty ;
    rdfs:label "age" ;
    rdfs:domain ex:Person ;
    rdfs:range xsd:integer .
        ';

        $result = $this->parser->parse($content);

        expect($result['properties'])->toHaveCount(3);

        $nameProperty = collect($result['properties'])->firstWhere('uri', 'http://example.org/name');
        expect($nameProperty)->not->toBeNull();
        expect($nameProperty['property_type'])->toBe('datatype');
        expect($nameProperty['label'])->toBe('name');

        $knowsProperty = collect($result['properties'])->firstWhere('uri', 'http://example.org/knows');
        expect($knowsProperty)->not->toBeNull();
        expect($knowsProperty['property_type'])->toBe('object');

        $ageProperty = collect($result['properties'])->firstWhere('uri', 'http://example.org/age');
        expect($ageProperty)->not->toBeNull();
    });

    it('extracts SHACL shapes from Turtle', function () {
        $content = '
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:name ;
        sh:datatype xsd:string ;
        sh:minCount 1 ;
        sh:maxCount 1
    ] .
        ';

        $result = $this->parser->parse($content);

        expect($result['shapes'])->not->toBeEmpty();
        $personShape = collect($result['shapes'])->firstWhere('uri', 'http://example.org/PersonShape');
        expect($personShape)->not->toBeNull();
    });

    it('extracts all components from complex ontology', function () {
        $content = '
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .

# Classes
ex:Person a owl:Class ;
    rdfs:label "Person" .

# Properties
ex:name a owl:DatatypeProperty ;
    rdfs:label "name" ;
    rdfs:domain ex:Person ;
    rdfs:range xsd:string .

# Shapes
ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person .
        ';

        $result = $this->parser->parse($content);

        // Should have prefixes
        expect($result['prefixes'])->toHaveKey('rdf');
        expect($result['prefixes'])->toHaveKey('rdfs');
        expect($result['prefixes'])->toHaveKey('owl');
        expect($result['prefixes'])->toHaveKey('xsd');
        expect($result['prefixes'])->toHaveKey('sh');
        expect($result['prefixes'])->toHaveKey('ex');

        // Should have at least one class
        expect($result['classes'])->not->toBeEmpty();
        $personClass = collect($result['classes'])->firstWhere('uri', 'http://example.org/Person');
        expect($personClass)->not->toBeNull();

        // Should have at least one property
        expect($result['properties'])->not->toBeEmpty();
        $nameProperty = collect($result['properties'])->firstWhere('uri', 'http://example.org/name');
        expect($nameProperty)->not->toBeNull();

        // Should have at least one shape
        expect($result['shapes'])->not->toBeEmpty();
    });

    it('handles multilingual labels and descriptions', function () {
        $content = '
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix ex: <http://example.org/> .

ex:Person a rdfs:Class ;
    rdfs:label "Person"@en ;
    rdfs:label "Persoon"@nl ;
    rdfs:label "Personne"@fr ;
    rdfs:comment "A human being"@en ;
    rdfs:comment "Een mens"@nl .
        ';

        $result = $this->parser->parse($content);

        $personClass = collect($result['classes'])->firstWhere('uri', 'http://example.org/Person');
        expect($personClass)->not->toBeNull();

        // Should have some form of label
        expect($personClass['label'])->toBeString();
    });

    it('extracts property constraints correctly', function () {
        $content = '
@prefix sh: <http://www.w3.org/ns/shacl#> .
@prefix ex: <http://example.org/> .
@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

ex:PersonShape a sh:NodeShape ;
    sh:targetClass ex:Person ;
    sh:property [
        sh:path ex:age ;
        sh:datatype xsd:integer ;
        sh:minInclusive 0 ;
        sh:maxInclusive 150 ;
        sh:minCount 1 ;
        sh:maxCount 1
    ] ;
    sh:property [
        sh:path ex:email ;
        sh:datatype xsd:string ;
        sh:pattern "^[a-zA-Z0-9+_.-]+@[a-zA-Z0-9.-]+$"
    ] .
        ';

        $result = $this->parser->parse($content);

        expect($result['shapes'])->not->toBeEmpty();
        $personShape = collect($result['shapes'])->firstWhere('uri', 'http://example.org/PersonShape');
        expect($personShape)->not->toBeNull();
        expect($personShape['property_shapes'])->not->toBeEmpty();
    });

    it('extracts blank node structures', function () {
        $content = '
@prefix owl: <http://www.w3.org/2002/07/owl#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix ex: <http://example.org/> .

ex:Parent a owl:Class ;
    rdfs:subClassOf [
        a owl:Restriction ;
        owl:onProperty ex:hasChild ;
        owl:minCardinality 1
    ] .
        ';

        $result = $this->parser->parse($content);

        $parentClass = collect($result['classes'])->firstWhere('uri', 'http://example.org/Parent');
        expect($parentClass)->not->toBeNull();
    });

    it('handles union and intersection classes', function () {
        $content = '
@prefix owl: <http://www.w3.org/2002/07/owl#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix ex: <http://example.org/> .

ex:ParentOrTeacher a owl:Class ;
    owl:unionOf ( ex:Parent ex:Teacher ) .
        ';

        $result = $this->parser->parse($content);

        // Should parse without errors
        expect($result['classes'])->toBeArray();
    });

    it('extracts properties with multiple domains and ranges', function () {
        $content = '
@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .
@prefix ex: <http://example.org/> .

ex:creator a owl:ObjectProperty ;
    rdfs:domain ex:Document ;
    rdfs:domain ex:Artwork ;
    rdfs:range ex:Person ;
    rdfs:range ex:Organization .
        ';

        $result = $this->parser->parse($content);

        $creatorProperty = collect($result['properties'])->firstWhere('uri', 'http://example.org/creator');
        expect($creatorProperty)->not->toBeNull();
        expect($creatorProperty['domain'])->toBeArray();
        expect($creatorProperty['range'])->toBeArray();
    });
});
