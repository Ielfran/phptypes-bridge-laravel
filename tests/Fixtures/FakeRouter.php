<?php

declare(strict_types=1);

namespace PHPTypeS\BridgeLaravel\Tests\Fixtures;

/**
 * Minimal fake router for RouteScanner unit tests.
 * Each route is a plain object with methods(), uri(), getAction(), middleware().
 */
final class FakeRouter
{
    /** @param list<array{string, string, string, list<string>}> $routes */
    public static function make(array $routes): self
    {
        $fake = new self();
        $fake->routes = array_map(
            fn(array $r) => new FakeRoute($r[0], $r[1], $r[2], $r[3]),
            $routes
        );
        return $fake;
    }

    /** @param list<array{string, string, null, list<string>}> $routes */
    public static function makeWithClosure(array $routes): self
    {
        $fake = new self();
        $fake->routes = array_map(
            fn(array $r) => new FakeRoute($r[0], $r[1], null, $r[3]),
            $routes
        );
        return $fake;
    }

    /** @var list<FakeRoute> */
    private array $routes = [];

    /** @return list<FakeRoute> */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}

final class FakeRoute
{
    /** @param list<string> $middleware */
    public function __construct(
        private readonly string  $method,
        private readonly string  $uri,
        private readonly ?string $controller,
        private readonly array   $middleware,
    ) {}

    /** @return list<string> */
    public function methods(): array { return [$this->method]; }
    public function uri(): string    { return $this->uri; }

    /** @return array<string, mixed> */
    public function getAction(?string $key = null): mixed
    {
        $full = $this->controller !== null ? ['controller' => $this->controller] : [];
        return $key !== null ? ($full[$key] ?? null) : $full;
    }

    /** @return list<string> */
    public function middleware(): array { return $this->middleware; }
}
