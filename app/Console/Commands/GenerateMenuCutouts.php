<?php

namespace App\Console\Commands;

use App\Jobs\GenerateMenuCutout;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RestaurantMenuItem;
use App\Services\Restaurant\MenuCutoutService;
use Illuminate\Console\Command;
use Throwable;

class GenerateMenuCutouts extends Command
{
    protected $signature = 'menu:cutouts
        {--sync : Process files now (useful for small catalogs and initial setup)}
        {--force : Regenerate even when a transparent PNG already exists}
        {--limit=0 : Process at most N distinct product photos; 0 means all}';

    protected $description = 'Create transparent, non-destructive cutouts for all ERP product and restaurant menu photos.';

    public function handle(MenuCutoutService $cutouts): int
    {
        if (! $cutouts->enabled()) {
            $this->error('Set TISH_CUTOUTS_ENABLED=true in .env first, then php artisan config:clear.');
            return self::FAILURE;
        }

        $limit = max(0, (int) $this->option('limit'));
        $force = (bool) $this->option('force');
        $sync = (bool) $this->option('sync');
        $seen = [];
        $queued = 0;
        $skipped = 0;
        $failed = 0;

        foreach ([Product::class, RestaurantMenuItem::class, ProductVariant::class] as $model) {
            foreach ($model::query()->whereNotNull('image')->where('image', '<>', '')->orderBy('id')->cursor() as $row) {
                $source = trim((string) $row->image);
                if ($source === '' || isset($seen[$source])) {
                    continue;
                }
                $seen[$source] = true;

                if (! $cutouts->needsGeneration($source, $force)) {
                    $skipped++;
                    continue;
                }

                try {
                    if ($sync) {
                        $cutouts->generate($source, $force);
                    } else {
                        GenerateMenuCutout::dispatch($source, $force)
                            ->onConnection((string) config('menu_cutouts.queue_connection', 'database'))
                            ->onQueue('menu-cutouts')
                            ->afterCommit();
                    }
                    $queued++;
                } catch (Throwable $exception) {
                    $failed++;
                    $this->warn($source.': '.$exception->getMessage());
                }

                if ($limit > 0 && $queued >= $limit) {
                    break 2;
                }
            }
        }

        $this->info(($sync ? 'Processed' : 'Queued').": {$queued}; skipped: {$skipped}; errors: {$failed}.");
        $this->line('Unprocessed originals remain fully available in the public menu.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
