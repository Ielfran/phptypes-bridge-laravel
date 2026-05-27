<?php

return [
    'source_dirs' => [
        app_path('Http/Controllers'),
        app_path('DTOs'),
    ],
    'output_dir'   => resource_path('js/api'),
    'generators'   => ['types', 'schemas', 'client'],
    'base_url'     => env('APP_URL', ''),
    'scan_routes'  => false,
    'route_file'   => 'routes/api.php',
    'type_aliases' => [
        'Carbon\Carbon'             => 'string',
        'Carbon\CarbonImmutable'    => 'string',
        'Illuminate\Support\Carbon' => 'string',
    ],
    'exclude' => [],
];
