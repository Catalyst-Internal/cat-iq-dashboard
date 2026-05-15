<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('github_id')->unique();
            $table->string('title');
            $table->string('state', 16);
            $table->date('due_on')->nullable();
            $table->unsignedInteger('open_issues')->default(0);
            $table->unsignedInteger('closed_issues')->default(0);
            $table->timestamps();

            $table->index(['repository_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milestones');
    }
};
