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
    }
}
