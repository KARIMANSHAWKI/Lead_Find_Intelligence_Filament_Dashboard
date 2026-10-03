<?php

namespace Tests\Feature;

use App\Filament\Pages\AgentRuns;
use App\Filament\Pages\Prospects;
use App\Filament\Pages\ViewAgentRun;
use App\Filament\Pages\ViewProspect;
use App\Models\AgentRun;
use App\Models\BuyingSignal;
use App\Models\Prospect;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class IntelligencePagesTest extends TestCase
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

    public function test_prospects_are_scoped_searchable_filterable_and_display_nullable_scores(): void
    {
        $run = AgentRun::factory()->for($this->user->organization)->create();
        $first = Prospect::factory()->for($run)->create(['company_name' => 'Alpha Logistics', 'icp_fit' => 'high', 'product_relevance' => 'high', 'status' => 'qualified']);
        $second = Prospect::factory()->forOrganization($this->user->organization)->create(['company_name' => 'Beta Software', 'icp_fit' => 'medium', 'product_relevance' => 'medium', 'website' => 'https://beta.example.com']);
        $other = Prospect::factory()->create(['company_name' => 'Private Company']);
        $page = Livewire::test(Prospects::class)
            ->assertCanSeeTableRecords([$first, $second])->assertCanNotSeeTableRecords([$other])
            ->assertSee('No verified signal')->assertSee('—');
        $page->searchTable('Alpha')->assertCanSeeTableRecords([$first])->assertCanNotSeeTableRecords([$second]);
        $page->searchTable('beta.example.com')->assertCanSeeTableRecords([$second])->assertCanNotSeeTableRecords([$first]);
        $page->searchTable('')->filterTable('icp_fit', 'high')->assertCanSeeTableRecords([$first])->assertCanNotSeeTableRecords([$second]);
        $page->resetTableFilters()->filterTable('product_relevance', 'medium')->assertCanSeeTableRecords([$second])->assertCanNotSeeTableRecords([$first]);
        $page->resetTableFilters()->filterTable('agent_run_id', $run->id)->assertCanSeeTableRecords([$first])->assertCanNotSeeTableRecords([$second]);
        $page->resetTableFilters()->filterTable('status', 'qualified')->assertCanSeeTableRecords([$first])->assertCanNotSeeTableRecords([$second]);
        Http::assertNothingSent();
    }

    public function test_prospect_detail_displays_evidence_and_enforces_ownership(): void
    {
        $prospect = Prospect::factory()->forOrganization($this->user->organization)->create();
        BuyingSignal::factory()->for($prospect)->create();
        BuyingSignal::factory()->for($prospect)->create(['source_url' => null, 'type' => 'new_location']);
        $this->get(ViewProspect::getUrl(['record' => $prospect->id], panel: 'app'))
            ->assertOk()->assertSee('Why now?')->assertSee($prospect->why_now)
            ->assertSee('ICP Fit')->assertSee('Product Relevance')->assertSee('Evidence Quality')
            ->assertSee('Hiring Growth')->assertSee('New Location')->assertSee('https://example.com/jobs')
            ->assertSee('noopener noreferrer')->assertSee('No source URL supplied.')
            ->assertSee('Not scored yet')->assertSee('Run #'.$prospect->agent_run_id);
        $other = Prospect::factory()->create();
        $this->get(ViewProspect::getUrl(['record' => $other->id], panel: 'app'))->assertNotFound();
    }

    public function test_unsafe_source_schemes_are_not_linked(): void
    {
        $prospect = Prospect::factory()->forOrganization($this->user->organization)->create(['website' => 'javascript:alert(1)']);
        BuyingSignal::factory()->for($prospect)->create(['source_url' => 'javascript:alert(1)']);
        $this->get(ViewProspect::getUrl(['record' => $prospect->id], panel: 'app'))
            ->assertOk()->assertDontSee('href="javascript:', false)->assertSee('No source URL supplied.');
    }

    public function test_run_list_detail_duration_and_related_prospects_are_scoped(): void
    {
        $run = AgentRun::factory()->for($this->user->organization)->completed()->create();
        $other = AgentRun::factory()->completed()->create();
        $prospect = Prospect::factory()->for($run)->create();
        Livewire::test(AgentRuns::class)->assertCanSeeTableRecords([$run])->assertCanNotSeeTableRecords([$other])->assertSee('Completed')->assertSee('5m 00s');
        $this->get(ViewAgentRun::getUrl(['record' => $run->id], panel: 'app'))
            ->assertOk()->assertSee('16')->assertSee('5')->assertSee('Related Prospects')
            ->assertSee($prospect->company_name)->assertSee(ViewProspect::getUrl(['record' => $prospect->id], panel: 'app'));
        $this->get(ViewAgentRun::getUrl(['record' => $other->id], panel: 'app'))->assertNotFound();
    }

    public function test_failed_run_errors_are_escaped_and_empty_runs_explain_next_steps(): void
    {
        $run = AgentRun::factory()->for($this->user->organization)->failed()->create(['error_message' => '<script>alert(1)</script>']);
        $this->get(ViewAgentRun::getUrl(['record' => $run->id], panel: 'app'))
            ->assertOk()->assertSee('Failed')->assertSee('No qualified prospects for this run')
            ->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_strongest_signal_is_selected_by_strength(): void
    {
        $prospect = Prospect::factory()->forOrganization($this->user->organization)->create();
        BuyingSignal::factory()->for($prospect)->create(['strength' => 'low', 'type' => 'branch_growth']);
        BuyingSignal::factory()->for($prospect)->create(['strength' => 'medium', 'type' => 'team_expansion']);
        BuyingSignal::factory()->for($prospect)->create(['strength' => 'high', 'type' => 'hiring_growth']);
        Livewire::test(Prospects::class)->assertSee('Hiring Growth')->assertDontSee('Branch Growth');
    }
}
