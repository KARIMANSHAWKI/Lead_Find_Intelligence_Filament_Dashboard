<?php

namespace Tests\Feature;

use App\Filament\Pages\AgentRuns;
use App\Filament\Pages\IcpConfiguration;
use App\Filament\Pages\Overview;
use App\Filament\Pages\Prospects;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        Http::preventStrayRequests();
    }

    public function test_the_panel_login_page_loads(): void
    {
        $this->get(Filament::getLoginUrl())
            ->assertOk()
            ->assertSee('Lead Intelligence')
            ->assertSee('Sign in');
    }

    public function test_overview_is_the_application_landing_page(): void
    {
        $panel = Filament::getPanel('app');

        $this->assertSame(url('/app'), Overview::getUrl(panel: 'app'));
        $this->assertSame(Overview::getUrl(panel: 'app'), $panel->getUrl());
        $this->get('/')->assertRedirect(Overview::getUrl(panel: 'app'));

        $this->actingAs(User::factory()->create())
            ->get($panel->getUrl())
            ->assertOk()
            ->assertSee('Lead Intelligence')
            ->assertSee('Find companies worth contacting now — and understand WHY NOW.')
            ->assertSee('Run Lead Agent')
            ->assertSee('Top Opportunities')
            ->assertSee('Agent Activity');
    }

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function applicationPages(): array
    {
        return [
            'Overview' => [Overview::class, 'Lead Intelligence'],
            'ICP Configuration' => [IcpConfiguration::class, 'Tell the Lead Intelligence Agent what your ideal customer looks like.'],
            'Prospects' => [Prospects::class, 'No prospects yet'],
            'Agent Runs' => [AgentRuns::class, 'No agent runs yet'],
        ];
    }

    #[DataProvider('applicationPages')]
    public function test_authenticated_users_can_access_application_pages(string $page, string $content): void
    {
        $this->actingAs(User::factory()->create())
            ->get($page::getUrl(panel: 'app'))
            ->assertOk()
            ->assertSee($content);
    }

    #[DataProvider('applicationPages')]
    public function test_guests_are_redirected_to_the_existing_web_guard_login(string $page, string $content): void
    {
        $this->get($page::getUrl(panel: 'app'))
            ->assertRedirect(Filament::getLoginUrl());
    }

    public function test_navigation_contains_only_the_four_product_pages_in_order(): void
    {
        $this->actingAs(User::factory()->create());

        $items = collect(Filament::getNavigation())
            ->flatMap(fn ($group) => $group->getItems());

        $this->assertSame(
            ['Overview', 'ICP Configuration', 'Prospects', 'Agent Runs'],
            $items->map(fn ($item) => $item->getLabel())->all(),
        );
    }

    public function test_login_uses_the_existing_user_and_web_guard_and_lands_on_overview(): void
    {
        $user = User::factory()->create(['password' => 'test-password']);

        Livewire::test(Login::class)
            ->set('data.email', $user->email)
            ->set('data.password', 'test-password')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect(Overview::getUrl(panel: 'app'));

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_run_lead_agent_requires_icp_without_sending_http(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Overview::class)
            ->callAction('runLeadAgent')
            ->assertNotified('Configure your ICP before running the agent.');

        Http::assertNothingSent();
    }

    public function test_existing_users_can_access_the_product_panel_in_production(): void
    {
        config(['app.env' => 'production']);

        $this->actingAs(User::factory()->create())
            ->get(Overview::getUrl(panel: 'app'))
            ->assertOk();
    }

    public function test_future_fastapi_url_is_exposed_through_configuration(): void
    {
        $services = require config_path('services.php');

        $this->assertArrayHasKey('base_url', $services['lead_intelligence']);
        $this->assertSame(env('FASTAPI_BASE_URL'), config('services.lead_intelligence.base_url'));
        Http::assertNothingSent();
    }
}
