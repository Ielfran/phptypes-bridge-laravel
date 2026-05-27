<?php

declare(strict_types=1);

namespace PHPTypeS\BridgeLaravel\Tests\Fixtures;

use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;

/**
 * A minimal fake of Illuminate\Routing\Route for tests.
 * Implements only the methods RouteScanner calls.
 */
final class FakeRoute
{
    /** @param list<string> $middleware */
    public function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly ?string $action,   // 'Controller@method' or null for closures
        private readonly array $middleware = [],
    ) {}

    /** @return list<string> */
    public function methods(): array
    {
        return [$this->method];
    }

    public function uri(): string
    {
        return $this->uri;
    }

    /** @return array<string, mixed> */
    public function getAction(?string $key = null): mixed
    {
        $full = $this->action !== null
            ? ['controller' => $this->action]
            : [];           // No 'controller' key = closure route

        if ($key !== null) {
            return $full[$key] ?? null;
        }

        return $full;
    }

    /** @return list<string> */
    public function middleware(): array
    {
        return $this->middleware;
    }
}

/**
 * A minimal fake of Illuminate\Routing\RouteCollection.
 */
final class FakeRouteCollection
{
    /** @param list<FakeRoute> $routes */
    public function __construct(private readonly array $routes) {}

    public function getRoutes(): self
    {
        return $this;
    }

    /** @return \ArrayIterator<int, FakeRoute> */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->routes);
    }
}
