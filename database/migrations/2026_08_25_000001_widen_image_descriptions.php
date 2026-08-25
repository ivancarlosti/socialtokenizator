<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Drop fulltext indexes before changing the column types
        DB::statement('ALTER TABLE images DROP INDEX IF EXISTS description_en_US_fulltext');
        DB::statement('ALTER TABLE images DROP INDEX IF EXISTS description_es_MX_fulltext');
        DB::statement('ALTER TABLE images DROP INDEX IF EXISTS description_pt_BR_fulltext');

        Schema::table('images', function (Blueprint $table) {
            $table->mediumText('description_en_US')->nullable()->change();
            $table->mediumText('description_es_MX')->nullable()->change();
            $table->mediumText('description_pt_BR')->nullable()->change();
        });

        // Recreate the fulltext indexes on the widened columns
        DB::statement('ALTER TABLE images ADD FULLTEXT description_en_US_fulltext (description_en_US)');
        DB::statement('ALTER TABLE images ADD FULLTEXT description_es_MX_fulltext (description_es_MX)');
        DB::statement('ALTER TABLE images ADD FULLTEXT description_pt_BR_fulltext (description_pt_BR)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE images DROP INDEX IF EXISTS description_en_US_fulltext');
        DB::statement('ALTER TABLE images DROP INDEX IF EXISTS description_es_MX_fulltext');
        DB::statement('ALTER TABLE images DROP INDEX IF EXISTS description_pt_BR_fulltext');

        Schema::table('images', function (Blueprint $table) {
            $table->text('description_en_US')->nullable()->change();
            $table->text('description_es_MX')->nullable()->change();
            $table->text('description_pt_BR')->nullable()->change();
        });

        DB::statement('ALTER TABLE images ADD FULLTEXT description_en_US_fulltext (description_en_US)');
        DB::statement('ALTER TABLE images ADD FULLTEXT description_es_MX_fulltext (description_es_MX)');
        DB::statement('ALTER TABLE images ADD FULLTEXT description_pt_BR_fulltext (description_pt_BR)');
    }
};
