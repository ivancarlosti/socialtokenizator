<?php

use App\Models\Image;
use App\Support\ShortId;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->string('short_id', 64)->nullable()->after('id');
        });

        // Backfill existing posts with stable short IDs (defaults: 6 lowercase letters).
        foreach (Image::query()->select(['id'])->cursor() as $image) {
            Image::query()->whereKey($image->id)->update([
                'short_id' => ShortId::unique(),
            ]);
        }

        Schema::table('images', function (Blueprint $table) {
            $table->unique('short_id');
        });
    }

    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropUnique(['short_id']);
            $table->dropColumn('short_id');
        });
    }
};
