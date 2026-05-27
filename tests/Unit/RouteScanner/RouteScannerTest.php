<?php

declare(strict_types=1);

namespace PHPTypeS\BridgeLaravel\Tests\Unit\RouteScanner;

use PHPUnit\Framework\TestCase;
use PHPTypeS\Bridge\Schema\ApiSchema;
use PHPTypeS\Bridge\Schema\Nodes\RefTypeNode;
use PHPTypeS\Bridge\Schema\Nodes\ScalarTypeNode;
use PHPTypeS\BridgeLaravel\RouteScanner\RouteScanner;
use PHPTypeS\BridgeLaravel\Tests\Fixtures\FakeRouter;
use PHPTypeS\BridgeLaravel\Tests\Fixtures\Controllers\ApiUserController;
use PHPTypeS\BridgeLaravel\Tests\Fixtures\Controllers\AdminController;

final class RouteScannerTest extends TestCase
{
    // ── Basic scanning ────────────────────────────────────────────────────────

    public function test_scanner_adds_endpoints_from_routes(): void
    {
        $router  = FakeRouter::make([
            ['GET', 'api/users/{id}', ApiUserController::class . '@show', ['api']],
        ]);
        $schema  = new ApiSchema();
        $scanner = new RouteScanner(prefixFilter: 'api', middlewareFilter: ['api']);

        $scanner->scan($router, $schema);

        $this->assertCount(1, $schema->endpoints());
    }

    public function test_scanner_sets_correct_http_method(): void
    {
        $router  = FakeRouter::make([
            ['POST', 'api/users', ApiUserController::class . '@store', ['api']],
        ]);
        $schema  = new ApiSchema();
        $scanner = new RouteScanner(prefixFilter: 'api');

        $scanner->scan($router, $schema);

        $this->assertSame('POST', $schema->endpoints()[0]->httpMethod());
    }

    public function test_scanner_sets_correct_path_with_leading_slash(): void
    {
        $router  = FakeRouter::make([
            ['GET', 'api/users/{id}', ApiUserController::class . '@show', ['api']],
        ]);
        $schema  = new ApiSchema();
        $scanner = new RouteScanner(prefixFilter: 'api');

        $scanner->scan($router, $schema);

        $this->assertSame('/api/users/{id}', $schema->endpoints()[0]->path());
    }

    public function test_scanner_extracts_path_params(): void
    {
        $router  = FakeRouter::make([
            ['GET', 'api/users/{id}/posts/{postId}', ApiUserController::class . '@show', ['api']],
        ]);
        $schema  = new ApiSchema();
        $scanner = new RouteScanner(prefixFilter: 'api');

        $scanner->scan($router, $schema);

        $this->assertSame(['id', 'postId'], $schema->endpoints()[0]->pathParams());
    }

    // ── Prefix filter ─────────────────────────────────────────────────────────

    public function test_scanner_skips_routes_not_matching_prefix(): void
    {
        $router  = FakeRouter::make([
            ['GET', 'api/users',  ApiUserController::class . '@index', ['api']],
            ['GET', 'web/home',   ApiUserController::class . '@show',  []],
        ]);
        $schema  = new ApiSchema();
        $scanner = new RouteScanner(prefixFilter: 'api');

        $scanner->scan($router, $schema);

        $this->assertCount(1, $schema->endpoints());
        $this->assertStringStartsWith('/api', $schema->endpoints()[0]->path());
    }

    public function test_scanner_with_empty_prefix_includes_all_routes(): void
    {
        $router  = FakeRouter::make([
            ['GET', 'api/users', ApiUserController::class . '@index', ['api']],
            ['GET', 'web/home',  ApiUserController::class . '@show',  []],
        ]);
        $schema  = new ApiSchema();
        $scanner = new RouteScanner(prefixFilter: '', middlewareFilter: []);

        $scanner->scan($router, $schema);

        $this->assertCount(2, $schema->endpoints());
    }

    // ── Middleware filter ─────────────────────────────────────────────────────

    public function test_scanner_skips_routes_missing_required_middleware(): void
    {
        $router  = FakeRouter::make([
            ['GET', 'api/users', ApiUserController::class . '@index', ['api', 'auth']],
            ['GET', 'api/open',  ApiUserController::class . '@show',  ['api']],
        ]);
        $schema  = new ApiSchema();
        $scanner = new RouteScanner(prefixFilter: 'api', middlewareFilter: ['api', 'auth']);

        $scanner->scan($router, $schema);

        // Only the route with BOTH api + auth passes the filter
        $this->assertCount(1, $schema->endpoints());
    }

    // ── Excluded controllers ──────────────────────────────────────────────────

    public function test_scanner_skips_excluded_controllers(): void
    {
        $router  = FakeRouter::make([
            ['GET', 'api/users', ApiUserController::class . '@index', ['api']],
            ['GET', 'api/admin', AdminController::class . '@index',   ['api']],
        ]);
        $schema  = new ApiSchema();
        $scanner = new RouteScanner(
            prefixFilter: 'api',
            excludeControllers: [AdminController::class],
        );

        $scanner->scan($router, $schema);

        $this->assertCount(1, $schema->endpoints());
        $this->assertSame(ApiUserController::class, $schema->endpoints()[0]->controllerFqcn());
    }

    // ── Already-registered deduplication ─────────────────────────────────────

    public function test_scanner_skips_endpoints_already_in_schema(): void
    {
        $router  = FakeRouter::make([
            ['GET', 'api/users/{id}', ApiUserController::class . '@show', ['api']],
        ]);

        $schema  = new ApiSchema();
        $scanner = new RouteScanner(prefixFilter: 'api');

        // First scan
        $scanner->scan($router, $schema);
        $this->assertCount(1, $schema->endpoints());

        // Second scan — endpoint already registered, should not duplicate
        $scanner->scan($router, $schema);
        $this->assertCount(1, $schema->endpoints());
    }

    // ── Response type inference ───────────────────────────────────────────────

    public function test_scanner_infers_response_type_from_return_type_hint(): void
    {
        $router  = FakeRouter::make([
            ['GET', 'api/users/{id}', ApiUserController::class . '@show', ['api']],
        ]);
        $schema  = new ApiSchema();
        $scanner = new RouteScanner(prefixFilter: 'api');

        $scanner->scan($router, $schema);

        $endpoint = $schema->endpoints()[0];
        $this->assertInstanceOf(RefTypeNode::class, $endpoint->responseType());
    }

    public function test_scanner_sets_null_response_for_void_method(): void
    {
        $router  = FakeRouter::make([
            ['DELETE', 'api/users/{id}', ApiUserController::class . '@destroy', ['api']],
        ]);
        $schema  = new ApiSchema();
        $scanner = new RouteScanner(prefixFilter: 'api');

        $scanner->scan($router, $schema);

        $this->assertNull($schema->endpoints()[0]->responseType());
    }

    // ── Closure route skipping ────────────────────────────────────────────────

    public function test_scanner_skips_closure_routes(): void
    {
        $router  = FakeRouter::makeWithClosure([
            ['GET', 'api/ping', null, ['api']],  // null = closure route in fake
        ]);
        $schema  = new ApiSchema();
        $scanner = new RouteScanner(prefixFilter: 'api');

        $scanner->scan($router, $schema);

        $this->assertCount(0, $schema->endpoints());
    }
}
