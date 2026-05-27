<?php

declare(strict_types=1);

namespace PHPTypeS\BridgeLaravel\Console;

use Illuminate\Console\Command;

final class BridgeInitCommand extends Command
{
    protected $signature = 'phptypes:init';

    protected $description = 'Publish the phptypes config file to config/phptypes.php';

    public function handle(): int
    {
        $target = config_path('phptypes.php');

        if (file_exists($target)) {
            $this->warn('config/phptypes.php already exists — not overwriting.');
            $this->line('Edit it directly or delete it and run phptypes:init again.');
            return self::SUCCESS;
        }

        $source = __DIR__ . '/../../config/phptypes.php';

        if (!file_exists($source)) {
            $this->error('Source config file not found. Try: php artisan vendor:publish --tag=phptypes-config');
            return self::FAILURE;
        }

        copy($source, $target);

        $this->info('Published config/phptypes.php');
        $this->newLine();
        $this->line('Next steps:');
        $this->line('  1. Edit <comment>config/phptypes.php</comment> — set source_dirs and output_dir');
        $this->line('  2. Run <info>php artisan phptypes:generate</info>');
        $this->line('  3. Add <info>php artisan phptypes:validate</info> to your CI pipeline');

        return self::SUCCESS;
    }
}