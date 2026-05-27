<?php

declare(strict_types=1);

namespace PHPTypeS\BridgeLaravel\Http;

use Closure;
use Illuminate\Http\Request;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;

final class DtoFromRequestMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $route = $request->route();

        if ($route === null) {
            return $next($request);
        }

        $action = $route->getAction('controller');

        if ($action === null || !str_contains($action, '@')) {
            return $next($request);
        }

        [$controllerClass, $method] = explode('@', $action, 2);

        if (!class_exists($controllerClass)) {
            return $next($request);
        }

        try {
            $ref = new ReflectionClass($controllerClass);
        } catch (ReflectionException) {
            return $next($request);
        }

        if (!$ref->hasMethod($method)) {
            return $next($request);
        }

        foreach ($ref->getMethod($method)->getParameters() as $param) {
            $type = $param->getType();

            if (!($type instanceof ReflectionNamedType) || $type->isBuiltin()) {
                continue;
            }

            $fqcn = $type->getName();

            foreach (['Illuminate\\', 'Symfony\\', 'Laravel\\', 'Psr\\'] as $prefix) {
                if (str_starts_with($fqcn, $prefix)) {
                    continue 2;
                }
            }

            if (!class_exists($fqcn)) {
                continue;
            }

            try {
                $dtoRef = new ReflectionClass($fqcn);
                $ctor   = $dtoRef->getConstructor();
            } catch (ReflectionException) {
                continue;
            }

            if ($ctor === null) {
                continue;
            }

            foreach ($ctor->getParameters() as $ctorParam) {
                $ctorType = $ctorParam->getType();
                if ($ctorType instanceof ReflectionNamedType && !$ctorType->isBuiltin()) {
                    continue 2; // has a class dependency — not a plain DTO
                }
            }

            app()->bind($fqcn, function () use ($fqcn, $ctor, $request): object {
                $args = [];

                foreach ($ctor->getParameters() as $ctorParam) {
                    $name = $ctorParam->getName();

                    $value = $request->input($name)
                        ?? $request->input($this->toSnakeCase($name));

                    if ($value === null && $ctorParam->isDefaultValueAvailable()) {
                        $value = $ctorParam->getDefaultValue();
                    }

                    $ctorType = $ctorParam->getType();
                    if ($ctorType instanceof ReflectionNamedType && $value !== null) {
                        $value = match ($ctorType->getName()) {
                            'int'   => (int) $value,
                            'float' => (float) $value,
                            'bool'  => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                            default => $value,
                        };
                    }

                    $args[] = $value;
                }

                foreach ($ctor->getParameters() as $i => $ctorParam) {
                    $ctorType = $ctorParam->getType();
                    if (
                        !$ctorParam->isDefaultValueAvailable()
                        && !($ctorType?->allowsNull() ?? false)
                        && ($args[$i] === null || $args[$i] === '')
                    ) {
                        abort(response()->json([
                            'message' => 'Validation failed.',
                            'errors'  => [
                                $ctorParam->getName() => [
                                    "The {$ctorParam->getName()} field is required."
                                ],
                            ],
                        ], 422));
                    }
                }

                return new $fqcn(...$args);
            });
        }

        return $next($request);
    }

    private function toSnakeCase(string $input): string
    {
        return strtolower((string) preg_replace('/[A-Z]/', '_$0', lcfirst($input)));
    }
}