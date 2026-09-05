<?php

use App\Models\Image;
use App\Support\OgImageProcessor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Output\ConsoleOutput;

return new class extends Migration {
    /**
     * The backfill can take a long time, so it must not be wrapped in a single
     * database transaction. Each image update commits independently, which also
     * keeps the migration resumable if it is interrupted part-way through.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasColumn('images', 'og_image_key')) {
            Schema::table('images', function (Blueprint $table) {
                $table->string('og_image_key')->nullable()->after('r2_key');
            });
        }

        if (! self::gdAvailable()) {
            Log::warning('OgImageProcessor: GD unavailable; skipping thumbnail backfill.');

            return;
        }

        $total = Image::query()->whereNull('og_image_key')->count();
        $output = new ConsoleOutput();

        $output->writeln("Generating OpenGraph thumbnails for {$total} posts...");

        if ($total === 0) {
            $output->writeln('No OpenGraph thumbnails to generate.');

            return;
        }

        $done = 0;

        Image::query()
            ->whereNull('og_image_key')
            ->select(['id', 'uuid', 'r2_key', 'mime_type', 'og_image_key'])
            ->chunkById(50, function ($images) use ($output, $total, &$done) {
                foreach ($images as $image) {
                    $done++;

                    try {
                        $key = OgImageProcessor::generate($image);
                        if ($key !== null) {
                            $image->update(['og_image_key' => $key]);
                            $output->writeln("[{$done}/{$total}] Generated OpenGraph thumbnail for image #{$image->id}");
                        } else {
                            $output->writeln("[{$done}/{$total}] Skipped image #{$image->id} (unable to decode/generate)");
                        }
                    } catch (\Throwable $e) {
                        Log::warning('OgImageProcessor: backfill failed for image.', [
                            'image_id' => $image->id,
                            'error'    => $e->getMessage(),
                        ]);
                        $output->writeln("[{$done}/{$total}] Failed image #{$image->id}: {$e->getMessage()}");
                    }
                }
            });

        $output->writeln('OpenGraph thumbnail backfill finished.');
    }

    public function down(): void
    {
        if (Schema::hasColumn('images', 'og_image_key')) {
            Schema::table('images', function (Blueprint $table) {
                $table->dropColumn('og_image_key');
            });
        }
    }

    private static function gdAvailable(): bool
    {
        return function_exists('imagecreatetruecolor')
            && function_exists('imagecopyresampled')
            && function_exists('imagejpeg');
    }
};
