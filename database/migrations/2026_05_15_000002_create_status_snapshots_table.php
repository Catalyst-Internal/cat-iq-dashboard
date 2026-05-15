<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->string('ci_state', 32)->default('none');
            $table->unsignedInteger('open_issues')->default(0);
            $table->unsignedInteger('open_prs')->default(0);
            $table->string('latest_release')->nullable();
            $table->string('latest_tag')->nullable();
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique('repository_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_snapshots');
    }
};
