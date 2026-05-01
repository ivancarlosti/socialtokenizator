<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('image_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('url', 1024);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['image_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
