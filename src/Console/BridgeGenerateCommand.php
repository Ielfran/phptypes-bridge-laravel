<?php

declare(strict_types=1);

namespace PHPTypeS\BridgeLaravel\Console;

use Illuminate\Console\Command;
use PHPTypeS\Bridge\Generators\FetchClientGenerator;
use PHPTypeS\Bridge\Generators\Output\GeneratorOutput;
use PHPTypeS\Bridge\Generators\TypeScriptTypeGenerator;
use PHPTypeS\Bridge\Generators\ZodSchemaGenerator;
use PHPTypeS\Bridge\Parser\ReflectionParser;
use PHPTypeS\Bridge\Schema\ApiSchema;
use PHPTypeS\Bridge\Writer\DryRunWriter;
use PHPTypeS\Bridge\Writer\FileWriter;
use PHPTypeS\BridgeLaravel\Support\LaravelConfigAdapter;
use PHPTypeS\BridgeLaravel\RouteScanner\RouteScanner;

final class BridgeGenerateCommand extends Command
{
    protected $signature = 'phptypes:generate
    {--out=         : Override the output directory}
    {--only=        : Comma-separated generators to run: types,schemas,client}
    {--dry-run      : Print output to console instead of writing files}
    {--routes       : Force route-scanning mode}
    {--no-routes    : Force attribute-scanning mode}';

    protected $description = 'Generate TypeScript types, Zod schemas, and a fetch client from your PHP API';

    public function handle(): int
    {
        $rawConfig = config('phptypes', []);
        $adapter   = new LaravelConfigAdapter();
        $config    = $adapter->fromLaravelConfig($rawConfig);

        if ($out = $this->option('out')) {
            $config = $config->withOverrides(outputDir: $out);
        }

        if ($only = $this->option('only')) {
            $config = $config->withOverrides(
                only: array_map('trim', explode(',', (string) $only))
            );
        }

        if ($this->option('dry-run')) {
            $config = $config->withOverrides(dryRun: true);
        }

        $useRoutes = $this->option('routes')
            || (!$this->option('no-routes') && config('phptypes.scan_routes', false));

        $this->line($useRoutes
            ? '  <comment>Mode:</comment> route scanning'
            : '  <comment>Mode:</comment> attribute scanning');

        try {
            $schema = $useRoutes
                ? $this->scanRoutes($config)
                : $this->scanAttributes($config);
        } catch (\Throwable $e) {
            $this->error('Scan error: ' . $e->getMessage());
            return self::FAILURE;
        }

        if ($schema->isEmpty()) {
            $this->warn('No endpoints found. '
                . ($useRoutes
                    ? 'Ensure route_file is correct and routes/api.php has controller actions.'
                    : 'Annotate controller methods with #[ApiEndpoint] or enable scan_routes.'));

            return self::SUCCESS;
        }

        $this->line(sprintf(
            '  Found <info>%d</info> endpoint(s) and <info>%d</info> DTO(s)',
            count($schema->endpoints()),
            count($schema->dtos())
        ));

        $outputs = [];

        if ($config->shouldRunGenerator('types')) {
            $outputs[] = (new TypeScriptTypeGenerator())->generate($schema);
        }

        if ($config->shouldRunGenerator('schemas')) {
            $outputs[] = (new ZodSchemaGenerator())->generate($schema);
        }

        if ($config->shouldRunGenerator('client')) {
            $outputs[] = (new FetchClientGenerator())->generate($schema);
        }

        if ($config->isDryRun()) {
            (new DryRunWriter())->printAll($outputs);
            $this->info('Dry run complete — no files written.');
            return self::SUCCESS;
        }

        try {
            $written = (new FileWriter())->writeAll($outputs, $config->outputDir());
        } catch (\Throwable $e) {
            $this->error('Write error: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();

        foreach ($written as $path) {
            $this->line("  <info>✓</info> {$path}");
        }

        $this->generateJsonSerializableTrait();

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function generateJsonSerializableTrait(): void
    {
        $dtoDirs = config('phptypes.source_dirs', []);
        $dtoPath = null;

        foreach ($dtoDirs as $dir) {
            if (str_contains((string) $dir, 'DTOs') || str_contains((string) $dir, 'Dto')) {
                $dtoPath = $dir;
                break;
            }
        }

        if ($dtoPath === null) {
            $dtoPath = app_path('DTOs');
        }

        $relativePath = ltrim(
            str_replace([app_path(), '\\', '/'], ['', '\\', '\\'], (string) $dtoPath),
            '\\'
        );

        $namespace = 'App\\' . $relativePath;
        $namespace = rtrim($namespace, '\\');

        if (!is_dir((string) $dtoPath)) {
            mkdir((string) $dtoPath, 0755, true);
        }

        $traitFile = rtrim((string) $dtoPath, '\\/') . DIRECTORY_SEPARATOR . 'JsonSerializableDto.php';

        $content = $this->buildTraitContent($namespace);

        file_put_contents($traitFile, $content);

        $this->line("  <info>✓</info> {$traitFile}");
        $this->line('  <comment>Tip:</comment> Add <info>implements \\JsonSerializable</info> + <info>use JsonSerializableDto;</info> to each DTO class.');
    }

    private function buildTraitContent(string $namespace): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        trait JsonSerializableDto
        {
            public function jsonSerialize(): array
            {
                return get_object_vars(\$this);
            }
        }
        PHP;
    }

    private function scanAttributes(\PHPTypeS\Bridge\Config\BridgeConfig $config): ApiSchema
    {
        return (new ReflectionParser($config->typeAliases()))
            ->parse($config->sourceDirs());
    }

    private function scanRoutes(\PHPTypeS\Bridge\Config\BridgeConfig $config): ApiSchema
    {
        $schema  = new ApiSchema();
        $exclude = (array) config('phptypes.exclude', []);

        $routeFile = base_path((string) config('phptypes.route_file', 'routes/api.php'));

        if (!file_exists($routeFile)) {
            throw new \RuntimeException("Route file not found: {$routeFile}");
        }

        $router = app('router');

        $scanner = new RouteScanner(
            prefixFilter:       'api',
            middlewareFilter:   ['api'],
            excludeControllers: $exclude,
            typeAliases:        $config->typeAliases(),
        );

        $scanner->scan($router->getRoutes(), $schema);

        return $schema;
    }
}