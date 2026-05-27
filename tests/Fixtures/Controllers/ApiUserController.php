<?php

declare(strict_types=1);

namespace PHPTypeS\BridgeLaravel\Tests\Fixtures\Controllers;

use PHPTypeS\BridgeLaravel\Tests\Fixtures\DTOs\UserDto;

/**
 * Fixture controller used by RouteScannerTest.
 * Methods have type hints so the scanner can infer request/response types.
 */
final class ApiUserController
{
    /** @return UserDto[] */
    public function index(): array
    {
        return [];
    }

    public function show(int $id): UserDto
    {
        return new UserDto($id, '', '');
    }

    public function store(UserDto $request): UserDto
    {
        return $request;
    }

    public function destroy(int $id): void
    {
    }
}
