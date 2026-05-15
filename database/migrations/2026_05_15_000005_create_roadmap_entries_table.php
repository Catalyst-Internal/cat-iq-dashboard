<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roadmap_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->string('phase');
            $table->string('milestone_title')->nullable();
            $table->string('item_text');
            $table->string('item_type', 16)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['repository_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roadmap_entries');
    }
};
