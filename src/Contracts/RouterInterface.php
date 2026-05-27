<?php

declare(strict_types=1);

namespace PHPTypeS\BridgeLaravel\Contracts;


interface RouterInterface
{
    public function getRoutes(): mixed;
}
