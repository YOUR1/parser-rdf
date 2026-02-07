<?php

namespace App\Services\Ontology\Parsers;

interface OntologyParserInterface
{
    /**
     * Parse ontology content and return structured data
     */
    public function parse(string $content, array $options = []): array;

    /**
     * Validate if the content can be parsed by this parser
     */
    public function canParse(string $content): bool;

    /**
     * Get supported formats for this parser
     */
    public function getSupportedFormats(): array;
}
