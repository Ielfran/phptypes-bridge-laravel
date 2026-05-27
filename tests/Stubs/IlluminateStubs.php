<?php

declare(strict_types=1);

/**
 * Minimal stubs for the Illuminate classes used by the Laravel package.
 *
 * These exist ONLY to make the package's own unit tests run without
 * requiring a full Laravel application. They implement exactly the
 * method signatures that RouteScanner and the ServiceProvider call.
 *
 * When this package is installed inside a real Laravel app, the real
 * Illuminate classes are loaded from vendor/ and these stubs are never
 * seen.
 */

namespace Illuminate\Support {
    abstract class ServiceProvider
    {
        public function __construct(protected mixed $app) {}
        protected function mergeConfigFrom(string $path, string $key): void {}
        protected function publishes(array $paths, string $tag = ''): void {}
        public function commands(array $commands): void {}
        abstract public function register(): void;
        public function boot(): void {}
        public function provides(): array { return []; }
    }
}

namespace Illuminate\Console {
    abstract class Command
    {
        protected string $signature   = '';
        protected string $description = '';

        protected function option(string $key): mixed { return null; }
        protected function argument(string $key): mixed { return null; }
        protected function line(string $msg): void { echo $msg . PHP_EOL; }
        protected function info(string $msg): void { echo '[INFO] ' . $msg . PHP_EOL; }
        protected function error(string $msg): void { echo '[ERROR] ' . $msg . PHP_EOL; }
        protected function warn(string $msg): void { echo '[WARN] ' . $msg . PHP_EOL; }
        protected function call(string $command, array $args = []): void {}
        protected function callSilent(string $command, array $args = []): void {}

        public const SUCCESS = 0;
        public const FAILURE = 1;

        abstract public function handle(): int;
    }
}

namespace Illuminate\Routing {

    use PHPTypeS\BridgeLaravel\Tests\Fixtures\FakeRouteCollection;

    class Router
    {
        private FakeRouteCollection $routes;

        public function __construct(FakeRouteCollection $routes)
        {
            $this->routes = $routes;
        }

        public function getRoutes(): FakeRouteCollection
        {
            return $this->routes;
        }
    }
}

namespace Illuminate\Contracts\Foundation {
    interface Application {}
}

namespace {

// Global Laravel helper function stubs
// These are defined in illuminate/support helpers.php in a real app.
if (!function_exists('app_path')) {
    function app_path(string $path = ''): string {
        return '/app' . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('resource_path')) {
    function resource_path(string $path = ''): string {
        return '/resources' . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('config_path')) {
    function config_path(string $path = ''): string {
        return '/config' . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string {
        return '/var/www' . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed {
        return $default;
    }
}

if (!function_exists('app')) {
    function app(string $abstract = ''): mixed {
        return null;
    }
}

}
