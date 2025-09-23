<?php

namespace Sdw\LivewireMaps\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class PublishAssetsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'livewire-maps:publish-assets {--force : Overwrite any existing files}';

    /**
     * The console command description.
     */
    protected $description = 'Publish the Livewire Maps JS asset to public/vendor, optionally overwriting any existing file.';

    public function handle(Filesystem $files): int
    {
        $source = __DIR__ . '/../../../resources/js/livewire-maps.js';
        $destinationDir = public_path('vendor/livewire-maps');
        $destination = $destinationDir . '/livewire-maps.js';

        if (! file_exists($source)) {
            $this->error('Source asset not found: ' . $source);
            return self::FAILURE;
        }

        // Ensure destination directory exists
        if (! is_dir($destinationDir)) {
            $files->makeDirectory($destinationDir, 0755, true);
        }

        if ($files->exists($destination) && ! $this->option('force')) {
            $this->warn('Asset already exists. Use --force to overwrite: ' . $destination);
            return self::SUCCESS;
        }

        try {
            $files->copy($source, $destination);
        } catch (\Throwable $e) {
            $this->error('Failed to publish asset: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info('Published asset to: ' . $destination . ($this->option('force') ? ' (overwritten)' : ''));
        return self::SUCCESS;
    }
}
