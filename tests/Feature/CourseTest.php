<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\{User, Course, Category};

class CourseTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_published_courses()
    {
        $course = Course::factory()->published()->create();

        $response = $this->getJson('/api/v1/courses');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_search_courses()
    {
        $course = Course::factory()->published()->create(['title' => 'Laravel Basics']);

        $response = $this->getJson('/api/v1/courses?search=Laravel');

        $response->assertStatus(200)
            ->assertJsonFragment(['title' => 'Laravel Basics']);
    }

    public function test_instructor_can_create_course()
    {
        $this->actingAs(User::factory()->instructor()->create());

        $category = Category::factory()->create();

        $response = $this->postJson('/api/v1/courses', [
            'title' => 'Test Course',
            'description' => 'Test Description',
            'level' => 'beginner',
            'language' => 'arabic',
            'price' => 99,
            'category_id' => $category->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Test Course');
    }

    public function test_student_can_enroll_in_course()
    {
        $user = User::factory()->student()->create();
        $this->actingAs($user);

        $course = Course::factory()->published()->create(['price' => 0]);

        $response = $this->postJson("/api/v1/courses/{$course->slug}/enroll");

        $response->assertStatus(201)
            ->assertJsonPath('data.enrollment.payment_status', 'completed');
    }
}
