<?php

namespace Tests\Feature;

use App\Application\LeadIntelligence\RunLeadIntelligenceAction;
use App\Filament\DashboardData;
use App\Filament\Pages\AgentRuns;
use App\Filament\Pages\IcpConfiguration as IcpConfigurationPage;
use App\Filament\Pages\Overview;
use App\Filament\Pages\Prospects;
use App\Filament\Pages\ViewAgentRun;
use App\Filament\Pages\ViewProspect;
use App\Models\AgentRun;
use App\Models\BuyingSignal;
use App\Models\IcpConfiguration;
use App\Models\Prospect;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationTenancyTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $teammate;

    protected User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Http::preventStrayRequests();
        $this->user = User::factory()->create();
        $this->teammate = User::factory()->for($this->user->organization)->create();
        $this->outsider = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_organization_relationships_and_factories_have_consistent_ownership(): void
    {
        $organization = $this->user->organization;
        $this->assertTrue($this->teammate->organization->is($organization));
        $this->assertSame([$this->user->id, $this->teammate->id], $organization->users()->orderBy('id')->pluck('id')->all());
        $this->assertDatabaseCount('organizations', 2);
        $icp = IcpConfiguration::factory()->for($organization)->create();
        $prospect = Prospect::factory()->forOrganization($organization)->create();
        $signal = BuyingSignal::factory()->for($prospect)->create();
        $this->assertTrue($organization->fresh()->icpConfiguration->is($icp));
        $this->assertTrue($icp->organization->is($organization));
        $this->assertTrue($prospect->organization->is($organization));
        $this->assertTrue($prospect->agentRun->organization->is($organization));
        $this->assertTrue($signal->prospect->organization->is($organization));
        $this->assertSame([$prospect->id], $organization->prospects()->pluck('id')->all());
        $defaultProspect = Prospect::factory()->create();
        $this->assertSame($defaultProspect->organization_id, $defaultProspect->agentRun->organization_id);
        $associated = Prospect::factory()->for($prospect->agentRun)->create();
        $this->assertSame($organization->id, $associated->organization_id);
    }

    public function test_same_organization_users_share_and_update_one_icp(): void
    {
        $icp = IcpConfiguration::factory()->for($this->user->organization)->create(['product' => 'Organization A product']);
        $other = IcpConfiguration::factory()->for($this->outsider->organization)->create(['product' => 'Organization B product']);
        foreach ([$this->user, $this->teammate] as $user) {
            $this->actingAs($user);
            Livewire::test(IcpConfigurationPage::class)->assertSchemaStateSet(['product' => 'Organization A product'], 'form');
            $this->get(IcpConfigurationPage::getUrl(panel: 'app'))->assertOk()->assertDontSee('Organization B product');
        }
        Livewire::test(IcpConfigurationPage::class)->fillForm(['product' => 'Shared updated product'], 'form')
            ->set('data.organization_id', $other->organization_id)->set('data.user_id', $this->outsider->id)
            ->set('data.id', $other->id)->call('save')->assertHasNoFormErrors()
            ->assertNotified('ICP configuration saved successfully.');
        $this->assertSame('Shared updated product', $icp->fresh()->product);
        $this->assertSame('Organization B product', $other->fresh()->product);
        $this->assertDatabaseCount('icp_configurations', 2);
        $this->actingAs($this->user);
        Livewire::test(IcpConfigurationPage::class)->assertSchemaStateSet(['product' => 'Shared updated product'], 'form');
        $this->actingAs($this->outsider);
        Livewire::test(IcpConfigurationPage::class)->assertSchemaStateSet(['product' => 'Organization B product'], 'form');
        Http::assertNothingSent();
    }

    public function test_icp_creation_ignores_submitted_organization_and_record_ids(): void
    {
        $other = IcpConfiguration::factory()->for($this->outsider->organization)->create();
        Livewire::test(IcpConfigurationPage::class)->fillForm([
            'product' => 'Our product', 'target_industries' => ['Software'], 'location' => 'Egypt',
        ], 'form')->set('data.organization_id', $other->organization_id)->set('data.id', $other->id)
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('Our product', $this->user->organization->icpConfiguration()->firstOrFail()->product);
        $this->assertSame('Recruitment Management Software', $other->fresh()->product);
        $this->assertDatabaseCount('icp_configurations', 2);
    }

    public function test_database_enforces_one_icp_per_organization(): void
    {
        IcpConfiguration::factory()->for($this->user->organization)->create();
        $this->expectException(UniqueConstraintViolationException::class);
        IcpConfiguration::factory()->for($this->teammate->organization)->create();
    }

    public function test_mandatory_cross_organization_prospect_signal_and_run_isolation(): void
    {
        $own = Prospect::factory()->forOrganization($this->user->organization)->create(['company_name' => 'Organization A Logistics']);
        $other = Prospect::factory()->forOrganization($this->outsider->organization)->create(['company_name' => 'Organization B Private']);
        BuyingSignal::factory()->for($own)->create(['evidence' => 'Organization A hiring evidence']);
        BuyingSignal::factory()->for($other)->create(['evidence' => 'Organization B confidential evidence']);
        foreach ([$this->user, $this->teammate] as $user) {
            $this->actingAs($user);
            Livewire::test(Prospects::class)->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$other]);
            Livewire::test(AgentRuns::class)->assertCanSeeTableRecords([$own->agentRun])->assertCanNotSeeTableRecords([$other->agentRun]);
            $this->get(ViewProspect::getUrl(['record' => $own->id], panel: 'app'))->assertOk()->assertSee('Organization A hiring evidence')->assertDontSee('Organization B confidential evidence');
            $this->get(ViewProspect::getUrl(['record' => $other->id], panel: 'app'))->assertNotFound();
            $this->get(ViewAgentRun::getUrl(['record' => $own->agent_run_id], panel: 'app'))->assertOk()->assertSee('Organization A Logistics')->assertDontSee('Organization B Private');
            $this->get(ViewAgentRun::getUrl(['record' => $other->agent_run_id], panel: 'app'))->assertNotFound();
        }
        $this->actingAs($this->outsider);
        Livewire::test(Prospects::class)->assertCanSeeTableRecords([$other])->assertCanNotSeeTableRecords([$own]);
        Livewire::test(AgentRuns::class)->assertCanSeeTableRecords([$other->agentRun])->assertCanNotSeeTableRecords([$own->agentRun]);
        $this->get(ViewProspect::getUrl(['record' => $own->id], panel: 'app'))->assertNotFound();
        $this->get(ViewAgentRun::getUrl(['record' => $own->agent_run_id], panel: 'app'))->assertNotFound();
    }

    public function test_search_filter_results_and_options_cannot_leak_other_organizations(): void
    {
        $own = Prospect::factory()->forOrganization($this->user->organization)->create([
            'company_name' => 'Shared search company', 'website' => 'https://search.example.com', 'status' => 'qualified',
        ]);
        $other = Prospect::factory()->forOrganization($this->outsider->organization)->create([
            'company_name' => 'Shared search company', 'website' => 'https://search.example.com', 'status' => 'private_status',
        ]);
        $page = Livewire::test(Prospects::class)->searchTable('Shared search')
            ->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$other])->assertDontSee('Private Status');
        $page->searchTable('search.example.com')->filterTable('icp_fit', 'high')
            ->filterTable('product_relevance', 'high')->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$other]);
        $page->filterTable('agent_run_id', $other->agent_run_id)->assertCanNotSeeTableRecords([$own, $other]);
        $filters = $page->instance()->getTable()->getFilters();
        $this->assertArrayNotHasKey($other->agent_run_id, $filters['agent_run_id']->getOptions());
        $this->assertArrayNotHasKey('private_status', $filters['status']->getOptions());
        Livewire::test(AgentRuns::class)->filterTable('status', 'pending')
            ->assertCanSeeTableRecords([$own->agentRun])->assertCanNotSeeTableRecords([$other->agentRun]);
    }

    public function test_dashboard_aggregates_chart_opportunities_and_activity_use_organization_scope(): void
    {
        $organization = $this->user->organization;
        Prospect::factory()->forOrganization($organization)->create(['score' => 90, 'company_name' => 'Shared Opportunity']);
        Prospect::factory()->forOrganization($organization)->create(['score' => 70]);
        Prospect::factory()->forOrganization($this->outsider->organization)->count(3)->create(['score' => 100, 'company_name' => 'Private Opportunity']);
        foreach ([$this->user, $this->teammate] as $user) {
            $this->actingAs($user);
            $data = app(DashboardData::class);
            $this->assertSame(['Total Prospects' => 2, 'High Priority' => 1, 'Medium Priority' => 1, 'Agent Runs' => 2], $data->metrics());
            $this->assertSame([1, 1, 0, 0], array_column($data->scoreDistribution(), 'count'));
            $this->assertCount(2, $data->opportunities());
            $this->assertCount(2, $data->activity());
            $this->get(Overview::getUrl(panel: 'app'))->assertOk()->assertSee('Shared Opportunity')->assertDontSee('Private Opportunity');
        }
    }

    public function test_existing_agent_action_uses_shared_icp_and_persists_in_current_organization(): void
    {
        $icp = IcpConfiguration::factory()->for($this->user->organization)->create(['product' => 'Shared organization product']);
        IcpConfiguration::factory()->for($this->outsider->organization)->create(['product' => 'Private ICP']);
        $this->actingAs($this->teammate);
        config(['services.lead_intelligence.base_url' => 'http://lead-service.test']);
        Http::fake(fn (Request $request) => Http::response(['run_id' => $request['run_id'], 'prospects' => [[
            'company_name' => 'Shared researched company', 'icp_fit' => 'high', 'product_relevance' => 'high',
            'why_now' => 'Hiring now.', 'organization_id' => $this->outsider->organization_id, 'buying_signals' => [],
        ]]]));
        $run = app(RunLeadIntelligenceAction::class)->execute($this->teammate);
        $prospect = $run->prospects()->firstOrFail();
        $this->assertSame($icp->organization_id, $run->organization_id);
        $this->assertSame($run->organization_id, $prospect->organization_id);
        Http::assertSent(fn (Request $request): bool => $request['client']['product'] === 'Shared organization product'
            && ! isset($request['organization_id']) && ! isset($request['client']['organization_id']));
        $this->actingAs($this->user);
        Livewire::test(Prospects::class)->assertCanSeeTableRecords([$prospect]);
    }

    public function test_deleting_a_user_keeps_the_shared_organization_and_business_data(): void
    {
        $icp = IcpConfiguration::factory()->for($this->user->organization)->create();
        $prospect = Prospect::factory()->forOrganization($this->user->organization)->create();
        $this->user->delete();
        $this->assertModelExists($icp);
        $this->assertModelExists($prospect);
        $this->assertModelExists($this->teammate);
        $this->assertModelExists($this->teammate->organization);
    }

    public function test_prospect_cannot_be_reassigned_to_another_organizations_run(): void
    {
        $prospect = Prospect::factory()->forOrganization($this->user->organization)->create();
        $run = AgentRun::factory()->for($this->outsider->organization)->create();
        $this->expectException(ValidationException::class);
        $prospect->update(['agent_run_id' => $run->id]);
    }

    public function test_run_with_prospects_cannot_be_moved_to_another_organization(): void
    {
        $prospect = Prospect::factory()->forOrganization($this->user->organization)->create();
        $this->expectException(ValidationException::class);
        $prospect->agentRun->forceFill(['organization_id' => $this->outsider->organization_id])->save();
    }

    public function test_organization_is_not_mass_assignable_on_users_or_business_records(): void
    {
        $this->user->update(['organization_id' => $this->outsider->organization_id]);
        $icp = IcpConfiguration::factory()->for($this->user->organization)->create();
        $prospect = Prospect::factory()->forOrganization($this->user->organization)->create();
        $icp->update(['organization_id' => $this->outsider->organization_id]);
        $prospect->update(['organization_id' => $this->outsider->organization_id]);
        $prospect->agentRun->update(['organization_id' => $this->outsider->organization_id]);
        foreach ([$this->user, $icp, $prospect, $prospect->agentRun] as $record) {
            $this->assertSame($this->user->organization_id, $record->fresh()->organization_id);
        }
    }

    public function test_detail_identity_cannot_be_changed_through_livewire_state(): void
    {
        $prospect = Prospect::factory()->forOrganization($this->user->organization)->create();
        $other = Prospect::factory()->forOrganization($this->outsider->organization)->create();
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(ViewProspect::class, ['record' => $prospect->id])->set('prospectId', $other->id);
    }

    public function test_existing_filament_user_command_creates_a_valid_organization_account(): void
    {
        $this->artisan('make:filament-user', [
            '--panel' => 'app', '--name' => 'New Account', '--email' => 'new-account@example.test',
            '--password' => 'test-command-password', '--no-interaction' => true,
        ])->assertSuccessful();
        $user = User::query()->where('email', 'new-account@example.test')->firstOrFail();
        $this->assertSame("New Account's Organization", $user->organization->name);
        $this->assertDatabaseCount('organizations', 3);
    }

    public function test_filament_user_command_can_associate_an_existing_organization(): void
    {
        $this->artisan('make:filament-user', [
            '--panel' => 'app', '--name' => 'Another Teammate', '--email' => 'teammate@example.test',
            '--password' => 'test-command-password', '--organization' => $this->user->organization_id,
            '--no-interaction' => true,
        ])->assertSuccessful();
        $user = User::query()->where('email', 'teammate@example.test')->firstOrFail();
        $this->assertSame($this->user->organization_id, $user->organization_id);
        $this->assertDatabaseCount('organizations', 2);
    }

    public function test_required_organization_prevents_unassociated_application_users(): void
    {
        $this->expectException(QueryException::class);
        User::factory()->create(['organization_id' => null]);
    }
}
