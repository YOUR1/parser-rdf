# Spec Completeness

> Assessment of parser-rdf implementation coverage against [W3C RDF 1.1 Concepts and Abstract Syntax](https://www.w3.org/TR/rdf11-concepts/) and related serialization specifications.
> Last updated: 2026-02-19

## Scope

This library is the **base RDF parsing layer** for the parser ecosystem. It provides multi-format
parsing (Turtle, RDF/XML, JSON-LD, N-Triples) and entity extraction (classes, properties, prefixes,
shapes). OWL-specific features are handled by `parser-owl`; SHACL-specific features by `parser-shacl`.
Both extend `RdfParser`.

Supported serialization formats: **Turtle**, **RDF/XML**, **JSON-LD**, **N-Triples**.

## Summary

| Spec Area | Implemented | Total | Coverage |
|---|---|---|---|
| RDF Concepts -- Triples & Graphs | 7 | 7 | 100% |
| RDF Concepts -- IRIs | 3 | 4 | 75% |
| RDF Concepts -- Blank Nodes | 4 | 4 | 100% |
| RDF Concepts -- Literals | 4 | 5 | 80% |
| RDF Concepts -- Datatypes | 3 | 5 | 60% |
| RDFS Vocabulary | 13 | 13 | 100% |
| Serialization -- Turtle | 8 | 10 | 80% |
| Serialization -- RDF/XML | 6 | 11 | 55% |
| Serialization -- JSON-LD | 3 | 6 | 50% |
| Serialization -- N-Triples | 7 | 7 | 100% |
| Extractors | 14 | 14 | 100% |
| Orchestration (RdfParser) | 9 | 9 | 100% |
| Error Handling | 5 | 5 | 100% |
| W3C N-Triples Conformance | 69 | 70 | 99% |
| **Overall (weighted)** | | | **~89%** |

---

## RDF 1.1 Concepts and Abstract Syntax

Reference: [W3C RDF 1.1 Concepts](https://www.w3.org/TR/rdf11-concepts/)

### Section 3 -- RDF Triples and Graphs

| Feature | Status | Location | Tests |
|---|---|---|---|
| Triple (subject, predicate, object) | implemented | `NTriplesHandler:43-63`, delegated to EasyRdf `Graph::parse()` | `NTriplesHandlerTest` (Unit:26 tests), `RdfParserPipelineTest:30-51` |
| Multiple triples about same subject | implemented | via EasyRdf graph merging | `NTriplesHandlerTest` Unit:158-167 |
| RDF Graph (set of triples) | implemented | `ParsedRdf.graph` holds `EasyRdf\Graph` instance | `RdfParserTest` Unit:192-203 |
| Resource count from graph | implemented | `RdfParser:128` via `ParsedRdf::getResourceCount()` | `RdfParserTest` Unit:134-140, Char:158-165 |
| Named graphs | implemented | `ParsedOntology.graphs` keyed by graph URI; `RdfParser::buildGraphs()` | `NamedGraphSupportTest` Unit:10 tests |
| RDF Dataset (multiple named graphs) | implemented | `ParsedOntology.graphs` with `_:default` sentinel + named URIs | `NamedGraphSupportTest` Unit:10 tests |
| Graph merging | not implemented | no explicit merge API; single-parse only | -- |

### Section 4 -- IRIs

| Feature | Status | Location | Tests |
|---|---|---|---|
| Absolute IRI references as resource identifiers | implemented | all extractors output full URIs; `ClassExtractor:66`, `PropertyExtractor:67` | `ClassExtractorTest` Unit:72-89, Char:73-89 |
| IRI resolution (prefix expansion) | implemented | EasyRdf resolves prefixed names to full URIs | `ClassExtractorTest` Unit:72-89 |
| IRI comparison (string equality) | implemented | extractors use string comparison on full URIs | `PropertyExtractorTest` Unit:159, Char:116 |
| IRI validation (RFC 3987) | not implemented | delegated to EasyRdf which is permissive | W3C conformance: 9 bad-uri tests skipped |

### Section 5 -- Blank Nodes

| Feature | Status | Location | Tests |
|---|---|---|---|
| Blank node identification (`_:` prefix) | implemented | `ClassExtractor:69` via `isBlankNode()` from `ResourceHelperTrait` | `ClassExtractorTest` Unit:227-248, Char:246-266 |
| Blank node filtering (excluded from extraction output) | implemented | `ClassExtractor:69`, `PropertyExtractor:69` | `PropertyExtractorTest` Unit:464-480, Char:542-561 |
| Blank node traversal (for complex expressions) | implemented | `PropertyExtractor:153-172` traverses blank nodes for `owl:unionOf` domain/range | `PropertyExtractorTest` Char:360-386 |
| Blank node skolemization | implemented | `ClassExtractor:75-79`, `PropertyExtractor:69-73`; opt-in via `$options['includeSkolemizedBlankNodes']`; `urn:bnode:{id}` pattern | `BlankNodeSkolemizationTest` Unit:8 tests |

### Section 6 -- Literals

| Feature | Status | Location | Tests |
|---|---|---|---|
| Plain string literals (`"value"`) | implemented | via EasyRdf; `NTriplesHandler` regex detects `"value" .` pattern at line 35 | `NTriplesHandlerTest` Unit:41-44 |
| Language-tagged literals (`"value"@en`) | implemented | via EasyRdf; extractors use `getAllResourceLabels()` / `getAllResourceComments()` | `NTriplesHandlerTest` Unit:134-142, `ClassExtractorTest` Unit:91-112 |
| Typed literals (`"value"^^<datatype>`) | implemented | via EasyRdf; `NTriplesHandler` regex detects `"value"^^<type> .` | `NTriplesHandlerTest` Unit:144-156, Char:155-169 |
| Multilingual label/comment extraction | implemented | `ClassExtractor:76-78`, `PropertyExtractor:85-89`, `ResourceHelperTrait` | `ClassExtractorTest` Unit:91-112, 133-153 |
| Lexical form normalization | not implemented | delegated to EasyRdf | -- |

### Section 7 -- Datatypes

| Feature | Status | Location | Tests |
|---|---|---|---|
| `xsd:string` | implemented | `PropertyExtractor:237-241` range-from-comment fallback | `PropertyExtractorTest` Unit:236-252 |
| `xsd:integer` | implemented | `PropertyExtractor:252-254` range-from-comment fallback | `PropertyExtractorTest` Unit:290-306 |
| `xsd:boolean` | implemented | `PropertyExtractor:248-250` range-from-comment fallback | `PropertyExtractorTest` Unit:272-288 |
| `xsd:dateTime` | not explicitly implemented | recognized via comment fallback at `PropertyExtractor:244-246` only | `PropertyExtractorTest` Unit:254-270 |
| `rdf:langString` | not explicitly implemented | recognized via comment fallback at `PropertyExtractor:232-234` only | `PropertyExtractorTest` Unit:200-216 |

---

## RDFS (RDF Schema)

Reference: [RDF Schema W3C Recommendation](https://www.w3.org/TR/rdf-schema/)

| Feature | Status | Location | Tests |
|---|---|---|---|
| `rdfs:Class` detection | implemented | `ClassExtractor:29` constant `CLASS_TYPE_URIS` | `ClassExtractorTest` Unit:17-35, Char:17-33 |
| `owl:Class` detection | implemented | `ClassExtractor:30` constant `CLASS_TYPE_URIS` | `ClassExtractorTest` Unit:37-53, Char:36-51 |
| `rdfs:subClassOf` | implemented | `ClassExtractor:79` via `getResourceValues()` | `ClassExtractorTest` Unit:173-199, 201-225 |
| `rdfs:subPropertyOf` | implemented | `PropertyExtractor:92` | `PropertyExtractorTest` Unit:331-349, Char:313-334 |
| `rdfs:domain` | implemented | `PropertyExtractor:90` via `extractPropertyClassExpression()` | `PropertyExtractorTest` Unit:143-159, Char:225-240 |
| `rdfs:range` | implemented | `PropertyExtractor:77-81` (formal) + comment fallback | `PropertyExtractorTest` Unit:161-178, Char:241-258 |
| `rdfs:label` (multilingual) | implemented | via `ResourceHelperTrait::getResourceLabel()` / `getAllResourceLabels()` | `ClassExtractorTest` Unit:91-131, `PropertyExtractorTest` Unit:370-409 |
| `rdfs:comment` (multilingual) | implemented | via `ResourceHelperTrait::getResourceComment()` / `getAllResourceComments()` | `ClassExtractorTest` Unit:133-171 |
| `rdfs:seeAlso` | implemented | `ClassExtractor` + `PropertyExtractor` metadata `see_also` array | `RdfsVocabularyCompletenessTest` Unit:3 class tests + 2 property tests |
| `rdfs:isDefinedBy` | implemented | `ClassExtractor` + `PropertyExtractor` metadata `is_defined_by` array | `RdfsVocabularyCompletenessTest` Unit:2 class tests + 1 property test |
| `rdfs:Datatype` | implemented | `ClassExtractor:31` constant `CLASS_TYPE_URIS` | `RdfsVocabularyCompletenessTest` Unit:2 tests |
| `rdfs:Container` | implemented | `ClassExtractor:32` constant `CLASS_TYPE_URIS` | `RdfsVocabularyCompletenessTest` Unit:1 test |
| `rdfs:Literal` | implemented | `ClassExtractor:33` constant `CLASS_TYPE_URIS` | `RdfsVocabularyCompletenessTest` Unit:1 test |

---

## Serialization Formats

### N-Triples

Reference: [N-Triples W3C Recommendation](https://www.w3.org/TR/n-triples/)

| Feature | Status | Location | Tests |
|---|---|---|---|
| Basic triple parsing (`<s> <p> <o> .`) | implemented | `NTriplesHandler:43-63` via EasyRdf `ntriples` parser | `NTriplesHandlerTest` Unit:97-101, Char:108-111 |
| Format detection (regex, first 10 lines) | implemented | `NTriplesHandler:20-41`, regex: `/^<[^>]+>\s+<[^>]+>\s+.+\s*\.$/` | `NTriplesHandlerTest` Unit:80-92, Char:74-88 |
| Comment lines (`# ...`) | implemented | `NTriplesHandler:31` skips lines starting with `#` | `NTriplesHandlerTest` Unit:29-33, Char:28-32 |
| Blank node subjects/objects (`_:label`) | implemented | via EasyRdf | `NTriplesHandlerTest` Unit:125-132 |
| Language-tagged literals (`"v"@lang`) | implemented | via EasyRdf | `NTriplesHandlerTest` Unit:134-142, Char:145-153 |
| Typed literals (`"v"^^<datatype>`) | implemented | via EasyRdf | `NTriplesHandlerTest` Unit:144-156, Char:155-169 |
| Pre-parse strict validation | implemented | `NTriplesHandler::validateContent()` with line-by-line checks | `NTriplesStrictValidationTest` Unit:25 tests |
| Inline comment stripping | implemented | `NTriplesHandler::stripInlineComments()` strips `# ...` after terminal `.` | `NTriplesStrictValidationTest` Unit:trailing comment test |
| N-Quads support | not implemented | -- | -- |

### Turtle

Reference: [Turtle W3C Recommendation](https://www.w3.org/TR/turtle/)

| Feature | Status | Location | Tests |
|---|---|---|---|
| `@prefix` declarations | implemented | detected by TurtleHandler (external); `PrefixExtractor:91-103` regex | `PrefixExtractorTest` Unit:15-31, Char:17-33 |
| `PREFIX` (SPARQL style) | implemented | `PrefixExtractor:105-113` regex | `PrefixExtractorTest` Unit:33-46, Char:35-49 |
| Blank nodes `[]` | implemented | via EasyRdf | `ClassExtractorTest` Unit:227-248 |
| Collections / list syntax `()` | implemented | via EasyRdf; `PropertyExtractor:183-202` traverses `rdf:first`/`rdf:rest` | `PropertyExtractorTest` Char:360-386 |
| Multi-valued properties `;` | implemented | via EasyRdf | `RdfParserTest` Char:214-243 |
| Object lists `,` | implemented | via EasyRdf | -- |
| Typed literals `^^` | implemented | via EasyRdf | `PropertyExtractorTest` Unit:161-178 |
| Language-tagged strings `@en` | implemented | via EasyRdf | `ClassExtractorTest` Unit:91-112 |
| `@base` / `BASE` | not implemented | -- | -- |
| String escape sequences | not implemented | delegated to EasyRdf (partial) | -- |

### RDF/XML

Reference: [RDF/XML Syntax W3C Recommendation](https://www.w3.org/TR/rdf-syntax-grammar/)

| Feature | Status | Location | Tests |
|---|---|---|---|
| Basic XML parsing | implemented | via external `RdfXmlHandler` package | `RdfParserTest` Unit:107-118, Char:125-135 |
| `xmlns:` namespace declarations | implemented | `PrefixExtractor:121-136` regex + `extractFromXml()` line 141-154 | `PrefixExtractorTest` Unit:48-68, Char:52-71 |
| `rdf:about` attributes | implemented | `ClassExtractor:130-131`, `PropertyExtractor:282-283` | `ClassExtractorTest` Unit:413-437 |
| `rdf:resource` references | implemented | `ClassExtractor:214-220`, `PropertyExtractor:447-456` | `ClassExtractorTest` Unit:509-530 |
| SimpleXML fallback extraction | implemented | `ClassExtractor:118-152`, `PropertyExtractor:262-319` | `ClassExtractorTest` Unit:413-577, `PropertyExtractorTest` Unit:531-688 |
| `rdf:type` attribute pattern (Dublin Core) | implemented | `PropertyExtractor:274-278` XPath for `rdf:type/@rdf:resource` | `PropertyExtractorTest` Char:630-650 |
| `rdf:parseType="Collection"` | not implemented | -- | -- |
| `rdf:parseType="Literal"` | not implemented | -- | -- |
| `rdf:parseType="Resource"` | not implemented | -- | -- |
| `rdf:ID` | not implemented | -- | -- |
| `rdf:nodeID` | not implemented | -- | -- |

### JSON-LD

Reference: [JSON-LD 1.1 W3C Recommendation](https://www.w3.org/TR/json-ld11/)

| Feature | Status | Location | Tests |
|---|---|---|---|
| `@context` parsing (prefix extraction) | implemented | `PrefixExtractor:159-173` with `FILTER_VALIDATE_URL` | `PrefixExtractorTest` Unit:70-81, Char:73-91 |
| Format detection (`{` + `@context`) | implemented | via external `JsonLdHandler` package | `RdfParserTest` Unit:43-45, Char:40-44 |
| Graph extraction via EasyRdf | implemented | via external `JsonLdHandler` package | `RdfParserPipelineTest:109-131` |
| `@graph` arrays | not implemented | depends on JsonLdHandler capabilities | -- |
| Remote context resolution | not implemented | -- | -- |
| JSON-LD Framing | not implemented | -- | -- |

---

## Extractors

### ClassExtractor

Reference: `src/Extractors/ClassExtractor.php` (243 lines)

| Feature | Status | Location | Tests |
|---|---|---|---|
| `rdfs:Class` type detection | implemented | `ClassExtractor:28-31` constant + `findClassResources()` lines 96-111 | `ClassExtractorTest` Unit:17-35 |
| `owl:Class` type detection | implemented | `ClassExtractor:28-31` constant | `ClassExtractorTest` Unit:37-53 |
| Label extraction (single best-match) | implemented | `ClassExtractor:75` via `getResourceLabel()` | `ClassExtractorTest` Unit:114-131 |
| Multilingual labels | implemented | `ClassExtractor:76` via `getAllResourceLabels()` | `ClassExtractorTest` Unit:91-112 |
| Description extraction | implemented | `ClassExtractor:77-78` | `ClassExtractorTest` Unit:133-171 |
| Parent classes (`rdfs:subClassOf`) | implemented | `ClassExtractor:79` | `ClassExtractorTest` Unit:173-225 |
| Blank node filtering | implemented | `ClassExtractor:69` | `ClassExtractorTest` Unit:227-248 |
| Anonymous OWL expression filtering | implemented | `ClassExtractor:69` | `ClassExtractorTest` Unit:250-268 |
| Custom annotations | implemented | `ClassExtractor:83` via `extractCustomAnnotations()` | `ClassExtractorTest` Unit:381-402 |
| XML fallback path (SimpleXML) | implemented | `ClassExtractor:118-152` | `ClassExtractorTest` Unit:413-577 |
| Empty graph handling | implemented | returns `[]` | `ClassExtractorTest` Unit:404-410 |
| Integration with RdfParser | implemented | `RdfParser:134-144` | `ClassExtractorTest` Unit:584-599 |

### PropertyExtractor

Reference: `src/Extractors/PropertyExtractor.php` (473 lines)

| Feature | Status | Location | Tests |
|---|---|---|---|
| `rdf:Property` type detection | implemented | `PropertyExtractor:33` constant | `PropertyExtractorTest` Unit:17-34 |
| `owl:DatatypeProperty` detection | implemented | `PropertyExtractor:34` constant | `PropertyExtractorTest` Unit:36-51 |
| `owl:ObjectProperty` detection | implemented | `PropertyExtractor:35` constant | `PropertyExtractorTest` Unit:53-68 |
| `owl:AnnotationProperty` detection | implemented | `PropertyExtractor:36` constant | `PropertyExtractorTest` Unit:70-85 |
| `owl:FunctionalProperty` detection | implemented | `PropertyExtractor:37` constant + line 75 | `PropertyExtractorTest` Unit:104-138 |
| Property type determination | implemented | `PropertyExtractor:129-142` (`determinePropertyType()`) | `PropertyExtractorTest` Unit:87-101 |
| Domain extraction (incl. `owl:unionOf`) | implemented | `PropertyExtractor:90,153-172` | `PropertyExtractorTest` Unit:143-159, Char:360-386 |
| Range extraction (formal) | implemented | `PropertyExtractor:77` | `PropertyExtractorTest` Unit:161-178 |
| Range-from-comment fallback (6 regex patterns) | implemented | `PropertyExtractor:228-257` | `PropertyExtractorTest` Unit:198-329 |
| RDF list traversal (`rdf:first`/`rdf:rest`/`rdf:nil`) | implemented | `PropertyExtractor:183-207` | `PropertyExtractorTest` Char:360-386 |
| Parent properties (`rdfs:subPropertyOf`) | implemented | `PropertyExtractor:92` | `PropertyExtractorTest` Unit:331-349 |
| Inverse properties (`owl:inverseOf`) | implemented | `PropertyExtractor:93` | `PropertyExtractorTest` Unit:351-367 |
| XML fallback path (SimpleXML) | implemented | `PropertyExtractor:262-319` | `PropertyExtractorTest` Unit:531-688 |
| XML property type from element name | implemented | `PropertyExtractor:321-348` | `PropertyExtractorTest` Unit:601-619, Char:652-677 |
| XML functional property detection | implemented | `PropertyExtractor:350-370` | `PropertyExtractorTest` Unit:622-641, Char:679-704 |
| XML range-from-comment fallback | implemented | `PropertyExtractor:375-392` | `PropertyExtractorTest` Unit:643-664, Char:706-732 |
| Integration with RdfParser | implemented | `RdfParser:147-157` | `PropertyExtractorTest` Unit:696-714 |

### PrefixExtractor

Reference: `src/Extractors/PrefixExtractor.php` (224 lines)

| Feature | Status | Location | Tests |
|---|---|---|---|
| Graph namespace map extraction | implemented | `PrefixExtractor:58-73` via `getNamespaceMap()` | `PrefixExtractorTest` Char:93-108 |
| Turtle `@prefix` regex | implemented | `PrefixExtractor:91-103` | `PrefixExtractorTest` Unit:15-31 |
| SPARQL `PREFIX` regex | implemented | `PrefixExtractor:105-113` | `PrefixExtractorTest` Unit:33-46 |
| RDF/XML `xmlns:` regex | implemented | `PrefixExtractor:121-136` | `PrefixExtractorTest` Unit:48-68 |
| SimpleXML `getNamespaces(true)` | implemented | `PrefixExtractor:141-154` | `PrefixExtractorTest` Char:110-127 |
| JSON-LD `@context` parsing | implemented | `PrefixExtractor:159-173` with `FILTER_VALIDATE_URL` guard | `PrefixExtractorTest` Unit:70-81 |
| Common prefix auto-detection | implemented | `PrefixExtractor:179-221` (11 well-known prefixes) | `PrefixExtractorTest` Unit:83-95, 97-122 |
| Format aliases (`ttl`/`turtle`, `xml`/`rdf/xml`, `jsonld`/`json-ld`) | implemented | `PrefixExtractor:80-85` match expression | `PrefixExtractorTest` Unit:172-184, Char:228-244 |
| Deduplication (last-write-wins via `array_merge`) | implemented | `PrefixExtractor:33-51` | `PrefixExtractorTest` Unit:124-137, Char:180-195 |
| Integration with RdfParser | implemented | `RdfParser:160-163` | `PrefixExtractorTest` Unit:191-201 |

### ShapeExtractor

Reference: `src/Extractors/ShapeExtractor.php` (183 lines)

| Feature | Status | Location | Tests |
|---|---|---|---|
| `sh:NodeShape` detection | implemented | `ShapeExtractor:27-30` constant | `ShapeExtractorTest` Unit:16-36 |
| `sh:PropertyShape` detection | implemented | `ShapeExtractor:27-30` constant | `ShapeExtractorTest` Unit:38-55 |
| Target class (`sh:targetClass`) | implemented | `ShapeExtractor:87` | `ShapeExtractorTest` Unit:78-94, Char:85-100 |
| Target node (`sh:targetNode`) | implemented | `ShapeExtractor:88` | `ShapeExtractorTest` Char:102-117 |
| Target subjects of (`sh:targetSubjectsOf`) | implemented | `ShapeExtractor:89` | `ShapeExtractorTest` Char:119-134 |
| Target objects of (`sh:targetObjectsOf`) | implemented | `ShapeExtractor:90` | `ShapeExtractorTest` Char:136-151 |
| Property shapes via `sh:property` | implemented | `ShapeExtractor:92,121-139` | `ShapeExtractorTest` Unit:96-127 |
| Property shape constraints (12 properties) | implemented | `ShapeExtractor:33-47` constant, `extractConstraints()` lines 168-181 | `ShapeExtractorTest` Unit:173-197, Char:275-307 |
| Path-less property shapes filtered out | implemented | `ShapeExtractor:133` | `ShapeExtractorTest` Unit:129-147 |
| Null values filtered via `array_filter` | implemented | `ShapeExtractor:134` | `ShapeExtractorTest` Unit:149-171 |
| RDF/XML early return (empty array) | implemented | `ShapeExtractor:56-58` | `ShapeExtractorTest` Unit:199-205, Char:309-323 |
| `RdfNamespace::set()` side effect for `sh` and `dct` | implemented | `ShapeExtractor:70-71` | `ShapeExtractorTest` Unit:270-287, Char:325-346 |
| Integration with RdfParser | implemented | `RdfParser:165-176` | `ShapeExtractorTest` Unit:366-380 |

---

## RdfParser Orchestration

Reference: `src/RdfParser.php` (225 lines)

| Feature | Status | Location | Tests |
|---|---|---|---|
| `OntologyParserInterface` implementation | implemented | `RdfParser:31` | `RdfParserTest` Unit:21-23 |
| Non-final class (extensible by OwlParser, ShaclParser) | implemented | `RdfParser:31` (no `final` keyword) | `RdfParserTest` Unit:26-28 |
| Default handler registration (4 handlers) | implemented | `RdfParser:184-192` order: JsonLd, Turtle, NTriples, RdfXml | `RdfParserTest` Unit:70-76 |
| Handler priority (first match wins) | implemented | `RdfParser:214-218` iterates handlers in order | `RdfParserTest` Unit:219-231 |
| Custom handler registration (`registerHandler()`) | implemented | `RdfParser:103-106` prepends via `array_unshift` | `RdfParserTest` Unit:233-306 |
| Auto-detection via `canHandle()` | implemented | `RdfParser:214-218` | `RdfParserTest` Unit:99-132, Char:113-156 |
| Explicit format selection via `options['format']` | implemented | `RdfParser:199-211` | `RdfParserTest` Unit:148-152, Char:167-171 |
| `canParse()` (never throws) | implemented | `RdfParser:79-92` with `try/catch(\Throwable)` | `RdfParserTest` Unit:61-64, Char:56-61 |
| `getSupportedFormats()` (dynamic from handlers) | implemented | `RdfParser:94-101` via `array_map` on handlers | `RdfParserTest` Unit:68-95, Char:74-96 |
| `buildParsedOntology()` (protected, overridable) | implemented | `RdfParser:108-119` | `RdfParserTest` Unit:186-214 |
| `buildMetadata()` adds `format` + `resource_count` | implemented | `RdfParser:124-131` | `RdfParserTest` Unit:134-140, Char:158-165 |
| `extractRestrictions()` returns empty array (base) | implemented | `RdfParser:179-182` | `RdfParserTest` Char:306-313 |
| Raw content preservation | implemented | `RdfParser:117` passes original content to `ParsedOntology` | `RdfParserTest` Unit:142-146, Char:185-189 |

---

## Format Detection (`canParse`)

| Content Pattern | Detected As | Location | Tests |
|---|---|---|---|
| `{"@context": ...}` JSON with `@context` | JSON-LD | via `JsonLdHandler::canHandle()` | `RdfParserTest` Unit:43-45, Char:40-44 |
| `@prefix ...` or `PREFIX ...` | Turtle | via `TurtleHandler::canHandle()` | `RdfParserTest` Unit:33-35, Char:20-26 |
| `<uri> <pred> <obj/lit> .` line pattern | N-Triples | `NTriplesHandler:20-41` regex on first 10 lines | `RdfParserTest` Unit:48-50, Char:63-65 |
| `<?xml ...` or `<rdf:RDF ...` | RDF/XML | via `RdfXmlHandler::canHandle()` | `RdfParserTest` Unit:38-41, Char:30-37 |
| Plain text / HTML / empty | Not recognized | returns `false` / throws `FormatDetectionException` | `RdfParserTest` Unit:53-64, Char:45-61 |

---

## Error Handling

| Scenario | Exception Type | Location | Tests |
|---|---|---|---|
| Empty / whitespace-only content | `ParseException` "Cannot parse empty content" | `RdfParser:59-61` | `RdfParserTest` Unit:160-167 |
| No handler matches content | `FormatDetectionException` | `RdfParser:220-224` | `RdfParserTest` Unit:170-173, Char:179-183 |
| Unknown explicit format requested | `FormatDetectionException` | `RdfParser:208-212` | `RdfParserTest` Unit:154-158, Char:173-177 |
| Handler parse failure | `ParseException` wrapping original with `$previous` | `RdfParser:70-76` | `RdfParserTest` Unit:175-184 |
| N-Triples invalid syntax | `ParseException` "N-Triples parsing failed: ..." | `NTriplesHandler:62` | `NTriplesHandlerTest` Unit:169-183 |

---

## Backward Compatibility (Aliases)

Reference: `aliases.php` (31 lines)

| Feature | Status | Location | Tests |
|---|---|---|---|
| `App\...\RdfParser` -> new namespace alias | implemented | `aliases.php:13-30` via `spl_autoload_register` | `AliasesTest:10-12` |
| `E_USER_DEPRECATED` on old namespace use | implemented | `aliases.php:20-26` | `AliasesTest:63-75` |
| No deprecation at autoload time | implemented | alias triggers only on old class reference | `AliasesTest:79-104` |
| No aliases for internal classes (extractors, handlers) | implemented | only `RdfParser` is aliased | `AliasesTest:107-123` |
| v2.0 removal notice in message | implemented | `aliases.php:24` | `AliasesTest:74-75` |

---

## W3C N-Triples Conformance

Reference: [W3C RDF 1.1 N-Triples Test Suite](https://w3c.github.io/rdf-tests/rdf/rdf11/rdf-n-triples/)

Test file: `tests/Conformance/W3cNTriplesConformanceTest.php` with 70 W3C test fixtures in `tests/Fixtures/W3c/NTriples/`.

| Category | Pass | Skip | Fail | Total |
|---|---|---|---|---|
| Positive Syntax | 40 | 1 | 0 | 41 |
| Negative Syntax | 29 | 0 | 0 | 29 |
| **Total** | **69** | **1** | **0** | **70** |

### Positive Syntax Skips (1)

| Test ID | Reason |
|---|---|
| `minimal_whitespace` | EasyRdf 1.1.1 requires whitespace between triple components |

Note: `comment_following_triple` now passes thanks to inline comment stripping in Story 12.4.

### Negative Syntax — All 29 Pass

Story 12.4 added pre-parse validation that catches all previously-skipped negative tests:
- 9 bad URI tests (whitespace, invalid escapes, relative IRIs)
- 2 bad blank node label tests (colons in labels)
- 2 bad structure tests (object lists, predicate-object lists)
- 1 bad language tag test (digit-starting tag)
- 3 bad escape tests (invalid escape sequences)
- 1 bad string test (triple-quoted strings)

---

## Test Coverage Summary

439 total test cases across 4 test suites and 18 test files.

### By Suite

| Suite | Test Count | Description |
|---|---|---|
| Unit | 202 | Fine-grained tests of each class in new namespace |
| Characterization | 146 | Behavioral characterization tests documenting existing behavior |
| Conformance | 70 | W3C N-Triples official test suite (50 pass, 20 skip) |
| Integration | 18 | Full-pipeline tests with fixture files across all 4 formats |
| **Total** | **406** | |

### By Component

| Component | Unit | Char | Integration | Total |
|---|---|---|---|---|
| `RdfParser` | 33 | 30 | 18 | 81 |
| `NTriplesHandler` | 26 | 25 | -- | 51 |
| `ClassExtractor` | 29 | 21 | -- | 50 |
| `PropertyExtractor` | 37 | 34 | -- | 71 |
| `PrefixExtractor` | 14 | 15 | -- | 29 |
| `ShapeExtractor` | 20 | 21 | -- | 41 |
| Aliases | 10 | -- | -- | 10 |
| W3C Conformance | -- | -- | 70 | 70 |

### Test File Inventory

| File | Path | Count |
|---|---|---|
| `Unit/RdfParserTest.php` | `tests/Unit/RdfParserTest.php` | 33 |
| `Unit/Handlers/NTriplesHandlerTest.php` | `tests/Unit/Handlers/NTriplesHandlerTest.php` | 26 |
| `Unit/Extractors/ClassExtractorTest.php` | `tests/Unit/Extractors/ClassExtractorTest.php` | 29 |
| `Unit/Extractors/PrefixExtractorTest.php` | `tests/Unit/Extractors/PrefixExtractorTest.php` | 14 |
| `Unit/Extractors/PropertyExtractorTest.php` | `tests/Unit/Extractors/PropertyExtractorTest.php` | 37 |
| `Unit/Extractors/ShapeExtractorTest.php` | `tests/Unit/Extractors/ShapeExtractorTest.php` | 20 |
| `Unit/BlankNodeSkolemizationTest.php` | `tests/Unit/BlankNodeSkolemizationTest.php` | 8 |
| `Unit/NTriplesStrictValidationTest.php` | `tests/Unit/NTriplesStrictValidationTest.php` | 25 |
| `Unit/AliasesTest.php` | `tests/Unit/AliasesTest.php` | 10 |
| `Characterization/RdfParserTest.php` | `tests/Characterization/RdfParserTest.php` | 30 |
| `Characterization/NTriplesHandlerTest.php` | `tests/Characterization/NTriplesHandlerTest.php` | 25 |
| `Characterization/ClassExtractorTest.php` | `tests/Characterization/ClassExtractorTest.php` | 21 |
| `Characterization/PrefixExtractorTest.php` | `tests/Characterization/PrefixExtractorTest.php` | 15 |
| `Characterization/PropertyExtractorTest.php` | `tests/Characterization/PropertyExtractorTest.php` | 34 |
| `Characterization/ShapeExtractorTest.php` | `tests/Characterization/ShapeExtractorTest.php` | 21 |
| `Conformance/W3cNTriplesConformanceTest.php` | `tests/Conformance/W3cNTriplesConformanceTest.php` | 70 |
| `Integration/RdfParserPipelineTest.php` | `tests/Integration/RdfParserPipelineTest.php` | 18 |

---

## Architecture Notes

The implementation follows a **handler-extractor pattern**:

- **4 format handlers** parse raw content into `ParsedRdf` value objects. `NTriplesHandler` is the only handler in this package; `TurtleHandler`, `RdfXmlHandler`, and `JsonLdHandler` come from separate Composer packages (`parser-turtle`, `parser-rdfxml`, `parser-jsonld`).
- **4 extractors** pull semantic entities from the parsed graph: `ClassExtractor`, `PropertyExtractor`, `PrefixExtractor`, `ShapeExtractor`.
- **`RdfParser`** orchestrates handler selection and extractor invocation, producing a `ParsedOntology` value object.

Key design decisions:

1. **Handler priority**: JSON-LD is checked first (line 187), then Turtle (line 188), then N-Triples (line 189), then RDF/XML (line 190). This prevents ambiguous content from being misdetected.
2. **Custom handler prepending**: `registerHandler()` uses `array_unshift` (line 105) so custom handlers are checked before defaults.
3. **Dual extraction paths**: `ClassExtractor` and `PropertyExtractor` support both an EasyRdf Graph path and a SimpleXML fallback path for RDF/XML, triggered when `xml_element` is present in metadata.
4. **EasyRdf dependency**: Heavy reliance on EasyRdf for format parsing means spec compliance at the serialization level depends on EasyRdf's own conformance (see W3C conformance skips).
5. **Extensibility**: `RdfParser` is deliberately not `final` -- `OwlParser` and `ShaclParser` extend it, overriding `buildParsedOntology()` and adding extraction logic.
6. **No reasoning engine**: The parser extracts declared structure only; inferred triples are out of scope.

---

## Remaining Gaps — Categorized by Responsibility

### Completed (Epic 12)

| Gap | Story | Status |
|---|---|---|
| Named Graphs / RDF Datasets | 12.1 | Done |
| RDFS vocabulary completeness | 12.2 | Done |
| Blank node skolemization | 12.3 | Done |
| N-Triples strict validation | 12.4 | Done |

### (a) Parser-RDF Orchestration — No Remaining Gaps

All parser-rdf orchestration features are complete:
- Extractors (class, property, prefix, shape): 100%
- RdfParser orchestration: 100%
- Error handling: 100%
- RDFS vocabulary: 100%
- N-Triples handler + validation: 100%
- Named graphs / RDF datasets: 100%
- Blank node skolemization (opt-in): 100%

**Parser-rdf orchestration: 100% complete.**

### (b) Handler-Delegated — Format-Specific Packages

These gaps are in the serialization layer, which is the responsibility of external handler packages:

| Gap | Current | Target Package | Epic/Story |
|---|---|---|---|
| Turtle: `@base` / `BASE` directives | not impl. | `parser-turtle` | Epic 9, Story 9-1 |
| Turtle: string escape sequences | partial (EasyRdf) | `parser-turtle` | Epic 9 |
| RDF/XML: `rdf:parseType="Collection"` | not impl. | `parser-rdfxml` | Epic 10, Story 10-3 |
| RDF/XML: `rdf:parseType="Literal"` | not impl. | `parser-rdfxml` | Epic 10, Story 10-3 |
| RDF/XML: `rdf:parseType="Resource"` | not impl. | `parser-rdfxml` | Epic 10, Story 10-3 |
| RDF/XML: `rdf:ID` | not impl. | `parser-rdfxml` | Epic 10, Story 10-4 |
| RDF/XML: `rdf:nodeID` | not impl. | `parser-rdfxml` | Epic 10, Story 10-4 |
| JSON-LD: `@graph` arrays | not impl. | `parser-jsonld` | Epic 11, Story 11-4 |
| JSON-LD: remote context resolution | not impl. | `parser-jsonld` | Epic 11, Story 11-5 |
| JSON-LD: JSON-LD Framing | not impl. | `parser-jsonld` | Epic 11 |

**Including handler coverage: ~89% complete** (handler gaps reduce the full-stack percentage).

### (c) EasyRdf-Delegated — Upstream Library Limitations

These limitations exist at the EasyRdf library level and cannot be addressed in parser-rdf or handler packages without replacing or patching EasyRdf:

| Limitation | Impact | W3C Tests Affected |
|---|---|---|
| **IRI validation** — EasyRdf does not enforce RFC 3987 | Permissive: accepts IRIs that a strict validator would reject | Mitigated by pre-parse validation in N-Triples handler (Story 12.4) |
| **Lexical form normalization** — literal values stored as-is | `+1` and `1` are not canonicalized to the same form | None (normalization is optional per W3C spec) |
| **Minimal whitespace** — EasyRdf requires whitespace between N-Triples components | Valid N-Triples without spaces between terms fails to parse | 1 W3C N-Triples test skipped (`minimal_whitespace`) |

### W3C Conformance

| Format | Pass | Skip | Fail | Total | Coverage |
|---|---|---|---|---|---|
| N-Triples | 69 | 1 | 0 | 70 | 99% |
| Turtle | — | — | — | — | Not yet integrated (Epic 9) |
| RDF/XML | — | — | — | — | Not yet integrated (Epic 10) |
| JSON-LD | — | — | — | — | Not yet integrated (Epic 11) |

---

## Out of Scope

The following are intentionally **not** covered by this library and belong in separate repositories:

| Area | Target Repository |
|---|---|
| OWL (Web Ontology Language) | `parser-owl` |
| SHACL (Shapes Constraint Language) | `parser-shacl` |
| Turtle format handler | `parser-turtle` |
| RDF/XML format handler | `parser-rdfxml` |
| JSON-LD format handler | `parser-jsonld` |
| Core contracts and value objects | `parser-core` |
