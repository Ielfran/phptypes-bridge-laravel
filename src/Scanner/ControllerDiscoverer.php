<?php

declare(strict_types=1);

namespace PHPTypeS\BridgeLaravel\Scanner;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;


final class ControllerDiscoverer
{
   
    public function __construct(
        private readonly array $excludedPrefixes = [],
    ) {}

    
    public function discover(array $directories): array
    {
        $classes = [];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            foreach ($this->findPhpFiles($dir) as $file) {
                $fqcn = $this->extractClassName($file);

                if ($fqcn === null || $this->isExcluded($fqcn)) {
                    continue;
                }

                if (!class_exists($fqcn)) {
                    continue;
                }

                try {
                    $ref = new ReflectionClass($fqcn);
                } catch (\ReflectionException) {
                    continue;
                }

                if ($ref->isAbstract() || $ref->isInterface() || $ref->isTrait()) {
                    continue;
                }

                $classes[] = $ref;
            }
        }

        return $classes;
    }

    private function findPhpFiles(string $dir): array
    {
        $files    = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getRealPath();
            }
        }

        return $files;
    }

    private function extractClassName(string $path): ?string
    {
        $contents = file_get_contents($path);

        if (!$contents) {
            return null;
        }

        $tokens    = token_get_all($contents);
        $namespace = '';
        $className = null;
        $count     = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $ns = '';
                $i++;
                while ($i < $count) {
                    $t = $tokens[$i];
                    if (is_array($t) && in_array($t[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                        $ns .= $t[1];
                    } elseif ($t === ';' || $t === '{') {
                        break;
                    }
                    $i++;
                }
                $namespace = $ns;
                continue;
            }

            if (in_array($token[0], [T_CLASS, T_ENUM, T_INTERFACE, T_TRAIT], true)) {
                $j = $i + 1;
                while ($j < $count) {
                    $t = $tokens[$j];
                    if (is_array($t) && $t[0] === T_STRING) {
                        $className = $t[1];
                        break;
                    }
                    if (!is_array($t) || $t[0] !== T_WHITESPACE) {
                        break;
                    }
                    $j++;
                }
                break;
            }
        }

        if ($className === null) {
            return null;
        }

        return $namespace !== '' ? "{$namespace}\\{$className}" : $className;
    }

    private function isExcluded(string $fqcn): bool
    {
        foreach ($this->excludedPrefixes as $prefix) {
            if (str_starts_with($fqcn, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
