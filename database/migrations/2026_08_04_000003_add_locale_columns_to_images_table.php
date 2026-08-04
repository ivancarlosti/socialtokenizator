<?php

use App\Models\Category;
use App\Models\Tag;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Drop the old fulltext index first
        DB::statement('ALTER TABLE images DROP INDEX IF EXISTS description_fulltext');

        Schema::table('images', function (Blueprint $table) {
            $table->string('headline_en', 300)->nullable()->after('headline');
            $table->string('headline_es', 300)->nullable()->after('headline_en');
            $table->string('headline_pt_BR', 300)->nullable()->after('headline_es');
            $table->text('description_en')->nullable()->after('headline_pt_BR');
            $table->text('description_es')->nullable()->after('description_en');
            $table->text('description_pt_BR')->nullable()->after('description_es');
        });

        // Migrate existing data: headline → headline_en, description → description_en
        DB::statement("UPDATE images SET headline_en = headline WHERE headline IS NOT NULL AND headline != ''");
        DB::statement("UPDATE images SET description_en = description WHERE description IS NOT NULL AND description != ''");

        // Drop old columns
        Schema::table('images', function (Blueprint $table) {
            $table->dropColumn('headline');
            $table->dropColumn('description');
        });

        // Create fulltext indexes on locale description columns
        DB::statement('ALTER TABLE images ADD FULLTEXT description_en_fulltext (description_en)');
        DB::statement('ALTER TABLE images ADD FULLTEXT description_es_fulltext (description_es)');
        DB::statement('ALTER TABLE images ADD FULLTEXT description_pt_BR_fulltext (description_pt_BR)');

        // Migrate existing tags → categories (handle = tag name, name_en = tag name humanized)
        $tags = Tag::all();
        foreach ($tags as $tag) {
            $humanized = ucwords(str_replace('-', ' ', $tag->name));
            $category = Category::firstOrCreate(
                ['handle' => $tag->name],
                ['name_en' => $humanized]
            );

            // Link all images that had this tag to the new category
            $imageIds = DB::table('image_tag')
                ->where('tag_id', $tag->id)
                ->pluck('image_id');

            foreach ($imageIds as $imageId) {
                DB::table('category_image')->insertOrIgnore([
                    'category_id' => $category->id,
                    'image_id' => $imageId,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE images DROP INDEX IF EXISTS description_en_fulltext');
        DB::statement('ALTER TABLE images DROP INDEX IF EXISTS description_es_fulltext');
        DB::statement('ALTER TABLE images DROP INDEX IF EXISTS description_pt_BR_fulltext');

        Schema::table('images', function (Blueprint $table) {
            $table->text('headline')->nullable()->after('height');
            $table->text('description')->nullable()->after('headline');
        });

        // Restore data
        DB::statement("UPDATE images SET headline = headline_en WHERE headline_en IS NOT NULL AND headline_en != ''");
        DB::statement("UPDATE images SET description = description_en WHERE description_en IS NOT NULL AND description_en != ''");

        Schema::table('images', function (Blueprint $table) {
            $table->dropColumn([
                'headline_en', 'headline_es', 'headline_pt_BR',
                'description_en', 'description_es', 'description_pt_BR',
            ]);
        });

        DB::statement('ALTER TABLE images ADD FULLTEXT description_fulltext (description)');
    }
};
