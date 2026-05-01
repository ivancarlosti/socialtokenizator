<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('r2_key');
            $table->string('original_filename')->nullable();
            $table->string('mime_type', 64)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });

        // Fulltext index for description search (MySQL 5.7+ / 8 InnoDB)
        \DB::statement('ALTER TABLE images ADD FULLTEXT description_fulltext (description)');
    }

    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};
