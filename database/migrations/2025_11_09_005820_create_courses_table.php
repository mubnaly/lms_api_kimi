// database/migrations/2024_01_01_000002_create_courses_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained('users');
            $table->foreignId('category_id')->constrained('categories');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('subtitle')->nullable();
            $table->text('description');
            $table->text('requirements')->nullable();
            $table->text('outcomes')->nullable();
            $table->string('level')->enum('level', ['beginner', 'intermediate', 'advanced', 'all']);
            $table->string('language')->default('english');
            $table->decimal('price', 10, 2)->default(0.00);
            $table->decimal('discount_price', 10, 2)->nullable();
            $table->integer('duration')->default(0); // in minutes
            $table->boolean('is_published')->default(false);
            $table->boolean('is_approved')->default(false);
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected'])->default('draft');
            $table->integer('students_count')->default(0);
            $table->decimal('rating', 3, 2)->default(0.00);
            $table->integer('reviews_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'is_approved', 'status']);
            $table->index(['rating', 'students_count']);
            $table->fullText(['title', 'subtitle', 'description']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
