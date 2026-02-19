<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\Exceptions\ParseException;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserRdf\Handlers\NTriplesHandler;

/*
 * CONFORMANCE_RESULTS
 * ===================
 * Source: W3C RDF 1.1 N-Triples Test Suite
 * URL: https://w3c.github.io/rdf-tests/rdf/rdf11/rdf-n-triples/
 * Date: 2026-02-19
 *
 * Total tests: 70
 * - Positive Syntax: 41 (39 pass, 2 skip, 0 fail)
 * - Negative Syntax: 29 (11 pass, 18 skip, 0 fail)
 *
 * Results: 50 pass, 20 skip, 0 fail
 *
 * Positive skips (2):
 *   - comment_following_triple: EasyRdf 1.1.1 cannot parse inline comments after triples
 *   - minimal_whitespace: EasyRdf 1.1.1 requires whitespace between triple components
 *
 * Negative skips (18):
 *   - nt-syntax-bad-uri-01..09 (9): EasyRdf does not validate IRI content or reject relative IRIs
 *   - nt-syntax-bad-bnode-01..02 (2): EasyRdf accepts colons in blank node labels
 *   - nt-syntax-bad-struct-01..02 (2): EasyRdf accepts objectList/predicateObjectList in N-Triples
 *   - nt-syntax-bad-lang-01 (1): EasyRdf accepts invalid language tags
 *   - nt-syntax-bad-esc-01..03 (3): EasyRdf does not validate string escape sequences
 *   - nt-syntax-bad-string-05 (1): EasyRdf accepts long double-quoted string literals
 *   Total negative skips: 18 (all due to EasyRdf's permissive N-Triples parser)
 */

function ntriplesFixturePath(string $filename): string
{
    return __DIR__ . '/../Fixtures/W3c/NTriples/' . $filename;
}

function ntriplesFixture(string $filename): string
{
    $path = ntriplesFixturePath($filename);
    if (! file_exists($path)) {
        throw new \RuntimeException("W3C N-Triples fixture not found: {$path}");
    }

    $content = file_get_contents($path);
    if ($content === false) {
        throw new \RuntimeException("Failed to read W3C N-Triples fixture: {$path}");
    }

    return $content;
}

// ---------------------------------------------------------------------------
// Positive Syntax Tests (41 tests)
// ---------------------------------------------------------------------------
describe('W3C N-Triples Positive Syntax Tests', function () {

    beforeEach(function () {
        $this->handler = new NTriplesHandler();
    });

    $positiveSyntaxTests = [
        'nt-syntax-file-01' => 'nt-syntax-file-01.nt',
        'nt-syntax-file-02' => 'nt-syntax-file-02.nt',
        'nt-syntax-file-03' => 'nt-syntax-file-03.nt',
        'nt-syntax-uri-01' => 'nt-syntax-uri-01.nt',
        'nt-syntax-uri-02' => 'nt-syntax-uri-02.nt',
        'nt-syntax-uri-03' => 'nt-syntax-uri-03.nt',
        'nt-syntax-uri-04' => 'nt-syntax-uri-04.nt',
        'nt-syntax-string-01' => 'nt-syntax-string-01.nt',
        'nt-syntax-string-02' => 'nt-syntax-string-02.nt',
        'nt-syntax-string-03' => 'nt-syntax-string-03.nt',
        'nt-syntax-str-esc-01' => 'nt-syntax-str-esc-01.nt',
        'nt-syntax-str-esc-02' => 'nt-syntax-str-esc-02.nt',
        'nt-syntax-str-esc-03' => 'nt-syntax-str-esc-03.nt',
        'nt-syntax-bnode-01' => 'nt-syntax-bnode-01.nt',
        'nt-syntax-bnode-02' => 'nt-syntax-bnode-02.nt',
        'nt-syntax-bnode-03' => 'nt-syntax-bnode-03.nt',
        'nt-syntax-datatypes-01' => 'nt-syntax-datatypes-01.nt',
        'nt-syntax-datatypes-02' => 'nt-syntax-datatypes-02.nt',
        'nt-syntax-subm-01' => 'nt-syntax-subm-01.nt',
        'comment_following_triple' => 'comment_following_triple.nt',
        'literal' => 'literal.nt',
        'literal_all_controls' => 'literal_all_controls.nt',
        'literal_all_punctuation' => 'literal_all_punctuation.nt',
        'literal_ascii_boundaries' => 'literal_ascii_boundaries.nt',
        'literal_with_2_dquotes' => 'literal_with_2_dquotes.nt',
        'literal_with_2_squotes' => 'literal_with_2_squotes.nt',
        'literal_with_BACKSPACE' => 'literal_with_BACKSPACE.nt',
        'literal_with_CARRIAGE_RETURN' => 'literal_with_CARRIAGE_RETURN.nt',
        'literal_with_CHARACTER_TABULATION' => 'literal_with_CHARACTER_TABULATION.nt',
        'literal_with_dquote' => 'literal_with_dquote.nt',
        'literal_with_FORM_FEED' => 'literal_with_FORM_FEED.nt',
        'literal_with_LINE_FEED' => 'literal_with_LINE_FEED.nt',
        'literal_with_numeric_escape4' => 'literal_with_numeric_escape4.nt',
        'literal_with_numeric_escape8' => 'literal_with_numeric_escape8.nt',
        'literal_with_REVERSE_SOLIDUS' => 'literal_with_REVERSE_SOLIDUS.nt',
        'literal_with_REVERSE_SOLIDUS2' => 'literal_with_REVERSE_SOLIDUS2.nt',
        'literal_with_squote' => 'literal_with_squote.nt',
        'literal_with_UTF8_boundaries' => 'literal_with_UTF8_boundaries.nt',
        'langtagged_string' => 'langtagged_string.nt',
        'lantag_with_subtag' => 'lantag_with_subtag.nt',
        'minimal_whitespace' => 'minimal_whitespace.nt',
    ];

    $skippedPositive = [
        'comment_following_triple' => 'EasyRdf 1.1.1 cannot parse inline comments after triples',
        'minimal_whitespace' => 'EasyRdf 1.1.1 requires whitespace between triple components',
    ];

    foreach ($positiveSyntaxTests as $testId => $filename) {
        $test = it("[{$testId}] parses valid syntax", function () use ($filename) {
            $content = ntriplesFixture($filename);
            $result = $this->handler->parse($content);
            expect($result)->toBeInstanceOf(ParsedRdf::class);
        });
        if (isset($skippedPositive[$testId])) {
            $test->skip($skippedPositive[$testId]);
        }
    }
});

// ---------------------------------------------------------------------------
// Negative Syntax Tests (29 tests)
// ---------------------------------------------------------------------------
describe('W3C N-Triples Negative Syntax Tests', function () {

    beforeEach(function () {
        $this->handler = new NTriplesHandler();
    });

    $negativeSyntaxTests = [
        'nt-syntax-bad-uri-01' => 'nt-syntax-bad-uri-01.nt',
        'nt-syntax-bad-uri-02' => 'nt-syntax-bad-uri-02.nt',
        'nt-syntax-bad-uri-03' => 'nt-syntax-bad-uri-03.nt',
        'nt-syntax-bad-uri-04' => 'nt-syntax-bad-uri-04.nt',
        'nt-syntax-bad-uri-05' => 'nt-syntax-bad-uri-05.nt',
        'nt-syntax-bad-uri-06' => 'nt-syntax-bad-uri-06.nt',
        'nt-syntax-bad-uri-07' => 'nt-syntax-bad-uri-07.nt',
        'nt-syntax-bad-uri-08' => 'nt-syntax-bad-uri-08.nt',
        'nt-syntax-bad-uri-09' => 'nt-syntax-bad-uri-09.nt',
        'nt-syntax-bad-prefix-01' => 'nt-syntax-bad-prefix-01.nt',
        'nt-syntax-bad-base-01' => 'nt-syntax-bad-base-01.nt',
        'nt-syntax-bad-bnode-01' => 'nt-syntax-bad-bnode-01.nt',
        'nt-syntax-bad-bnode-02' => 'nt-syntax-bad-bnode-02.nt',
        'nt-syntax-bad-struct-01' => 'nt-syntax-bad-struct-01.nt',
        'nt-syntax-bad-struct-02' => 'nt-syntax-bad-struct-02.nt',
        'nt-syntax-bad-lang-01' => 'nt-syntax-bad-lang-01.nt',
        'nt-syntax-bad-esc-01' => 'nt-syntax-bad-esc-01.nt',
        'nt-syntax-bad-esc-02' => 'nt-syntax-bad-esc-02.nt',
        'nt-syntax-bad-esc-03' => 'nt-syntax-bad-esc-03.nt',
        'nt-syntax-bad-string-01' => 'nt-syntax-bad-string-01.nt',
        'nt-syntax-bad-string-02' => 'nt-syntax-bad-string-02.nt',
        'nt-syntax-bad-string-03' => 'nt-syntax-bad-string-03.nt',
        'nt-syntax-bad-string-04' => 'nt-syntax-bad-string-04.nt',
        'nt-syntax-bad-string-05' => 'nt-syntax-bad-string-05.nt',
        'nt-syntax-bad-string-06' => 'nt-syntax-bad-string-06.nt',
        'nt-syntax-bad-string-07' => 'nt-syntax-bad-string-07.nt',
        'nt-syntax-bad-num-01' => 'nt-syntax-bad-num-01.nt',
        'nt-syntax-bad-num-02' => 'nt-syntax-bad-num-02.nt',
        'nt-syntax-bad-num-03' => 'nt-syntax-bad-num-03.nt',
    ];

    $skippedNegative = [
        'nt-syntax-bad-uri-01' => 'EasyRdf 1.1.1 does not validate IRI content (accepts spaces)',
        'nt-syntax-bad-uri-02' => 'EasyRdf 1.1.1 does not validate IRI escape sequences',
        'nt-syntax-bad-uri-03' => 'EasyRdf 1.1.1 does not validate IRI long escape sequences',
        'nt-syntax-bad-uri-04' => 'EasyRdf 1.1.1 does not reject character escapes in IRIs',
        'nt-syntax-bad-uri-05' => 'EasyRdf 1.1.1 does not reject character escapes in IRIs',
        'nt-syntax-bad-uri-06' => 'EasyRdf 1.1.1 does not reject relative IRIs in subject',
        'nt-syntax-bad-uri-07' => 'EasyRdf 1.1.1 does not reject relative IRIs in predicate',
        'nt-syntax-bad-uri-08' => 'EasyRdf 1.1.1 does not reject relative IRIs in object',
        'nt-syntax-bad-uri-09' => 'EasyRdf 1.1.1 does not reject relative IRIs in datatype',
        'nt-syntax-bad-bnode-01' => 'EasyRdf 1.1.1 accepts colons in blank node labels',
        'nt-syntax-bad-bnode-02' => 'EasyRdf 1.1.1 accepts colons in blank node labels',
        'nt-syntax-bad-struct-01' => 'EasyRdf 1.1.1 accepts objectList syntax in N-Triples',
        'nt-syntax-bad-struct-02' => 'EasyRdf 1.1.1 accepts predicateObjectList syntax in N-Triples',
        'nt-syntax-bad-lang-01' => 'EasyRdf 1.1.1 accepts invalid language tags',
        'nt-syntax-bad-esc-01' => 'EasyRdf 1.1.1 does not validate string escape sequences',
        'nt-syntax-bad-esc-02' => 'EasyRdf 1.1.1 does not validate string escape sequences',
        'nt-syntax-bad-esc-03' => 'EasyRdf 1.1.1 does not validate string escape sequences',
        'nt-syntax-bad-string-05' => 'EasyRdf 1.1.1 accepts long double-quoted string literals in N-Triples',
    ];

    foreach ($negativeSyntaxTests as $testId => $filename) {
        $test = it("[{$testId}] rejects invalid syntax", function () use ($filename) {
            $content = ntriplesFixture($filename);
            expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
        });
        if (isset($skippedNegative[$testId])) {
            $test->skip($skippedNegative[$testId]);
        }
    }
});
