<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_contains_all_configuration_tools(): void
    {
        $response = $this->get(route('settings.index'));

        $response->assertOk();
        $response->assertSee('General settings');
        $response->assertSee('Workday Calendar');
        $response->assertSee('Salary Schedules');
        $response->assertSee('Prompt Templates');
        $response->assertSee('Weekly financial review');
        $response->assertSee('Month-end financial report');
        $response->assertSee('Nothing is sent outside this application.');
        $response->assertSee('id="promptPreview"', false);
        $response->assertDontSee('File Export / Import');
        $response->assertDontSee('export-import-pane');
        $response->assertSee('Starting Amount (RM)');
        $response->assertSee('id="counterNotificationToggle"', false);
        $response->assertSee('fa-cog', false);
        $response->assertSee('aria-current="page"', false);
    }
}
