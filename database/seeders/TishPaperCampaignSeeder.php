<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class TishPaperCampaignSeeder extends Seeder
{
    public function run(): void
    {
        $artwork = [
            'american-breakfast.webp' => 'فطور أمريكي',
            'double-smash.webp' => 'سماش على أصوله',
            'breakfast-to-dinner.webp' => 'من الصبح للمسا',
        ];

        DB::transaction(function () use ($artwork): void {
            $disk = Storage::disk('public');
            $sortOrder = (int) Banner::query()->max('sort_order') + 1;

            foreach ($artwork as $file => $title) {
                $image = 'banners/tish-paper-campaign/'.$file;

                if (! $disk->exists($image)) {
                    $source = public_path('assets/tish-paper/campaign/'.$file);
                    $contents = file_get_contents($source);

                    if ($contents === false || ! $disk->put($image, $contents)) {
                        throw new RuntimeException('Could not import TISH banner: '.$file);
                    }
                }

                Banner::query()->firstOrCreate(
                    ['image' => $image],
                    ['title' => $title, 'is_active' => true, 'sort_order' => $sortOrder]
                );
                $sortOrder++;
            }
        });
    }
}
