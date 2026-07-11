<?php

namespace Tests\Unit;

use App\Models\TransportationCommuteLog;
use App\Models\TransportationFuelLog;
use App\Models\TransportationParkingLog;
use App\Models\TransportationVehicle;
use App\Services\TransportationExportPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransportationExportPayloadBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_the_selected_transportation_period_payload(): void
    {
        $vehicle = TransportationVehicle::query()->create([
            'name' => 'Myvi',
            'description' => null,
            'tank_capacity_l' => 40,
            'consumption_unit_default' => 'L_PER_100KM',
        ]);

        TransportationFuelLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'odometer_km' => 1000,
            'fuel_litres' => 20,
            'fuel_price_mode' => 'budi95',
            'price_per_litre' => 2,
            'total_amount' => 40,
            'fuelled_at' => '2026-07-02 09:00:00',
            'location' => 'Petron',
            'notes' => null,
        ]);
        TransportationCommuteLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'commute_type' => 'work_commute',
            'origin' => 'Home',
            'destination' => 'Office',
            'distance_km' => 10,
            'final_odometer_km' => 1010,
            'consumption_value' => 8,
            'consumption_unit' => 'L_PER_100KM',
            'driven_at' => '2026-07-03 09:00:00',
            'ended_at' => '2026-07-03 09:30:00',
            'average_speed_kmh' => 20,
            'top_speed_kmh' => 40,
            'drive_time_minutes' => 30,
            'notes' => null,
        ]);
        TransportationParkingLog::query()->create([
            'parking_type' => 'casual',
            'location' => 'Office',
            'parking_date' => '2026-07-03',
            'billing_month' => null,
            'start_hour' => 9,
            'end_hour' => 18,
            'total_amount' => 8,
            'notes' => null,
        ]);

        $payload = app(TransportationExportPayloadBuilder::class)->build([
            'period' => 'custom',
            'reference_date' => '2026-07-01',
            'custom_start_date' => '2026-07-01',
            'custom_end_date' => '2026-07-31',
            'refuel_offset' => 0,
        ]);

        $this->assertSame([
            'fuel_spending' => 40.0,
            'estimated_drive_cost' => 1.6,
            'fuel_litres_refuelled' => 20.0,
            'average_mileage_l_per_100km' => 8.0,
            'commute_distance_km' => 10.0,
            'parking_cost' => 8.0,
        ], $payload['headline_stats']);
        $this->assertSame('Myvi', $payload['refuel_logs'][0]['vehicle']);
        $this->assertSame(1000.0, $payload['refuel_logs'][0]['odometer_at_pump_km']);
        $this->assertSame('BUDI95', $payload['refuel_logs'][0]['fuel_price_type']);
        $this->assertSame('Petron', $payload['refuel_logs'][0]['location']);
        $this->assertSame('Home - Office', $payload['drive_logs'][0]['route']);
        $this->assertSame('2026-07-03', $payload['drive_logs'][0]['date']);
        $this->assertSame('09:00', $payload['drive_logs'][0]['start_time']);
        $this->assertSame('09:30', $payload['drive_logs'][0]['end_time']);
        $this->assertSame(1010.0, $payload['drive_logs'][0]['odometer_reading_km']);
        $this->assertArrayNotHasKey('fuel_used_litres', $payload['drive_logs'][0]);
        $this->assertSame('N/A', $payload['drive_logs'][0]['notes']);
        $this->assertSame('2026-07-03 09:00 - 18:00', $payload['parking_logs'][0]['date_or_datetime']);
    }
}
