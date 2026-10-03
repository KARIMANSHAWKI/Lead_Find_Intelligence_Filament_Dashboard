<?php

namespace Tests\Feature;

use App\Filament\Pages\Overview;
use App\Filament\Widgets\LeadStatsOverview;
use App\Filament\Widgets\OpportunityScoreChart;
use App\Filament\Widgets\RecentAgentActivity;
use App\Filament\Widgets\TopOpportunities;
use App\Models\AgentRun;
use App\Models\Prospect;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        Http::preventStrayRequests();
    }

    public function test_overview_shows_real_data_and_only_the_owners_opportunities(): void
    {
        $first = Prospect::factory()->forOrganization($this->user->organization)->create(['score' => 92, 'company_name' => 'Alpha Logistics']);
        Prospect::factory()->forOrganization($this->user->organization)->create(['score' => 76, 'company_name' => 'Beta Systems']);
        Prospect::factory()->create(['score' => 100, 'company_name' => 'Private Company']);
        $this->get(Overview::getUrl(panel: 'app'))->assertOk()
            ->assertSee('Total Prospects')->assertSee('High Priority')->assertSee('Medium Priority')->assertSee('Agent Runs')
            ->assertSee('Opportunity Score Distribution')->assertSee('Top Opportunities')->assertSee('Recent Agent Activity')
            ->assertSee('Alpha Logistics')->assertSee('Beta Systems')->assertSee('92')
            ->assertSee($first->why_now)->assertDontSee('Private Company')->assertDontSee('Demo data');
        Livewire::test(LeadStatsOverview::class)->assertSeeTextInOrder(['Total Prospects', '2', 'High Priority', '1', 'Medium Priority', '1', 'Agent Runs', '2']);
        Http::assertNothingSent();
    }

    public function test_chart_contains_persisted_counts_and_accessible_ranges(): void
    {
        foreach ([92, 70, 50, 10] as $score) {
            Prospect::factory()->forOrganization($this->user->organization)->create(['score' => $score]);
        }
        Livewire::test(OpportunityScoreChart::class)->assertSee('80–100: 1 prospects')
            ->assertSee('60–79: 1 prospects')->assertSee('40–59: 1 prospects')->assertSee('0–39: 1 prospects')
            ->assertSee('role="img"', false)->assertSee('data-chart-type="bar"', false);
    }

    public function test_activity_uses_real_runs_and_links(): void
    {
        $run = AgentRun::factory()->for($this->user->organization)->completed()->create();
        Livewire::test(RecentAgentActivity::class)->assertSee('Run #'.$run->id)->assertSee('Completed')
            ->assertSee('5 qualified opportunities')->assertDontSee('Example workflow');
    }

    public function test_empty_data_gives_next_steps_in_each_widget(): void
    {
        Livewire::test(TopOpportunities::class)->assertSee('No opportunities yet');
        Livewire::test(RecentAgentActivity::class)->assertSee('No activity yet');
        Livewire::test(OpportunityScoreChart::class)->assertSee('No scores to display yet');
        $this->get(Overview::getUrl(panel: 'app'))->assertSee('Complete your ICP to start discovering opportunities')->assertSee('Configure ICP');
    }

    public function test_unscored_records_are_not_misrepresented_as_zero_scores(): void
    {
        Prospect::factory()->forOrganization($this->user->organization)->create(['score' => null]);
        Livewire::test(OpportunityScoreChart::class)->assertSee('Not scored: 1 prospects');
        Livewire::test(TopOpportunities::class)->assertSee('No opportunities yet');
    }
}
