<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $map = [
        'PHPTypeS\\BridgeLaravel\\Tests\\Fixtures\\' => __DIR__ . '/Fixtures/',
        'PHPTypeS\\BridgeLaravel\\Scanner\\'             => __DIR__ . '/../src/Scanner/',
        'PHPTypeS\\BridgeLaravel\\Tests\\'           => __DIR__ . '/',
        'PHPTypeS\\BridgeLaravel\\'                  => __DIR__ . '/../src/',
        'PHPTypeS\\Bridge\\Tests\\'                  => __DIR__ . '/../../phptypes/tests/',
        'PHPTypeS\\Bridge\\'                         => __DIR__ . '/../../phptypes/src/',
    ];

    foreach ($map as $prefix => $base) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }
        $relative = substr($class, strlen($prefix));
        $file = $base . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

// Pull in core bridge autoloader (Symfony Console + polyfills)
if (file_exists(__DIR__ . '/../../phptypes/autoload.php')) {
    require __DIR__ . '/../../phptypes/autoload.php';
}

// Minimal Illuminate stubs so tests run without a full Laravel install
if (!interface_exists('Illuminate\Contracts\Foundation\Application')) {
    require __DIR__ . '/Stubs/IlluminateStubs.php';
}
