<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\{User, Course, Enrollment};
use App\Services\EgyptianPaymentGatewayService;
use Mockery;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_fawry_payment_generation()
    {
        $user = User::factory()->student()->create();
        $course = Course::factory()->create(['price' => 199]);

        $service = new EgyptianPaymentGatewayService();
        $result = $service->enroll($user, $course);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['payment_url']);
        $this->assertNotNull($result['reference']);

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'payment_status' => 'pending',
        ]);
    }

    public function test_payment_verification()
    {
        $enrollment = Enrollment::factory()->create([
            'payment_status' => 'pending',
            'transaction_id' => 'TEST_REF',
        ]);

        $service = Mockery::mock(EgyptianPaymentGatewayService::class);
        $service->shouldReceive('verifyPayment')
            ->with('TEST_REF', $enrollment->id)
            ->andReturn(true);

        $response = $this->postJson('/api/v1/verify-payment', [
            'reference' => 'TEST_REF',
            'enrollment_id' => $enrollment->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'payment_status' => 'completed',
        ]);
    }
}
