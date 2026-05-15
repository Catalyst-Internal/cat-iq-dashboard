<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('github_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 64);
            $table->string('action', 64)->nullable();
            $table->string('github_delivery', 64)->nullable();
            $table->timestamps();

            $table->index(['repository_id', 'created_at']);
            $table->index('github_delivery');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_webhook_events');
    }
};
