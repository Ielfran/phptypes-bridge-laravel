<?php

declare(strict_types=1);

namespace PHPTypeS\BridgeLaravel\Support;

use PHPTypeS\Bridge\Config\BridgeConfig;
use PHPTypeS\Bridge\Schema\Nodes\ScalarTypeNode;
use PHPTypeS\Bridge\Schema\Nodes\TypeNode;
use PHPTypeS\Bridge\TypeMapper\TypeMapper;

final class LaravelConfigAdapter
{
   
    public function fromLaravelConfig(array $config): BridgeConfig
    {
        return new BridgeConfig(
            sourceDirs:   $this->resolveSourceDirs($config),
            outputDir:    (string) ($config['output_dir'] ?? (function_exists('resource_path') ? resource_path('js/api') : '/resources/js/api')),
            generators:   $this->resolveGenerators($config),
            baseUrl:      (string) ($config['base_url'] ?? ''),
            moduleFormat: $this->resolveModuleFormat($config),
            typeAliases:  $this->resolveTypeAliases($config),
        );
    }

    private function resolveSourceDirs(array $config): array
    {
        
        if (!array_key_exists('source_dirs', $config)) {
            return [];
        }
        $dirs = (array) $config['source_dirs'];

        return array_values(array_filter($dirs, static function (string $dir): bool {
            if (!is_dir($dir)) {
                return false;
            }
            return true;
        }));
    }

    private function resolveGenerators(array $config): array
    {
        $valid = ['types', 'schemas', 'client'];
        $raw   = (array) ($config['generators'] ?? $valid);

        return array_values(array_intersect($raw, $valid)) ?: $valid;
    }

   
    private function resolveModuleFormat(array $config): string
    {
        $format = (string) ($config['module_format'] ?? 'esm');
        return in_array($format, ['esm', 'cjs'], true) ? $format : 'esm';
    }

   
    private function resolveTypeAliases(array $config): array
    {
        $raw     = (array) ($config['type_aliases'] ?? []);
        $aliases = [];
        $mapper  = new TypeMapper();

        foreach ($raw as $fqcn => $tsType) {
            if (!is_string($fqcn) || !is_string($tsType)) {
                continue;
            }

            try {
                $aliases[$fqcn] = $mapper->fromString($tsType, "type_aliases[{$fqcn}]");
            } catch (\Throwable) {
                $aliases[$fqcn] = new ScalarTypeNode('mixed');
            }
        }

        return $aliases;
    }
}
