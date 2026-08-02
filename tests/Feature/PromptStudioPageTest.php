<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptStudioPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_prompt_studio_contains_template_and_composer_workspaces(): void
    {
        $response = $this->get(route('prompt-studio.index'));

        $response->assertOk();
        $response->assertSee('<h1 class="h3 mb-1">Prompt Studio</h1>', false);
        $response->assertSee('class="card-header prompt-studio-workspace-header"', false);
        $response->assertSee('class="prompt-studio-section-title"', false);
        $response->assertSee('id="promptStudioSectionTitle"', false);
        $response->assertSee('id="promptStudioSectionSubtitle"', false);
        $response->assertSee('id="promptStudioTabs"', false);
        $response->assertSee('class="projection-input-tab active" id="prompt-templates-tab"', false);
        $response->assertSee('class="projection-input-tab" id="prompt-composer-tab"', false);
        $response->assertSee('id="prompt-templates-pane"', false);
        $response->assertSee('id="prompt-composer-pane"', false);
        $response->assertSee('class="row g-3 prompt-template-layout"', false);
        $response->assertSee('class="data-card prompt-template-details w-100"', false);
        $response->assertSee('class="data-card prompt-template-text-column w-100"', false);
        $response->assertSee('id="promptTemplateStatusToast"', false);
        $response->assertSee('id="promptTemplateStatus" class="toast-body"', false);
        $response->assertSee('id="promptComposerStatusToast"', false);
        $response->assertSee('id="promptComposerStatus" class="toast-body"', false);
        $response->assertDontSee('prompt-workspace-status', false);
        $response->assertSee('class="col-lg-6 d-flex"', false);
        $response->assertSee('prompt-composer-working', false);
        $response->assertSee('prompt-composer-output', false);
        $response->assertDontSee('prompt-studio-nav-card', false);
        $response->assertSee('id="promptComposerTemplateSelect"', false);
        $response->assertSee('id="promptWeek"', false);
        $response->assertSee('id="promptMonth"', false);
        $response->assertSee('id="promptCustomPeriodControl"', false);
        $response->assertSee('id="promptResolvedPeriod"', false);
        $response->assertSee('id="promptPeriodStatus"', false);
        $response->assertSee('Weekly financial review');
        $response->assertSee('Month-end financial report');
        $response->assertSee('window.promptStudioConfig', false);
        $response->assertSee('aria-current="page"', false);
    }

    public function test_prompt_studio_navigation_is_between_transportation_and_theme_toggle(): void
    {
        $response = $this->get(route('prompt-studio.index'));

        $response->assertSeeInOrder([
            'Go to transportation',
            'Go to prompt studio',
            'Switch to dark mode',
        ]);
    }
}
