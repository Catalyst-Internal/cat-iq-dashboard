<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wiki_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('title');
            $table->longText('content_md');
            $table->string('sha', 64);
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique(['repository_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wiki_pages');
    }
};
