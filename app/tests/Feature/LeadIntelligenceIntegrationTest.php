<?php

namespace Tests\Feature;

use App\Application\LeadIntelligence\RunLeadIntelligenceAction;
use App\Application\Scoring\ScoreProspect;
use App\Filament\Pages\AgentRuns;
use App\Filament\Pages\IcpConfiguration as IcpConfigurationPage;
use App\Filament\Pages\Overview;
use App\Filament\Pages\ViewAgentRun;
use App\Filament\Pages\ViewProspect;
use App\Infrastructure\LeadIntelligence\LeadIntelligenceFailure;
use App\Models\AgentRun;
use App\Models\IcpConfiguration;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class LeadIntelligenceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Filament::setCurrentPanel(Filament::getPanel('app'));
        config(['services.lead_intelligence.base_url' => 'http://lead-service.test', 'services.lead_intelligence.timeout' => 120]);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        Http::preventStrayRequests();
    }

    public function test_icp_to_agent_to_dashboard_and_detail_journey(): void
    {
        Livewire::test(IcpConfigurationPage::class)->fillForm([
            'product' => 'Recruitment Management Software',
            'target_industries' => ['Software', 'Logistics'], 'location' => 'Egypt',
            'company_size_min' => 50, 'company_size_max' => 300,
            'ideal_customer_description' => 'Companies actively growing their teams.',
        ], 'form')->call('save')->assertHasNoFormErrors()->assertNotified('ICP configuration saved successfully.');
        $icp = $this->user->organization->icpConfiguration()->firstOrFail();
        $baseline = DB::transactionLevel();
        Http::fake(function (Request $request) use ($baseline) {
            $this->assertSame($baseline, DB::transactionLevel());
            $this->assertSame('running', AgentRun::findOrFail($request['run_id'])->status);

            return Http::response($this->response($request['run_id']));
        });
        Livewire::test(Overview::class)->callAction('runLeadAgent')->assertNotified('Research completed')->assertDispatched('intelligence-updated');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://lead-service.test/api/v1/agent/run'
            && $request->method() === 'POST' && $request['run_id'] === AgentRun::first()->id
            && $request['client'] === [
                'product' => $icp->product, 'target_industries' => ['Software', 'Logistics'],
                'location' => 'Egypt', 'company_size' => ['min' => 50, 'max' => 300],
                'ideal_customer_description' => $icp->ideal_customer_description,
            ]);
        $run = $this->user->organization->agentRuns()->firstOrFail();
        $prospect = $run->prospects()->firstOrFail();
        $this->assertSame('completed', $run->status);
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->completed_at);
        $this->assertSame(1, $run->candidates_found);
        $this->assertSame(1, $run->prospects_qualified);
        $this->assertSame($this->user->organization_id, $prospect->organization_id);
        $this->assertSame(100, $prospect->score);
        $this->assertSame(2, $prospect->buyingSignals()->count());
        $this->get(Overview::getUrl(panel: 'app'))->assertSee('Integrated Logistics')->assertSee('ICP configured')->assertDontSee('Demo data');
        $this->get(ViewProspect::getUrl(['record' => $prospect->id], panel: 'app'))->assertOk()->assertSee('100 / 100')->assertSee('Why now?')->assertSee('https://example.com/jobs');
        Livewire::test(AgentRuns::class)->assertCanSeeTableRecords([$run]);
        $this->get(ViewAgentRun::getUrl(['record' => $run->id], panel: 'app'))->assertOk()->assertSee('Integrated Logistics')->assertSee('Completed');
        $this->actingAs(User::factory()->create());
        $this->get(Overview::getUrl(panel: 'app'))->assertOk()->assertDontSee('Integrated Logistics');
        $this->get(ViewProspect::getUrl(['record' => $prospect->id], panel: 'app'))->assertNotFound();
        $this->get(ViewAgentRun::getUrl(['record' => $run->id], panel: 'app'))->assertNotFound();
    }

    public function test_missing_icp_blocks_execution_without_creating_a_run(): void
    {
        Livewire::test(Overview::class)->callAction('runLeadAgent')->assertNotified('Configure your ICP before running the agent.');
        $this->assertDatabaseCount('agent_runs', 0);
        Http::assertNothingSent();
    }

    public function test_service_failure_displays_safe_feedback_and_updates_recent_activity(): void
    {
        IcpConfiguration::factory()->for($this->user->organization)->create();
        Http::fake(['*' => Http::response(['detail' => 'sensitive-secret-stack'], 500)]);
        Livewire::test(Overview::class)->callAction('runLeadAgent')
            ->assertNotified('Lead Intelligence service is temporarily unavailable.')
            ->assertDispatched('intelligence-updated');
        $this->get(Overview::getUrl(panel: 'app'))->assertOk()->assertSee('Failed')->assertDontSee('sensitive-secret-stack');
        $run = $this->user->organization->agentRuns()->firstOrFail();
        $this->get(ViewAgentRun::getUrl(['record' => $run->id], panel: 'app'))->assertOk()
            ->assertSee('Lead Intelligence service is temporarily unavailable.')->assertDontSee('sensitive-secret-stack');
    }

    public function test_actual_fastapi_contract_without_evidence_quality_is_supported(): void
    {
        IcpConfiguration::factory()->for($this->user->organization)->create();
        Http::fake(function (Request $request) {
            $data = $this->response($request['run_id']);
            unset($data['prospects'][0]['evidence_quality']);

            return Http::response($data);
        });
        $run = app(RunLeadIntelligenceAction::class)->execute($this->user);
        $prospect = $run->prospects()->firstOrFail();
        $this->assertNull($prospect->evidence_quality);
        $this->assertSame(90, $prospect->score);
    }

    public function test_empty_result_completes_with_zero_metrics_and_useful_next_steps(): void
    {
        IcpConfiguration::factory()->for($this->user->organization)->create();
        Http::fake(fn (Request $request) => Http::response(['run_id' => $request['run_id'], 'prospects' => []]));
        Livewire::test(Overview::class)->callAction('runLeadAgent')->assertNotified('Research completed');
        $run = $this->user->organization->agentRuns()->firstOrFail();
        $this->assertSame('completed', $run->status);
        $this->assertSame(0, $run->candidates_found);
        $this->assertSame(0, $run->prospects_qualified);
        $this->get(Overview::getUrl(panel: 'app'))->assertOk()->assertSee('No opportunities yet');
    }

    /** @return array<string, array{string}> */
    public static function failures(): array
    {
        return array_combine(
            ['mismatched run', 'invalid JSON', 'invalid classification', 'missing company', 'empty why now', 'invalid signal', 'unsafe URL', 'missing prospects', '500', '422', 'timeout', 'unavailable'],
            array_map(fn (string $value): array => [$value], ['mismatch', 'json', 'classification', 'company', 'why', 'signal', 'url', 'prospects', '500', '422', 'timeout', 'unavailable']),
        );
    }

    #[DataProvider('failures')]
    public function test_failures_mark_the_run_failed_without_partial_data_or_secret_exposure(string $failure): void
    {
        IcpConfiguration::factory()->for($this->user->organization)->create();
        Http::fake(function (Request $request) use ($failure) {
            if ($failure === 'timeout' || $failure === 'unavailable') {
                throw new ConnectionException($failure === 'timeout' ? 'cURL error 28: timed out sensitive-secret' : 'connection refused sensitive-secret');
            }
            if (in_array($failure, ['500', '422'], true)) {
                return Http::response(['detail' => 'sensitive-secret'], (int) $failure);
            }
            if ($failure === 'json') {
                return Http::response('not json sensitive-secret');
            }
            $data = $this->response($request['run_id']);
            match ($failure) {
                'mismatch' => $data['run_id'] = $request['run_id'] + 1,
                'classification' => $data['prospects'][0]['icp_fit'] = 'excellent',
                'company' => $data['prospects'][0]['company_name'] = null,
                'why' => $data['prospects'][0]['why_now'] = '   ',
                'signal' => $data['prospects'][0]['buying_signals'][0]['strength'] = 'excellent',
                'url' => $data['prospects'][0]['website'] = 'javascript:alert(1)',
                'prospects' => $data['prospects'] = null,
            };

            return Http::response($data);
        });
        try {
            app(RunLeadIntelligenceAction::class)->execute($this->user);
            $this->fail('Expected a safe integration failure.');
        } catch (LeadIntelligenceFailure $exception) {
            $this->assertStringNotContainsString('sensitive-secret', $exception->getMessage());
        }
        $run = $this->user->organization->agentRuns()->firstOrFail();
        $this->assertSame('failed', $run->status);
        $this->assertNotNull($run->failed_at);
        $this->assertNull($run->completed_at);
        $this->assertDatabaseCount('prospects', 0);
        $this->assertDatabaseCount('buying_signals', 0);
        $this->assertStringNotContainsString('sensitive-secret', $run->error_message);
        if (in_array($failure, ['timeout', 'unavailable'], true)) {
            $this->assertSame($failure === 'timeout'
                ? 'The agent run timed out. Please try again.'
                : 'Lead Intelligence service is temporarily unavailable.', $run->error_message);
        }
    }

    public function test_persistence_failure_rolls_back_the_complete_result(): void
    {
        IcpConfiguration::factory()->for($this->user->organization)->create();
        Http::fake(fn (Request $request) => Http::response($this->response($request['run_id'])));
        $this->mock(ScoreProspect::class, function (MockInterface $mock): void {
            $mock->shouldReceive('execute')->once()->andThrow(new RuntimeException('sensitive-stack'));
        });
        try {
            app(RunLeadIntelligenceAction::class)->execute($this->user);
            $this->fail('Expected persistence failure.');
        } catch (LeadIntelligenceFailure $exception) {
            $this->assertSame('The agent run could not be saved. Please try again.', $exception->getMessage());
        }
        $this->assertDatabaseCount('prospects', 0);
        $this->assertDatabaseCount('buying_signals', 0);
        $this->assertSame('failed', AgentRun::first()->status);
    }

    public function test_deduplication_and_unknown_external_fields_cannot_override_owner_or_score(): void
    {
        IcpConfiguration::factory()->for($this->user->organization)->create();
        Http::fake(function (Request $request) {
            $data = $this->response($request['run_id']);
            $first = $data['prospects'][0];
            $first['website'] = 'https://www.example.com/';
            $first['score'] = 0;
            $first['organization_id'] = 999;
            $second = $first;
            $second['website'] = 'http://example.com/?utm=demo';
            $third = $first;
            $third['company_name'] = 'No Website Company';
            $third['website'] = null;
            $third['evidence_quality'] = null;
            $fourth = $third;
            $fourth['company_name'] = '  no website company  ';
            $data['prospects'] = [$first, $second, $third, $fourth];

            return Http::response($data);
        });
        $run = app(RunLeadIntelligenceAction::class)->execute($this->user);
        $this->assertSame(4, $run->candidates_found);
        $this->assertSame(2, $run->prospects_qualified);
        $this->assertSame([100, 90], $run->prospects()->orderBy('id')->pluck('score')->all());
        $this->assertSame([$this->user->organization_id], $run->prospects()->distinct()->pluck('organization_id')->all());
    }

    /** @return array<string, mixed> */
    private function response(int $runId): array
    {
        return ['run_id' => $runId, 'prospects' => [[
            'company_name' => 'Integrated Logistics', 'website' => 'https://example.com',
            'icp_fit' => 'high', 'product_relevance' => 'high', 'evidence_quality' => 'high',
            'why_now' => 'The company is hiring and opening a new office.',
            'buying_signals' => [
                ['type' => 'hiring_growth', 'evidence' => '18 open operations roles.', 'source_url' => 'https://example.com/jobs', 'strength' => 'high'],
                ['type' => 'new_location', 'evidence' => 'A new office is announced.', 'source_url' => null, 'strength' => 'high'],
            ],
        ]]];
    }
}
