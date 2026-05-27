<?php

declare(strict_types=1);

namespace PHPTypeS\BridgeLaravel\RouteScanner;

use PHPTypeS\BridgeLaravel\Contracts\RouterInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use PHPTypeS\Bridge\Parser\DtoParser;
use PHPTypeS\Bridge\Parser\DocBlockParser;
use PHPTypeS\Bridge\Schema\ApiSchema;
use PHPTypeS\Bridge\Schema\EndpointSchema;
use PHPTypeS\Bridge\Schema\Nodes\ArrayTypeNode;
use PHPTypeS\Bridge\Schema\Nodes\RefTypeNode;
use PHPTypeS\Bridge\Schema\Nodes\ScalarTypeNode;
use PHPTypeS\Bridge\Schema\Nodes\TypeNode;
use PHPTypeS\Bridge\TypeMapper\TypeMapper;

final class RouteScanner
{
    private readonly TypeMapper $typeMapper;
    private readonly DtoParser $dtoParser;
    private readonly DocBlockParser $docBlockParser;

    public function __construct(
        private readonly string $prefixFilter = 'api',
        private readonly array $middlewareFilter = ['api'],
        private readonly array $excludeControllers = [],
        private readonly array $typeAliases = [],
    ) {
        $this->typeMapper     = new TypeMapper($typeAliases);
        $this->dtoParser      = new DtoParser($this->typeMapper);
        $this->docBlockParser = new DocBlockParser();
    }

    public function scan(object $router, ApiSchema $schema): void
    {
        
        $rawCollection = $router->getRoutes();
        $iterable = is_array($rawCollection)
            ? $rawCollection
            : (method_exists($rawCollection, 'getRoutes') ? $rawCollection->getRoutes() : []);

        foreach ($iterable as $route) {
            if (!$this->shouldInclude($route)) {
                continue;
            }

            $action = $route->getAction();

            if (!isset($action['controller'])) {
                continue;
            }

            [$controllerFqcn, $methodName] = $this->parseAction((string) $action['controller']);

            if ($controllerFqcn === null || $methodName === null) {
                continue;
            }

            if (in_array($controllerFqcn, $this->excludeControllers, true)) {
                continue;
            }

            if ($this->endpointAlreadyRegistered($schema, $controllerFqcn, $methodName)) {
                continue;
            }

            $endpoint = $this->buildEndpoint($route, $controllerFqcn, $methodName, $schema);

            if ($endpoint !== null) {
                $schema->addEndpoint($endpoint);
            }
        }
    }


    private function shouldInclude(object $route): bool
    {
        if ($this->prefixFilter !== '') {
            $uri = ltrim($route->uri(), '/');
            if (!str_starts_with($uri, ltrim($this->prefixFilter, '/'))) {
                return false;
            }
        }

        if ($this->middlewareFilter !== []) {
            $routeMiddleware = $route->middleware();
            foreach ($this->middlewareFilter as $required) {
                if (!in_array($required, $routeMiddleware, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function buildEndpoint(
        object $route,
        string $controllerFqcn,
        string $methodName,
        ApiSchema $schema,
    ): ?EndpointSchema {
        try {
            $refClass  = new ReflectionClass($controllerFqcn);
            $refMethod = $refClass->getMethod($methodName);
        } catch (ReflectionException) {
            return null;
        }

        $httpMethod   = $this->resolveHttpMethod($route);
        $path         = '/' . ltrim($route->uri(), '/');
        $pathParams   = $this->extractPathParams($path);

        $responseType = $this->resolveResponseType($refMethod, $controllerFqcn, $schema);
        $requestType  = $this->resolveRequestType($refMethod, $controllerFqcn, $schema);

        return new EndpointSchema(
            name: lcfirst($methodName),
            httpMethod: $httpMethod,
            path: $path,
            controllerFqcn: $controllerFqcn,
            methodName: $methodName,
            requestType: $requestType,
            responseType: $responseType,
            pathParams: $pathParams,
        );
    }

    private function resolveResponseType(
        ReflectionMethod $method,
        string $context,
        ApiSchema $schema,
    ): ?TypeNode {
        $returnType = $method->getReturnType();

        if ($returnType !== null) {
            $node = $this->typeMapper->fromReflectionType($returnType, $context);

            if ($node instanceof ScalarTypeNode
                && in_array($node->phpType(), ['void', 'null', 'self', 'static', 'mixed'], true)
            ) {
                return null;
            }

            if ($node instanceof ArrayTypeNode) {
                $node = $this->enrichArrayFromDocBlock($node, $method, $context);
            }

            $this->registerDtoIfNeeded($node, $schema);
            return $node;
        }

        $docComment = $method->getDocComment() ?: '';
        if ($docComment !== '') {
            $docType = $this->docBlockParser->extractReturn($docComment);
            if ($docType !== null) {
                try {
                    $node = $this->typeMapper->fromString($docType, $context);
                    $this->registerDtoIfNeeded($node, $schema);
                    return $node;
                } catch (\Throwable) {
                }
            }
        }

        return null;
    }

    private function resolveRequestType(
        ReflectionMethod $method,
        string $context,
        ApiSchema $schema,
    ): ?TypeNode {
        foreach ($method->getParameters() as $param) {
            $type = $param->getType();

            if ($type === null) {
                continue;
            }

            $node = $this->typeMapper->fromReflectionType($type, $context);

            if ($node instanceof RefTypeNode) {
                $this->registerDtoIfNeeded($node, $schema);
                return $node;
            }
        }

        return null;
    }

   
    private function registerDtoIfNeeded(TypeNode $node, ApiSchema $schema): void
    {
        $visiting = [];
        $this->walkTypeNode($node, $schema, $visiting);
    }

 
    private function walkTypeNode(TypeNode $node, ApiSchema $schema, array &$visiting): void
    {
        if ($node instanceof RefTypeNode) {
            $fqcn = $node->fqcn();

            if ($schema->hasDto($fqcn) || isset($visiting[$fqcn]) || !class_exists($fqcn)) {
                return;
            }

            $visiting[$fqcn] = true;

            try {
                $ref = new ReflectionClass($fqcn);
                $dto = $this->dtoParser->parse($ref);
                $schema->addDto($dto);

                foreach ($dto->properties() as $prop) {
                    $this->walkTypeNode($prop->typeNode(), $schema, $visiting);
                }
            } catch (\Throwable) {
            }

            unset($visiting[$fqcn]);
        }

        if ($node instanceof ArrayTypeNode) {
            $this->walkTypeNode($node->itemType(), $schema, $visiting);
        }
    }

 
    private function enrichArrayFromDocBlock(
        ArrayTypeNode $node,
        ReflectionMethod $method,
        string $context,
    ): TypeNode {
        $doc = $method->getDocComment() ?: '';

        if ($doc === '') {
            return $node;
        }

        $docType = $this->docBlockParser->extractReturn($doc);

        if ($docType === null) {
            return $node;
        }

        try {
            return $this->typeMapper->fromString($docType, $context);
        } catch (\Throwable) {
            return $node;
        }
    }

    private function resolveHttpMethod(object $route): string
    {
        $methods = $route->methods();
      
        foreach ($methods as $method) {
            if ($method !== 'HEAD') {
                return strtoupper($method);
            }
        }

        return 'GET';
    }

    private function extractPathParams(string $path): array
    {
        preg_match_all('/\{([^}?]+)\??}/', $path, $matches);
        return $matches[1];
    }

  
    private function parseAction(string $action): array
    {
      
        if (!str_contains($action, '@')) {
            return [null, null];
        }

        [$controller, $method] = explode('@', $action, 2);
        return [$controller ?: null, $method ?: null];
    }

    private function endpointAlreadyRegistered(
        ApiSchema $schema,
        string $controllerFqcn,
        string $methodName,
    ): bool {
        foreach ($schema->endpoints() as $endpoint) {
            if ($endpoint->controllerFqcn() === $controllerFqcn
                && $endpoint->methodName() === $methodName
            ) {
                return true;
            }
        }

        return false;
    }
}
