<?php

declare(strict_types=1);

namespace PHPTypeS\BridgeLaravel\Console;

use Illuminate\Console\Command;
use PHPTypeS\Bridge\Generators\FetchClientGenerator;
use PHPTypeS\Bridge\Generators\TypeScriptTypeGenerator;
use PHPTypeS\Bridge\Generators\ZodSchemaGenerator;
use PHPTypeS\Bridge\Parser\ReflectionParser;
use PHPTypeS\BridgeLaravel\Support\LaravelConfigAdapter;
use PHPTypeS\BridgeLaravel\RouteScanner\RouteScanner;

final class BridgeValidateCommand extends Command
{
    protected $signature = 'phptypes:validate
    {--out= : Override the output directory to validate against}';

    protected $description = 'Validate that generated TypeScript files are up to date with the PHP source';

    public function handle(): int
    {
        $config = (new LaravelConfigAdapter())->fromLaravelConfig(config('phptypes', []));

        if ($out = $this->option('out')) {
            $config = $config->withOverrides(outputDir: $out);
        }

        $this->line('  Validating generated files...');

        $useRoutes = config('phptypes.scan_routes', false);

        try {
            if ($useRoutes) {
                $schema = new \PHPTypeS\Bridge\Schema\ApiSchema();
                $scanner = new RouteScanner(
                    prefixFilter:       'api',
                    middlewareFilter:   ['api'],
                    excludeControllers: (array) config('phptypes.exclude', []),
                    typeAliases:        $config->typeAliases(),
                );
                /** @var \Illuminate\Routing\Router $router */
                $router = app('router');
                $scanner->scan($router->getRoutes(), $schema);
            } else {
                $schema = (new ReflectionParser($config->typeAliases()))
                    ->parse($config->sourceDirs());
            }
        } catch (\Throwable $e) {
            $this->error('Scan error: ' . $e->getMessage());
            return self::FAILURE;
        }

        $fresh   = [];
        $drifted = [];

        if ($config->shouldRunGenerator('types')) {
            $fresh[] = (new TypeScriptTypeGenerator())->generate($schema);
        }
        if ($config->shouldRunGenerator('schemas')) {
            $fresh[] = (new ZodSchemaGenerator())->generate($schema);
        }
        if ($config->shouldRunGenerator('client')) {
            $fresh[] = (new FetchClientGenerator())->generate($schema);
        }

        foreach ($fresh as $output) {
            $path = $config->outputDir() . '/' . $output->filename();

            if (!file_exists($path)) {
                $drifted[] = [$output->filename(), 'file does not exist'];
                continue;
            }

            if (file_get_contents($path) !== $output->content()) {
                $lines     = substr_count($output->content(), "\n") - substr_count((string) file_get_contents($path), "\n");
                $drifted[] = [$output->filename(), ($lines > 0 ? "+{$lines}" : "{$lines}") . ' lines'];
            }
        }

        if ($drifted === []) {
            $this->info('All generated files are up to date.');
            return self::SUCCESS;
        }

        $this->error(count($drifted) . ' file(s) out of sync:');

        foreach ($drifted as [$file, $reason]) {
            $this->line("  <fg=red>✗</> <comment>{$file}</comment>: {$reason}");
        }

        $this->newLine();
        $this->line('Run <info>php artisan phptypes:generate</info> to regenerate.');

        return self::FAILURE;
    }
}