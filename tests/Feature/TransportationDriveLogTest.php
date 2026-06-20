<?php

namespace Tests\Feature;

use App\Models\TransportationVehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransportationDriveLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_final_odometer_reading_is_optional_and_can_be_created_and_updated(): void
    {
        $vehicle = TransportationVehicle::query()->create([
            'name' => 'Test Car',
            'description' => null,
            'tank_capacity_l' => 45,
            'consumption_unit_default' => 'L_PER_100KM',
        ]);

        $payload = [
            'vehicle_id' => $vehicle->id,
            'commute_type' => 'personal_drive',
            'origin' => 'Home',
            'destination' => 'Town',
            'distance_km' => 12.5,
            'consumption_value' => 7.25,
            'consumption_unit' => 'L_PER_100KM',
            'driven_at' => '2026-06-20T10:00:00',
            'ended_at' => '2026-06-20T10:30:00',
            'average_speed_kmh' => 25,
            'top_speed_kmh' => 60,
            'notes' => null,
        ];

        $createResponse = $this->postJson(route('transportation-log.commute-logs.store'), [
            ...$payload,
            'final_odometer_km' => 45678.9,
        ]);

        $createResponse->assertOk();
        $createResponse->assertJsonPath('commuteLogs.0.final_odometer_km', 45678.9);

        $commuteLogId = $createResponse->json('commuteLogs.0.id');
        $this->putJson(route('transportation-log.commute-logs.update', $commuteLogId), [
            ...$payload,
            'final_odometer_km' => null,
        ])->assertOk();

        $this->assertDatabaseHas('transportation_commute_logs', [
            'id' => $commuteLogId,
            'final_odometer_km' => null,
        ]);
    }
}
