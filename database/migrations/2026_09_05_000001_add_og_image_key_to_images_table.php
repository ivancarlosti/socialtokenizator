<?php

use App\Models\Image;
use App\Support\OgImageProcessor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->string('og_image_key')->nullable()->after('r2_key');
        });

        // Backfill thumbnails for every existing post. This runs inside
        // `php artisan migrate` and can take a long time on large catalogs
        // because each post requires an R2 download, GD processing, and an
        // R2 upload. Individual failures are logged and skipped so a single
        // broken image does not abort the whole migration.
        if (! self::gdAvailable()) {
            Log::warning('OgImageProcessor: GD unavailable; skipping thumbnail backfill.');

            return;
        }

        Image::query()
            ->whereNull('og_image_key')
            ->select(['id', 'uuid', 'r2_key', 'mime_type', 'og_image_key'])
            ->chunkById(50, function ($images) {
                foreach ($images as $image) {
                    try {
                        $key = OgImageProcessor::generate($image);
                        if ($key !== null) {
                            $image->update(['og_image_key' => $key]);
                        }
                    } catch (\Throwable $e) {
                        Log::warning('OgImageProcessor: backfill failed for image.', [
                            'image_id' => $image->id,
                            'error'    => $e->getMessage(),
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropColumn('og_image_key');
        });
    }

    private static function gdAvailable(): bool
    {
        return function_exists('imagecreatetruecolor')
            && function_exists('imagecopyresampled')
            && function_exists('imagejpeg');
    }
};
