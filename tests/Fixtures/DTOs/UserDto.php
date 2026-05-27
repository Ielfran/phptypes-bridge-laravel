<?php

declare(strict_types=1);

namespace PHPTypeS\BridgeLaravel\Tests\Fixtures\DTOs;

final class UserDto
{
    public function __construct(
        public readonly int    $id,
        public readonly string $email,
        public readonly string $name,
        public readonly ?string $bio = null,
    ) {}
}
