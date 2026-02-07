<?php

namespace App\Services\Ontology\Parsers\Handlers;

use App\Services\Ontology\Exceptions\OntologyImportException;
use App\Services\Ontology\Parsers\Contracts\RdfFormatHandlerInterface;
use App\Services\Ontology\Parsers\ValueObjects\ParsedRdf;
use EasyRdf\Graph;

/**
 * Handler for JSON-LD format parsing
 */
class JsonLdHandler implements RdfFormatHandlerInterface
{
    public function canHandle(string $content): bool
    {
        $trimmed = trim($content);

        if (! str_starts_with($trimmed, '{')) {
            return false;
        }

        return str_contains($trimmed, '@context');
    }

    public function parse(string $content): ParsedRdf
    {
        try {
            // Validate JSON structure
            $decoded = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new OntologyImportException('Invalid JSON: '.json_last_error_msg());
            }

            if (! isset($decoded['@context'])) {
                throw new OntologyImportException('Missing @context in JSON-LD');
            }

            $graph = new Graph;
            $graph->parse($content, 'jsonld');

            $metadata = [
                'parser' => 'jsonld_handler',
                'format' => 'json-ld',
                'resource_count' => count($graph->resources()),
                'context' => $decoded['@context'] ?? null,
            ];

            return new ParsedRdf(
                graph: $graph,
                format: 'json-ld',
                rawContent: $content,
                metadata: $metadata
            );

        } catch (OntologyImportException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new OntologyImportException('JSON-LD parsing failed: '.$e->getMessage(), 0, $e);
        }
    }

    public function getFormatName(): string
    {
        return 'json-ld';
    }
}
