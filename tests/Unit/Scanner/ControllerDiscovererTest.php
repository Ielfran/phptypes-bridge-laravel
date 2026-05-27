<?php

declare(strict_types=1);

namespace PHPTypeS\BridgeLaravel\Tests\Unit\Scanner;

use PHPUnit\Framework\TestCase;
use PHPTypeS\BridgeLaravel\Scanner\ControllerDiscoverer;
use PHPTypeS\BridgeLaravel\Tests\Fixtures\Controllers\ApiUserController;
use PHPTypeS\BridgeLaravel\Tests\Fixtures\Controllers\AdminController;
use PHPTypeS\BridgeLaravel\Tests\Fixtures\DTOs\UserDto;

final class ControllerDiscovererTest extends TestCase
{
    // ── Basic discovery ───────────────────────────────────────────────────────

    public function test_discovers_classes_in_controllers_directory(): void
    {
        $discoverer = new ControllerDiscoverer();
        $classes    = $discoverer->discover([
            __DIR__ . '/../../Fixtures/Controllers',
        ]);

        $fqcns = array_map(fn($r) => $r->getName(), $classes);

        $this->assertContains(ApiUserController::class, $fqcns);
    }

    public function test_discovers_classes_in_dtos_directory(): void
    {
        $discoverer = new ControllerDiscoverer();
        $classes    = $discoverer->discover([
            __DIR__ . '/../../Fixtures/DTOs',
        ]);

        $fqcns = array_map(fn($r) => $r->getName(), $classes);
        $this->assertContains(UserDto::class, $fqcns);
    }

    public function test_discovers_across_multiple_directories(): void
    {
        $discoverer = new ControllerDiscoverer();
        $classes    = $discoverer->discover([
            __DIR__ . '/../../Fixtures/Controllers',
            __DIR__ . '/../../Fixtures/DTOs',
        ]);

        $fqcns = array_map(fn($r) => $r->getName(), $classes);

        $this->assertContains(ApiUserController::class, $fqcns);
        $this->assertContains(UserDto::class, $fqcns);
    }

    // ── Exclusion ─────────────────────────────────────────────────────────────

    public function test_excluded_prefix_removes_matching_classes(): void
    {
        $discoverer = new ControllerDiscoverer(
            excludedPrefixes: [AdminController::class],
        );

        $classes = $discoverer->discover([__DIR__ . '/../../Fixtures/Controllers']);
        $fqcns   = array_map(fn($r) => $r->getName(), $classes);

        $this->assertNotContains(AdminController::class, $fqcns);
        $this->assertContains(ApiUserController::class, $fqcns);
    }

    public function test_excluded_namespace_prefix_removes_all_matching(): void
    {
        $discoverer = new ControllerDiscoverer(
            excludedPrefixes: ['PHPTypeS\BridgeLaravel\Tests\Fixtures\Controllers'],
        );

        $classes = $discoverer->discover([__DIR__ . '/../../Fixtures/Controllers']);
        $this->assertCount(0, $classes);
    }

    // ── Edge cases ────────────────────────────────────────────────────────────

    public function test_nonexistent_directory_is_silently_skipped(): void
    {
        $discoverer = new ControllerDiscoverer();
        $classes    = $discoverer->discover(['/does/not/exist/anywhere']);

        $this->assertSame([], $classes);
    }

    public function test_empty_directories_list_returns_empty_array(): void
    {
        $discoverer = new ControllerDiscoverer();
        $classes    = $discoverer->discover([]);

        $this->assertSame([], $classes);
    }
}
