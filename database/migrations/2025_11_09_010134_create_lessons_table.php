<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->foreignId('section_id')->constrained('course_sections')->onDelete('cascade');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->enum('type', ['video', 'document', 'quiz', 'text'])->default('video');
            $table->text('content')->nullable();
            $table->string('video_url')->nullable();
            $table->string('video_platform')->nullable(); // youtube, vimeo, dailymotion
            $table->string('video_id')->nullable();
            $table->integer('duration')->default(0); // in seconds
            $table->boolean('is_preview')->default(false);
            $table->boolean('is_free')->default(false);
            $table->integer('order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'section_id', 'order']);
            $table->index(['is_preview', 'is_free']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
