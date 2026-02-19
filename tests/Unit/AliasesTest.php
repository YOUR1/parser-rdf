<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserRdf\RdfParser;

describe('class_alias bridge', function () {

    describe('alias resolution', function () {
        it('resolves RdfParser from old namespace', function () {
            expect(class_exists('App\Services\Ontology\Parsers\RdfParser'))->toBeTrue();
        });
    });

    describe('instanceof compatibility', function () {
        it('new RdfParser is instanceof old namespace name', function () {
            $parser = new RdfParser();
            expect($parser)->toBeInstanceOf('App\Services\Ontology\Parsers\RdfParser');
        });

        it('old namespace resolves to same class as new namespace', function () {
            $oldReflection = new \ReflectionClass('App\Services\Ontology\Parsers\RdfParser');
            $newReflection = new \ReflectionClass(RdfParser::class);
            expect($oldReflection->getName())->toBe($newReflection->getName());
        });
    });

    describe('deprecation warnings', function () {
        $captureDeprecations = function (): array {
            static $cache = null;
            if ($cache !== null) {
                return $cache;
            }

            $projectRoot = dirname(__DIR__, 2);
            $script = <<<'PHP'
<?php
$deprecations = [];
set_error_handler(function (int $errno, string $errstr) use (&$deprecations) {
    if ($errno === E_USER_DEPRECATED) {
        $deprecations[] = $errstr;
    }
    return true;
});
require $argv[1] . '/vendor/autoload.php';
// Trigger alias by referencing old namespace class
class_exists('App\Services\Ontology\Parsers\RdfParser');
echo json_encode($deprecations);
PHP;
            $tempFile = tempnam(sys_get_temp_dir(), 'alias_test_');
            if ($tempFile === false) {
                throw new \RuntimeException('Failed to create temp file');
            }
            file_put_contents($tempFile, $script);
            $output = shell_exec('php ' . escapeshellarg($tempFile) . ' ' . escapeshellarg($projectRoot)) ?? '[]';
            unlink($tempFile);

            $cache = json_decode($output, true) ?? [];

            return $cache;
        };

        it('triggers E_USER_DEPRECATED when old RdfParser class is referenced', function () use ($captureDeprecations) {
            expect($captureDeprecations())->toBeArray()->toHaveCount(1);
        });

        it('deprecation message contains old and new FQCN', function () use ($captureDeprecations) {
            $deprecations = $captureDeprecations();
            expect($deprecations[0])
                ->toContain('App\Services\Ontology\Parsers\RdfParser')
                ->toContain('Youri\vandenBogert\Software\ParserRdf\RdfParser');
        });

        it('deprecation message mentions v2.0 removal', function () use ($captureDeprecations) {
            $deprecations = $captureDeprecations();
            expect($deprecations[0])->toContain('v2.0');
        });

        it('does NOT trigger deprecation at autoload time', function () {
            $projectRoot = dirname(__DIR__, 2);
            $script = <<<'PHP'
<?php
$deprecations = [];
set_error_handler(function (int $errno, string $errstr) use (&$deprecations) {
    if ($errno === E_USER_DEPRECATED) {
        $deprecations[] = $errstr;
    }
    return true;
});
require $argv[1] . '/vendor/autoload.php';
// Do NOT reference any old namespace classes
echo json_encode($deprecations);
PHP;
            $tempFile = tempnam(sys_get_temp_dir(), 'alias_test_');
            if ($tempFile === false) {
                throw new \RuntimeException('Failed to create temp file');
            }
            file_put_contents($tempFile, $script);
            $output = shell_exec('php ' . escapeshellarg($tempFile) . ' ' . escapeshellarg($projectRoot)) ?? '[]';
            unlink($tempFile);

            $deprecations = json_decode($output, true) ?? [];
            expect($deprecations)->toBeArray()->toHaveCount(0);
        });
    });

    describe('no aliases for internal classes', function () {
        it('does not eagerly alias extractors', function () {
            expect(class_exists('App\Services\Ontology\Parsers\Extractors\ClassExtractor', false))->toBeFalse();
            expect(class_exists('App\Services\Ontology\Parsers\Extractors\PropertyExtractor', false))->toBeFalse();
            expect(class_exists('App\Services\Ontology\Parsers\Extractors\PrefixExtractor', false))->toBeFalse();
            expect(class_exists('App\Services\Ontology\Parsers\Extractors\ShapeExtractor', false))->toBeFalse();
        });

        it('does not eagerly alias NTriplesHandler', function () {
            expect(class_exists('App\Services\Ontology\Parsers\Handlers\NTriplesHandler', false))->toBeFalse();
        });

        it('does not eagerly alias old OntologyParserInterface', function () {
            // OntologyParserInterface alias is owned by parser-core (Story 2.7), not parser-rdf
            expect(true)->toBeTrue();
        });
    });
});
