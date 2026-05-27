<?php

declare(strict_types=1);

namespace PHPTypeS\BridgeLaravel\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use PHPTypeS\Bridge\Config\BridgeConfig;
use PHPTypeS\Bridge\Schema\Nodes\ScalarTypeNode;
use PHPTypeS\BridgeLaravel\Support\LaravelConfigAdapter;

final class LaravelConfigAdapterTest extends TestCase
{
    private LaravelConfigAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new LaravelConfigAdapter();
    }

    // ── BridgeConfig returned ─────────────────────────────────────────────────

    public function test_returns_bridge_config_instance(): void
    {
        $config = $this->adapter->fromLaravelConfig([]);
        $this->assertInstanceOf(BridgeConfig::class, $config);
    }

    // ── source_dirs ───────────────────────────────────────────────────────────

    public function test_source_dirs_filters_non_existent_directories(): void
    {
        $config = $this->adapter->fromLaravelConfig([
            'source_dirs' => ['/does/not/exist', sys_get_temp_dir()],
        ]);

        // Only the real tmp dir survives the filter
        $this->assertContains(sys_get_temp_dir(), $config->sourceDirs());
        $this->assertNotContains('/does/not/exist', $config->sourceDirs());
    }

    public function test_empty_source_dirs_returns_empty_array(): void
    {
        $config = $this->adapter->fromLaravelConfig(['source_dirs' => []]);
        $this->assertSame([], $config->sourceDirs());
    }

    // ── output_dir ────────────────────────────────────────────────────────────

    public function test_output_dir_is_set_from_config(): void
    {
        $config = $this->adapter->fromLaravelConfig([
            'output_dir' => '/var/www/html/resources/js/api',
        ]);

        $this->assertSame('/var/www/html/resources/js/api', $config->outputDir());
    }

    // ── generators ────────────────────────────────────────────────────────────

    public function test_generators_defaults_to_all_three(): void
    {
        $config = $this->adapter->fromLaravelConfig([]);

        $this->assertSame(['types', 'schemas', 'client'], $config->generators());
    }

    public function test_generators_respects_user_selection(): void
    {
        $config = $this->adapter->fromLaravelConfig([
            'generators' => ['types', 'schemas'],
        ]);

        $this->assertSame(['types', 'schemas'], $config->generators());
        $this->assertFalse($config->shouldRunGenerator('client'));
    }

    public function test_invalid_generator_names_are_filtered_out(): void
    {
        $config = $this->adapter->fromLaravelConfig([
            'generators' => ['types', 'invalid-name', 'client'],
        ]);

        $this->assertSame(['types', 'client'], $config->generators());
    }

    // ── base_url ──────────────────────────────────────────────────────────────

    public function test_base_url_is_set(): void
    {
        $config = $this->adapter->fromLaravelConfig([
            'base_url' => 'https://api.example.com',
        ]);

        $this->assertSame('https://api.example.com', $config->baseUrl());
    }

    public function test_base_url_defaults_to_empty_string(): void
    {
        $config = $this->adapter->fromLaravelConfig([]);
        $this->assertSame('', $config->baseUrl());
    }

    // ── module_format ─────────────────────────────────────────────────────────

    public function test_module_format_esm_is_accepted(): void
    {
        $config = $this->adapter->fromLaravelConfig(['module_format' => 'esm']);
        $this->assertSame('esm', $config->moduleFormat());
    }

    public function test_module_format_cjs_is_accepted(): void
    {
        $config = $this->adapter->fromLaravelConfig(['module_format' => 'cjs']);
        $this->assertSame('cjs', $config->moduleFormat());
    }

    public function test_invalid_module_format_falls_back_to_esm(): void
    {
        $config = $this->adapter->fromLaravelConfig(['module_format' => 'amd']);
        $this->assertSame('esm', $config->moduleFormat());
    }

    // ── type_aliases ──────────────────────────────────────────────────────────

    public function test_string_type_alias_is_converted_to_scalar_node(): void
    {
        $config = $this->adapter->fromLaravelConfig([
            'type_aliases' => [
                'Carbon\\Carbon' => 'string',
            ],
        ]);

        $aliases = $config->typeAliases();
        $this->assertArrayHasKey('Carbon\\Carbon', $aliases);
        $this->assertInstanceOf(ScalarTypeNode::class, $aliases['Carbon\\Carbon']);
        $this->assertSame('string', $aliases['Carbon\\Carbon']->phpType());
    }

    public function test_multiple_type_aliases_all_resolved(): void
    {
        $config = $this->adapter->fromLaravelConfig([
            'type_aliases' => [
                'Carbon\\Carbon'          => 'string',
                'Carbon\\CarbonImmutable' => 'string',
            ],
        ]);

        $this->assertCount(2, $config->typeAliases());
    }

    public function test_unresolvable_type_alias_falls_back_to_mixed_node(): void
    {
        $config = $this->adapter->fromLaravelConfig([
            'type_aliases' => [
                'Some\\Unknown\\ValueObject' => 'SomeComplexUnresolvableType',
            ],
        ]);

        $aliases = $config->typeAliases();
        $this->assertArrayHasKey('Some\\Unknown\\ValueObject', $aliases);
        // Falls back to ScalarTypeNode('mixed')
        $this->assertInstanceOf(ScalarTypeNode::class, $aliases['Some\\Unknown\\ValueObject']);
    }

    public function test_empty_type_aliases_returns_empty_array(): void
    {
        $config = $this->adapter->fromLaravelConfig(['type_aliases' => []]);
        $this->assertSame([], $config->typeAliases());
    }

    public function test_non_string_alias_entries_are_skipped(): void
    {
        $config = $this->adapter->fromLaravelConfig([
            'type_aliases' => [
                'ValidClass' => 'string',
                123          => 'number',     // invalid key
                'AnotherClass' => null,        // invalid value
            ],
        ]);

        // Only the valid string => string entry survives
        $this->assertArrayHasKey('ValidClass', $config->typeAliases());
        $this->assertCount(1, $config->typeAliases());
    }
}
