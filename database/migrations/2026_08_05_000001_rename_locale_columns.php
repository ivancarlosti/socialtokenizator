<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Categories table: rename name_en → name_en_US, name_es → name_es_MX
        Schema::table('categories', function (Blueprint $table) {
            $table->string('name_en_US', 128)->nullable()->after('handle');
            $table->string('name_es_MX', 128)->nullable()->after('name_en_US');
        });

        DB::statement("UPDATE categories SET name_en_US = name_en WHERE name_en IS NOT NULL AND name_en != ''");
        DB::statement("UPDATE categories SET name_es_MX = name_es WHERE name_es IS NOT NULL AND name_es != ''");

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'name_es']);
        });

        // Images table: drop old fulltext indexes first
        DB::statement('ALTER TABLE images DROP INDEX IF EXISTS description_en_fulltext');
        DB::statement('ALTER TABLE images DROP INDEX IF EXISTS description_es_fulltext');

        // Rename headline columns
        Schema::table('images', function (Blueprint $table) {
            $table->string('headline_en_US', 300)->nullable()->after('headline_pt_BR');
            $table->string('headline_es_MX', 300)->nullable()->after('headline_en_US');
        });

        DB::statement("UPDATE images SET headline_en_US = headline_en WHERE headline_en IS NOT NULL AND headline_en != ''");
        DB::statement("UPDATE images SET headline_es_MX = headline_es WHERE headline_es IS NOT NULL AND headline_es != ''");

        Schema::table('images', function (Blueprint $table) {
            $table->dropColumn(['headline_en', 'headline_es']);
        });

        // Rename description columns
        Schema::table('images', function (Blueprint $table) {
            $table->text('description_en_US')->nullable()->after('headline_es_MX');
            $table->text('description_es_MX')->nullable()->after('description_en_US');
        });

        DB::statement("UPDATE images SET description_en_US = description_en WHERE description_en IS NOT NULL AND description_en != ''");
        DB::statement("UPDATE images SET description_es_MX = description_es WHERE description_es IS NOT NULL AND description_es != ''");

        Schema::table('images', function (Blueprint $table) {
            $table->dropColumn(['description_en', 'description_es']);
        });

        // Recreate fulltext indexes with new column names
        DB::statement('ALTER TABLE images ADD FULLTEXT description_en_US_fulltext (description_en_US)');
        DB::statement('ALTER TABLE images ADD FULLTEXT description_es_MX_fulltext (description_es_MX)');
    }

    public function down(): void
    {
        // Images: drop new fulltext indexes
        DB::statement('ALTER TABLE images DROP INDEX IF EXISTS description_en_US_fulltext');
        DB::statement('ALTER TABLE images DROP INDEX IF EXISTS description_es_MX_fulltext');

        // Restore old image columns
        Schema::table('images', function (Blueprint $table) {
            $table->string('headline_en', 300)->nullable()->after('headline_pt_BR');
            $table->string('headline_es', 300)->nullable()->after('headline_en');
        });

        DB::statement("UPDATE images SET headline_en = headline_en_US WHERE headline_en_US IS NOT NULL AND headline_en_US != ''");
        DB::statement("UPDATE images SET headline_es = headline_es_MX WHERE headline_es_MX IS NOT NULL AND headline_es_MX != ''");

        Schema::table('images', function (Blueprint $table) {
            $table->dropColumn(['headline_en_US', 'headline_es_MX']);
        });

        Schema::table('images', function (Blueprint $table) {
            $table->text('description_en')->nullable()->after('headline_es');
            $table->text('description_es')->nullable()->after('description_en');
        });

        DB::statement("UPDATE images SET description_en = description_en_US WHERE description_en_US IS NOT NULL AND description_en_US != ''");
        DB::statement("UPDATE images SET description_es = description_es_MX WHERE description_es_MX IS NOT NULL AND description_es_MX != ''");

        Schema::table('images', function (Blueprint $table) {
            $table->dropColumn(['description_en_US', 'description_es_MX']);
        });

        // Recreate old fulltext indexes
        DB::statement('ALTER TABLE images ADD FULLTEXT description_en_fulltext (description_en)');
        DB::statement('ALTER TABLE images ADD FULLTEXT description_es_fulltext (description_es)');

        // Categories: restore old columns
        Schema::table('categories', function (Blueprint $table) {
            $table->string('name_en', 128)->nullable()->after('handle');
            $table->string('name_es', 128)->nullable()->after('name_en');
        });

        DB::statement("UPDATE categories SET name_en = name_en_US WHERE name_en_US IS NOT NULL AND name_en_US != ''");
        DB::statement("UPDATE categories SET name_es = name_es_MX WHERE name_es_MX IS NOT NULL AND name_es_MX != ''");

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['name_en_US', 'name_es_MX']);
        });
    }
};
