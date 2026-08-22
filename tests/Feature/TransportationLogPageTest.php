<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransportationLogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_transportation_summary_includes_an_export_action(): void
    {
        $response = $this->get(route('transportation-log.index'));

        $response->assertOk();
        $response->assertSee('id="transportationSummaryTitle"', false);
        $response->assertSee('id="transportationSummaryExport"', false);
        $response->assertSee('Export');
        $response->assertSee('data-summary-period="monthly"', false);
        $response->assertSee('data-summary-period="weekly"', false);
        $response->assertSee('data-summary-period="since_refuel"', false);
        $response->assertSee('data-summary-period="custom"', false);
        $response->assertSee('id="transportationSummaryCustomPeriod"', false);
        $response->assertSee('id="transportationSummaryStartDate"', false);
        $response->assertSee('id="transportationSummaryEndDate"', false);
        $response->assertSee('id="fuelOdometerKm" type="number" min="0" step="1"', false);
        $response->assertSee('id="commuteFinalOdometerKm" type="number" min="0" step="1"', false);
        $response->assertSeeInOrder([
            'Refuel Logs',
            '<th>Location</th>',
        ], false);
        $response->assertSeeInOrder([
            'Parking Logs',
            '<th>Location</th>',
            '<th>Notes</th>',
            '<th class="text-end">Cost (RM)</th>',
        ], false);
        $response->assertSee('<td colspan="5" class="text-center text-secondary py-4">No parking logs yet.</td>', false);
        $response->assertSee('vehicleBrandLogoBaseUrl', false);
        $response->assertSee('Ending Odometer Reading (km)');
        $response->assertSeeInOrder([
            '<th>Route</th>',
            '<th>Drive Type</th>',
            '<th class="text-end">Distance</th>',
        ], false);
        $response->assertSee('<td colspan="7" class="text-center text-secondary py-4">No drive logs yet.</td>', false);
    }

    public function test_summary_and_each_log_table_are_rendered_in_separate_cards(): void
    {
        $response = $this->get(route('transportation-log.index'));

        $response->assertOk();
        $response->assertSeeInOrder([
            'id="transportationSummaryCard"',
            'id="transportationSummaryTitle"',
            'id="refuelLogsSection"',
            '<div class="card-header">Refuel Logs</div>',
            'id="driveLogsSection"',
            '<div class="card-header">Drive Logs</div>',
            'id="parkingLogsSection"',
            '<div class="card-header">Parking Logs</div>',
        ], false);
        $this->assertSame(
            4,
            substr_count($response->getContent(), 'class="card panel-card transportation-output-card"'),
        );
    }

    public function test_transportation_export_downloads_the_selected_period_as_json(): void
    {
        $response = $this->post(route('transportation-log.export'), [
            'period' => 'monthly',
            'reference_date' => '2026-07-11',
        ]);

        $response->assertOk();
        $response->assertDownload('transportation-log-monthly-2026-07-01.json');
        $this->assertSame('monthly', json_decode($response->streamedContent(), true, 512, JSON_THROW_ON_ERROR)['period']['type']);
    }
}
