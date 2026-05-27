<?php

declare(strict_types=1);

namespace PHPTypeS\BridgeLaravel;

use Illuminate\Support\ServiceProvider;
use PHPTypeS\Bridge\Config\BridgeConfig;
use PHPTypeS\Bridge\Generators\FetchClientGenerator;
use PHPTypeS\Bridge\Generators\TypeScriptTypeGenerator;
use PHPTypeS\Bridge\Generators\ZodSchemaGenerator;
use PHPTypeS\Bridge\Parser\ReflectionParser;
use PHPTypeS\Bridge\Writer\FileWriter;
use PHPTypeS\BridgeLaravel\Console\BridgeGenerateCommand;
use PHPTypeS\BridgeLaravel\Console\BridgeInitCommand;
use PHPTypeS\BridgeLaravel\Console\BridgeValidateCommand;
use PHPTypeS\BridgeLaravel\RouteScanner\RouteScanner;
use PHPTypeS\BridgeLaravel\Support\LaravelConfigAdapter;

final class BridgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/phptypes.php', 'phptypes');

        $this->app->singleton(BridgeConfig::class, function (): BridgeConfig {
            return (new LaravelConfigAdapter())->fromLaravelConfig(
                config('phptypes', [])
            );
        });

        $this->app->singleton(ReflectionParser::class, function (): ReflectionParser {
            $config = $this->app->make(BridgeConfig::class);
            return new ReflectionParser($config->typeAliases());
        });

        $this->app->singleton(TypeScriptTypeGenerator::class, fn () => new TypeScriptTypeGenerator());
        $this->app->singleton(ZodSchemaGenerator::class,      fn () => new ZodSchemaGenerator());
        $this->app->singleton(FetchClientGenerator::class,    fn () => new FetchClientGenerator());
        $this->app->singleton(FileWriter::class,               fn () => new FileWriter());

        $this->app->singleton(RouteScanner::class, function (): RouteScanner {
            $config = $this->app->make(BridgeConfig::class);
            return new RouteScanner(
                prefixFilter: (string) config('phptypes.route_scanning.prefix_filter', 'api'),
                middlewareFilter: (array) config('phptypes.route_scanning.middleware_filter', ['api']),
                excludeControllers: (array) config('phptypes.exclude_controllers', []),
                typeAliases: $config->typeAliases(),
            );
        });
    }

    public function boot(): void
    {
        $this->app['router']->pushMiddlewareToGroup(
            'api',
            \PHPTypeS\BridgeLaravel\Http\DtoFromRequestMiddleware::class
        );
    
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/phptypes.php' => config_path('phptypes.php'),
            ], 'phptypes-config');
    
            $this->commands([
                BridgeGenerateCommand::class,
                BridgeValidateCommand::class,
                BridgeInitCommand::class,
            ]);
        }
    }

    public static function objectToArray(mixed $value): mixed
    {
        if (is_object($value)) {
            $result = [];
            foreach (get_object_vars($value) as $key => $prop) {
                $result[$key] = self::objectToArray($prop);
            }
            return $result;
        }

        if (is_array($value)) {
            return array_map([self::class, 'objectToArray'], $value);
        }

        return $value;
    }
}