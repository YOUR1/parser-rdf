<?php

declare(strict_types=1);

namespace Youri\vandenBogert\Software\ParserRdf\Handlers;

use EasyRdf\Graph;
use Youri\vandenBogert\Software\ParserCore\Contracts\RdfFormatHandlerInterface;
use Youri\vandenBogert\Software\ParserCore\Exceptions\ParseException;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;

/**
 * Handler for N-Triples format parsing with strict W3C validation.
 *
 * N-Triples is a line-based format where each line contains a complete triple.
 * Pre-parse validation catches structural issues that EasyRdf might silently accept.
 */
final class NTriplesHandler implements RdfFormatHandlerInterface
{
    private const MAX_LINE_LENGTH = 1_048_576; // 1MB

    public function canHandle(string $content): bool
    {
        $trimmed = trim($content);

        $lines = explode("\n", $trimmed);
        $validLines = 0;

        foreach (array_slice($lines, 0, 10) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Support IRI subjects (<...>) and blank node subjects (_:...)
            // Allow optional trailing comments and minimal whitespace
            if (preg_match('/^(?:<[^>]+>|_:\S+)\s*<[^>]+>\s*.+\s*\.\s*(?:#.*)?$/', $line)) {
                $validLines++;
            }
        }

        return $validLines > 0;
    }

    public function parse(string $content): ParsedRdf
    {
        $this->validateContent($content);
        $cleanedContent = $this->stripInlineComments($content);

        try {
            $graph = new Graph();
            $graph->parse($cleanedContent, 'ntriples');

            $metadata = [
                'parser' => 'ntriples_handler',
                'format' => 'n-triples',
                'resource_count' => count($graph->resources()),
            ];

            return new ParsedRdf(
                graph: $graph,
                format: 'n-triples',
                rawContent: $content,
                metadata: $metadata,
            );
        } catch (\Throwable $e) {
            throw new ParseException('N-Triples parsing failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getFormatName(): string
    {
        return 'n-triples';
    }

    /**
     * Strip inline comments from N-Triples content before passing to EasyRdf.
     *
     * EasyRdf does not support inline comments after triples, so we strip them.
     */
    private function stripInlineComments(string $content): string
    {
        $lines = explode("\n", $content);
        $cleaned = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                $cleaned[] = $line;

                continue;
            }

            $cleaned[] = $this->stripTrailingComment($trimmed);
        }

        return implode("\n", $cleaned);
    }

    /**
     * Pre-parse validation of N-Triples content per W3C specification.
     *
     * @throws ParseException
     */
    private function validateContent(string $content): void
    {
        $lines = explode("\n", $content);

        foreach ($lines as $index => $line) {
            $lineNum = $index + 1;
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (\strlen($line) > self::MAX_LINE_LENGTH) {
                throw new ParseException("N-Triples validation failed on line {$lineNum}: line exceeds maximum length");
            }

            $this->validateLine($trimmed, $lineNum);
        }
    }

    /**
     * @throws ParseException
     */
    private function validateLine(string $line, int $lineNum): void
    {
        // Strip trailing comment (only outside string literals)
        $line = $this->stripTrailingComment($line);

        $this->validateNoTripleQuotedStrings($line, $lineNum);
        $this->validateIRIs($line, $lineNum);
        $this->validateBlankNodes($line, $lineNum);
        $this->validateStringEscapes($line, $lineNum);
        $this->validateLanguageTags($line, $lineNum);
        $this->validateStructure($line, $lineNum);
    }

    private function stripTrailingComment(string $line): string
    {
        // Find the terminal dot, then strip any trailing comment
        // We need to be careful about dots inside string literals
        $inString = false;
        $escaped = false;
        $lastDotPos = null;

        for ($i = 0, $len = \strlen($line); $i < $len; $i++) {
            $char = $line[$i];

            if ($escaped) {
                $escaped = false;

                continue;
            }

            if ($char === '\\' && $inString) {
                $escaped = true;

                continue;
            }

            if ($char === '"') {
                $inString = ! $inString;

                continue;
            }

            if ($char === '.' && ! $inString) {
                $lastDotPos = $i;
            }
        }

        if ($lastDotPos !== null) {
            $afterDot = trim(substr($line, $lastDotPos + 1));
            if ($afterDot === '' || str_starts_with($afterDot, '#')) {
                return trim(substr($line, 0, $lastDotPos + 1));
            }
        }

        return $line;
    }

    /**
     * @throws ParseException
     */
    private function validateNoTripleQuotedStrings(string $line, int $lineNum): void
    {
        if (str_contains($line, '"""')) {
            throw new ParseException("N-Triples validation failed on line {$lineNum}: triple-quoted strings are not allowed in N-Triples");
        }
    }

    /**
     * @throws ParseException
     */
    private function validateIRIs(string $line, int $lineNum): void
    {
        foreach ($this->extractIRIs($line) as $iri) {
            // Check for spaces
            if (preg_match('/\s/', $iri)) {
                throw new ParseException("N-Triples validation failed on line {$lineNum}: IRI contains whitespace");
            }

            // Check for relative IRIs (must contain a scheme: scheme://...)
            if (! preg_match('/^[a-zA-Z][a-zA-Z0-9+.\-]*:/', $iri)) {
                throw new ParseException("N-Triples validation failed on line {$lineNum}: relative IRIs are not allowed in N-Triples");
            }

            // Validate escape sequences in IRIs (only \uXXXX and \UXXXXXXXX allowed)
            $this->validateIRIEscapes($iri, $lineNum);
        }
    }

    /**
     * Extract IRI contents from <...> brackets, excluding those inside string literals.
     *
     * @return list<string>
     */
    private function extractIRIs(string $line): array
    {
        $iris = [];
        $inString = false;
        $escaped = false;
        $inIRI = false;
        $iriStart = 0;

        for ($i = 0, $len = \strlen($line); $i < $len; $i++) {
            $char = $line[$i];

            if ($escaped) {
                $escaped = false;

                continue;
            }

            if ($char === '\\' && $inString) {
                $escaped = true;

                continue;
            }

            if ($char === '"' && ! $inIRI) {
                $inString = ! $inString;

                continue;
            }

            if (! $inString) {
                if ($char === '<') {
                    $inIRI = true;
                    $iriStart = $i + 1;

                    continue;
                }

                if ($char === '>' && $inIRI) {
                    $iris[] = substr($line, $iriStart, $i - $iriStart);
                    $inIRI = false;

                    continue;
                }
            }
        }

        return $iris;
    }

    /**
     * @throws ParseException
     */
    private function validateIRIEscapes(string $iri, int $lineNum): void
    {
        $offset = 0;
        while (($pos = strpos($iri, '\\', $offset)) !== false) {
            $nextChar = $iri[$pos + 1] ?? '';

            if ($nextChar === 'u') {
                $hex = substr($iri, $pos + 2, 4);
                if (\strlen($hex) < 4 || ! ctype_xdigit($hex)) {
                    throw new ParseException("N-Triples validation failed on line {$lineNum}: invalid \\u escape in IRI");
                }
                $offset = $pos + 6;
            } elseif ($nextChar === 'U') {
                $hex = substr($iri, $pos + 2, 8);
                if (\strlen($hex) < 8 || ! ctype_xdigit($hex)) {
                    throw new ParseException("N-Triples validation failed on line {$lineNum}: invalid \\U escape in IRI");
                }
                $offset = $pos + 10;
            } else {
                throw new ParseException("N-Triples validation failed on line {$lineNum}: only \\u and \\U escapes are allowed in IRIs");
            }
        }
    }

    /**
     * @throws ParseException
     */
    private function validateBlankNodes(string $line, int $lineNum): void
    {
        // Match blank node labels: _: followed by PN_CHARS-compatible characters
        // Stop at whitespace, <, >, ., ;, , (delimiters in N-Triples)
        if (preg_match_all('/_:([^\s<>.;,]+)/', $line, $matches)) {
            foreach ($matches[1] as $label) {
                // Blank node label must start with PN_CHARS_U or digit
                if (! preg_match('/^[a-zA-Z0-9_]/', $label)) {
                    throw new ParseException("N-Triples validation failed on line {$lineNum}: invalid blank node label");
                }

                // Blank node label must not contain colons
                if (str_contains($label, ':')) {
                    throw new ParseException("N-Triples validation failed on line {$lineNum}: blank node label must not contain ':'");
                }
            }
        }
    }

    /**
     * @throws ParseException
     */
    private function validateStringEscapes(string $line, int $lineNum): void
    {
        // Extract string literals (content between non-escaped double quotes)
        if (! preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"/s', $line, $matches)) {
            return;
        }

        $validEscapeChars = ['t', 'b', 'n', 'r', 'f', '"', '\\'];

        foreach ($matches[1] as $literal) {
            $offset = 0;
            while (($pos = strpos($literal, '\\', $offset)) !== false) {
                $nextChar = $literal[$pos + 1] ?? '';

                if (\in_array($nextChar, $validEscapeChars, true)) {
                    $offset = $pos + 2;
                } elseif ($nextChar === 'u') {
                    $hex = substr($literal, $pos + 2, 4);
                    if (\strlen($hex) < 4 || ! ctype_xdigit($hex)) {
                        throw new ParseException("N-Triples validation failed on line {$lineNum}: invalid \\u escape in string literal");
                    }
                    $offset = $pos + 6;
                } elseif ($nextChar === 'U') {
                    $hex = substr($literal, $pos + 2, 8);
                    if (\strlen($hex) < 8 || ! ctype_xdigit($hex)) {
                        throw new ParseException("N-Triples validation failed on line {$lineNum}: invalid \\U escape in string literal");
                    }
                    $offset = $pos + 10;
                } else {
                    throw new ParseException("N-Triples validation failed on line {$lineNum}: invalid escape sequence '\\{$nextChar}' in string literal");
                }
            }
        }
    }

    /**
     * @throws ParseException
     */
    private function validateLanguageTags(string $line, int $lineNum): void
    {
        // Match language tags: "string"@tag (before optional ^^ or whitespace)
        if (preg_match_all('/"(?:[^"\\\\]|\\\\.)*"@([^\s.^]+)/', $line, $matches)) {
            foreach ($matches[1] as $tag) {
                if (! preg_match('/^[a-zA-Z]+(-[a-zA-Z0-9]+)*$/', $tag)) {
                    throw new ParseException("N-Triples validation failed on line {$lineNum}: invalid language tag '{$tag}'");
                }
            }
        }
    }

    /**
     * @throws ParseException
     */
    private function validateStructure(string $line, int $lineNum): void
    {
        // Check for Turtle-only syntax: semicolons and commas outside string literals and IRIs
        $inString = false;
        $inIRI = false;
        $escaped = false;

        for ($i = 0, $len = \strlen($line); $i < $len; $i++) {
            $char = $line[$i];

            if ($escaped) {
                $escaped = false;

                continue;
            }

            if ($char === '\\' && $inString) {
                $escaped = true;

                continue;
            }

            if ($char === '"' && ! $inIRI) {
                $inString = ! $inString;

                continue;
            }

            if (! $inString) {
                if ($char === '<') {
                    $inIRI = true;

                    continue;
                }

                if ($char === '>' && $inIRI) {
                    $inIRI = false;

                    continue;
                }
            }

            if (! $inString && ! $inIRI) {
                if ($char === ';') {
                    throw new ParseException("N-Triples validation failed on line {$lineNum}: predicate-object lists (';') are not allowed in N-Triples");
                }

                if ($char === ',') {
                    throw new ParseException("N-Triples validation failed on line {$lineNum}: object lists (',') are not allowed in N-Triples");
                }
            }
        }
    }
}
