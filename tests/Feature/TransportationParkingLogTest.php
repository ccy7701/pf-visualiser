<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransportationParkingLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_parking_logs_can_be_created_updated_deleted_and_returned_in_snapshot(): void
    {
        $casualResponse = $this->postJson(route('transportation-log.parking-logs.store'), [
            'parking_type' => 'casual',
            'location' => 'Mall',
            'parking_date' => '2026-06-12',
            'billing_month' => null,
            'start_hour' => 10,
            'end_hour' => 13,
            'total_amount' => 6.50,
            'notes' => 'Weekend errand',
        ]);

        $casualResponse->assertOk();
        $casualResponse->assertJsonPath('parkingLogs.0.location', 'Mall');
        $casualResponse->assertJsonPath('parkingLogs.0.start_hour', 10);
        $casualResponse->assertJsonPath('parkingLogs.0.end_hour', 13);

        $monthlyResponse = $this->postJson(route('transportation-log.parking-logs.store'), [
            'parking_type' => 'monthly_pass',
            'location' => 'Office Tower',
            'parking_date' => '2026-06-01',
            'billing_month' => '2026-06-01',
            'start_hour' => null,
            'end_hour' => null,
            'total_amount' => 180,
            'notes' => 'June pass',
        ]);

        $monthlyResponse->assertOk();
        $monthlyResponse->assertJsonPath('parkingLogs.0.location', 'Mall');
        $monthlyResponse->assertJsonPath('parkingLogs.1.location', 'Office Tower');
        $monthlyResponse->assertJsonPath('parkingLogs.1.billing_month', '2026-06-01');

        $parkingLogId = $casualResponse->json('parkingLogs.0.id');
        $updateResponse = $this->putJson(route('transportation-log.parking-logs.update', $parkingLogId), [
            'parking_type' => 'casual',
            'location' => 'Mall Basement',
            'parking_date' => '2026-06-12',
            'billing_month' => null,
            'start_hour' => 11,
            'end_hour' => 14,
            'total_amount' => 7,
            'notes' => '',
        ]);

        $updateResponse->assertOk();
        $this->assertDatabaseHas('transportation_parking_logs', [
            'id' => $parkingLogId,
            'location' => 'Mall Basement',
            'start_hour' => 11,
            'end_hour' => 14,
            'total_amount' => 7,
        ]);

        $this->deleteJson(route('transportation-log.parking-logs.destroy', $parkingLogId))->assertOk();
        $this->assertDatabaseMissing('transportation_parking_logs', [
            'id' => $parkingLogId,
        ]);
    }

    public function test_casual_parking_end_hour_must_be_after_start_hour(): void
    {
        $response = $this->postJson(route('transportation-log.parking-logs.store'), [
            'parking_type' => 'casual',
            'location' => 'Mall',
            'parking_date' => '2026-06-12',
            'billing_month' => null,
            'start_hour' => 13,
            'end_hour' => 10,
            'total_amount' => 6.50,
            'notes' => '',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('end_hour');
    }
}
