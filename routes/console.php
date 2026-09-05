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

    $bar = $this->getOutput()->createProgressBar($total);
    $bar->start();

    $query
        ->select(['id', 'uuid', 'r2_key', 'mime_type', 'og_image_key'])
        ->chunkById(50, function ($images) use ($bar) {
            foreach ($images as $image) {
                try {
                    $key = OgImageProcessor::generate($image);
                    if ($key !== null) {
                        $image->update(['og_image_key' => $key]);
                    }
                } catch (\Throwable $e) {
                    $this->error('Failed for image #'.$image->id.': '.$e->getMessage());
                }

                $bar->advance();
            }
        });

    $bar->finish();
    $this->newLine();
    $this->info('Done.');

    return 0;
})->purpose('Generate 1200x630 OpenGraph thumbnails for posts');
