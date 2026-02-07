<?php

namespace App\Services\Ontology\Parsers\Handlers;

use App\Services\Ontology\Exceptions\OntologyImportException;
use App\Services\Ontology\Parsers\Contracts\RdfFormatHandlerInterface;
use App\Services\Ontology\Parsers\ValueObjects\ParsedRdf;
use EasyRdf\Graph;

/**
 * Handler for N-Triples format parsing
 *
 * N-Triples is a line-based format where each line contains a complete triple.
 * It's the simplest RDF serialization format.
 */
class NTriplesHandler implements RdfFormatHandlerInterface
{
    public function canHandle(string $content): bool
    {
        $trimmed = trim($content);

        // N-Triples lines look like: <uri> <predicate> <object> .
        // They start with < and contain space-separated URIs
        $lines = explode("\n", $trimmed);
        $validLines = 0;

        foreach (array_slice($lines, 0, 10) as $line) { // Check first 10 lines
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('/^<[^>]+>\s+<[^>]+>\s+.+\s*\.$/', $line)) {
                $validLines++;
            }
        }

        return $validLines > 0;
    }

    public function parse(string $content): ParsedRdf
    {
        try {
            $graph = new Graph;
            $graph->parse($content, 'ntriples');

            $metadata = [
                'parser' => 'ntriples_handler',
                'format' => 'n-triples',
                'resource_count' => count($graph->resources()),
            ];

            return new ParsedRdf(
                graph: $graph,
                format: 'n-triples',
                rawContent: $content,
                metadata: $metadata
            );

        } catch (\Throwable $e) {
            throw new OntologyImportException('N-Triples parsing failed: '.$e->getMessage(), 0, $e);
        }
    }

    public function getFormatName(): string
    {
        return 'n-triples';
    }
}
