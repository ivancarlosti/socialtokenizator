<?php

use App\Models\Image;
use App\Support\OgImageProcessor;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('images:generate-og {--fresh : Regenerate thumbnails for all posts instead of only missing ones}', function () {
    $fresh = (bool) $this->option('fresh');

    $query = Image::query();
    if (! $fresh) {
        $query->whereNull('og_image_key');
    }

    $total = (clone $query)->count();
    $this->info("Generating OpenGraph thumbnails for {$total} posts...");

    if ($total === 0) {
        $this->info('No OpenGraph thumbnails to generate.');

        return 0;
    }

    $done = 0;

    $query
        ->select(['id', 'uuid', 'r2_key', 'mime_type', 'og_image_key'])
        ->chunkById(50, function ($images) use ($total, &$done) {
            foreach ($images as $image) {
                $done++;

                try {
                    $key = OgImageProcessor::generate($image);
                    if ($key !== null) {
                        $image->update(['og_image_key' => $key]);
                        $this->info("[{$done}/{$total}] Generated OpenGraph thumbnail for image #{$image->id}");
                    } else {
                        $this->warn("[{$done}/{$total}] Skipped image #{$image->id} (unable to decode/generate)");
                    }
                } catch (\Throwable $e) {
                    $this->error("[{$done}/{$total}] Failed image #{$image->id}: {$e->getMessage()}");
                }
            }
        });

    $this->info('Done.');

    return 0;
})->purpose('Generate 1200x630 OpenGraph thumbnails for posts');
