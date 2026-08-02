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
        $response->assertDontSee('id="prompt-templates-pane"', false);
        $response->assertDontSee('id="prompt-composer-pane"', false);
        $response->assertDontSee('window.promptStudioConfig', false);
        $response->assertDontSee('File Export / Import');
        $response->assertDontSee('export-import-pane');
        $response->assertSee('Starting Amount (RM)');
        $response->assertSee('id="counterNotificationToggle"', false);
        $response->assertSee('fa-cog', false);
        $response->assertSee('aria-current="page"', false);
    }
}
