<?php

namespace Tests\Feature;

use App\Models\Rental;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MidtransIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_midtrans_service_builds_snap_payload_for_rental(): void
    {
        $user = User::factory()->create([
            'name' => 'Abdan',
            'email' => 'abdan@example.com',
        ]);

        $rental = Rental::create([
            'user_id' => $user->id,
            'rental_code' => 'RENT-TEST-001',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'total_price' => 250000,
            'payment_status' => 'unpaid',
            'rental_status' => 'pending',
        ]);

        $service = new MidtransService();

        $payload = $service->buildSnapPayload(
            'RENTAL-' . $rental->id . '-' . time(),
            (float) $rental->total_price,
            [
                'first_name' => $user->name,
                'email' => $user->email,
            ]
        );

        $this->assertArrayHasKey('transaction_details', $payload);
        $this->assertSame(250000, $payload['transaction_details']['gross_amount']);
        $this->assertSame('abdan@example.com', $payload['customer_details']['email']);
    }
}
