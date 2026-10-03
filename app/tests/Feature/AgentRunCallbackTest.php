<?php

namespace Tests\Feature;

use App\Application\LeadIntelligence\CompleteLeadIntelligenceRun;
use App\Application\LeadIntelligence\RunLeadIntelligenceAction;
use App\Application\Scoring\ScoreProspect;
use App\Filament\Pages\Overview;
use App\Filament\Pages\ViewProspect;
use App\Models\AgentRun;
use App\Models\IcpConfiguration;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class AgentRunCallbackTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $token;

    protected string $url;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->user = User::factory()->create();
        IcpConfiguration::factory()->for($this->user->organization)->create();
        $this->actingAs($this->user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        config(['services.lead_intelligence.base_url' => 'http://lead-service.test', 'services.lead_intelligence.callback_base_url' => 'http://dashboard.test']);
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $this->token = $request['callback']['token'];
            $this->url = parse_url($request['callback']['url'], PHP_URL_PATH);

            return Http::response(['run_id' => $request['run_id'], 'status' => 'running'], 202);
        });
    }

    public function test_run_action_acknowledges_background_execution_and_announces_later_notification(): void
    {
        Livewire::test(Overview::class)->callAction('runLeadAgent')->assertNotified('Agent started');
        $run = $this->user->organization->agentRuns()->firstOrFail();
        $this->assertSame('running', $run->status);
        $this->assertSame($this->user->id, $run->initiated_by_user_id);
        $this->assertSame(hash('sha256', $this->token), $run->callback_token_hash);
        $this->assertArrayNotHasKey('callback_token_hash', $run->toArray());
        $this->assertDatabaseCount('prospects', 0);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://lead-service.test/api/v1/agent/run'
            && $request['callback']['url'] === 'http://dashboard.test'.$this->url && strlen($request['callback']['token']) === 64);
        $this->assertTrue(Filament::getPanel('app')->hasDatabaseNotifications());
    }

    public function test_authorized_callback_persists_scores_and_notifies_only_the_initiating_user_once(): void
    {
        $teammate = User::factory()->for($this->user->organization)->create();
        $outsider = User::factory()->create();
        $run = app(RunLeadIntelligenceAction::class)->execute($this->user);
        $payload = $this->callbackPayload($run);
        $payload['organization_id'] = $outsider->organization_id;
        $payload['prospects'][0]['organization_id'] = $outsider->organization_id;
        $payload['prospects'][0]['score'] = 0;
        $this->postJson($this->url, $payload, $this->headers())->assertOk()->assertJsonPath('status', 'completed');
        $prospect = $run->prospects()->firstOrFail();
        $this->assertSame($run->organization_id, $prospect->organization_id);
        $this->assertSame(85, $prospect->score);
        $this->assertSame(1, $prospect->buyingSignals()->count());
        $this->assertSame('hr.director@example.com', $prospect->contact_info['people'][0]['email']);
        $this->get(ViewProspect::getUrl(['record' => $prospect->id], panel: 'app'))
            ->assertOk()
            ->assertSee('HR Director')
            ->assertSee('hr.director@example.com');
        $this->assertSame(1, $run->fresh()->prospects_qualified);
        $this->assertNotNull($run->fresh()->completed_at);
        $this->assertSame('Agent finished', $this->user->notifications()->firstOrFail()->data['title']);
        $this->assertSame('/app/agent-runs/'.$run->id, $this->user->notifications()->firstOrFail()->data['actions'][0]['url']);
        $this->assertSame(0, $teammate->notifications()->count());
        $this->assertSame(0, $outsider->notifications()->count());
        $this->postJson($this->url, $payload, $this->headers())->assertOk();
        $this->assertDatabaseCount('prospects', 1);
        $this->assertDatabaseCount('buying_signals', 1);
        $this->assertSame(1, $this->user->notifications()->count());
        $this->get(Overview::getUrl(panel: 'app'))->assertSee('Callback Logistics');
    }

    public function test_callback_needs_the_token_even_for_an_authenticated_user(): void
    {
        $run = app(RunLeadIntelligenceAction::class)->execute($this->user);
        $this->postJson($this->url, $this->callbackPayload($run))->assertUnauthorized();
        $this->postJson($this->url, $this->callbackPayload($run), ['Authorization' => 'Bearer incorrect'])->assertUnauthorized();
        $this->assertSame('running', $run->fresh()->status);
        $this->assertDatabaseCount('prospects', 0);
    }

    public function test_one_runs_token_cannot_access_another_organizations_run(): void
    {
        $run = app(RunLeadIntelligenceAction::class)->execute($this->user);
        $otherUser = User::factory()->create();
        IcpConfiguration::factory()->for($otherUser->organization)->create();
        $ownToken = $this->token;
        $other = app(RunLeadIntelligenceAction::class)->execute($otherUser);
        $this->postJson($this->url, $this->callbackPayload($other), ['Authorization' => 'Bearer '.$ownToken])->assertUnauthorized();
        $this->assertSame('running', $run->fresh()->status);
        $this->assertSame('running', $other->fresh()->status);
    }

    public function test_invalid_result_or_run_id_does_not_persist_partial_data(): void
    {
        $run = app(RunLeadIntelligenceAction::class)->execute($this->user);
        $payload = $this->callbackPayload($run);
        $payload['prospects'][0]['icp_fit'] = 'excellent';
        $this->postJson($this->url, $payload, $this->headers())->assertUnprocessable();
        $payload = $this->callbackPayload($run);
        $payload['run_id'] = $run->id + 1;
        $this->postJson($this->url, $payload, $this->headers())->assertUnprocessable();
        $payload['run_id'] = (string) $run->id;
        $this->postJson($this->url, $payload, $this->headers())->assertUnprocessable();
        $this->assertDatabaseCount('prospects', 0);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertSame('running', $run->fresh()->status);
    }

    public function test_failed_callback_uses_safe_messages_and_notifies_once(): void
    {
        $run = app(RunLeadIntelligenceAction::class)->execute($this->user);
        $payload = ['run_id' => $run->id, 'status' => 'failed', 'error_code' => 'timeout', 'error_message' => 'sensitive-secret-stack'];
        $this->postJson($this->url, $payload, $this->headers())->assertOk()->assertJsonPath('status', 'failed');
        $this->assertSame('The agent run timed out. Please try again.', $run->fresh()->error_message);
        $this->assertNotNull($run->fresh()->failed_at);
        $notification = $this->user->notifications()->firstOrFail();
        $this->assertSame('Agent run failed', $notification->data['title']);
        $this->assertStringNotContainsString('sensitive-secret-stack', json_encode($notification->data));
        $this->postJson($this->url, $payload, $this->headers())->assertOk();
        $this->assertSame(1, $this->user->notifications()->count());
        $this->postJson($this->url, $this->callbackPayload($run), $this->headers())->assertConflict();
    }

    public function test_callback_does_not_require_browser_login_or_csrf(): void
    {
        $run = app(RunLeadIntelligenceAction::class)->execute($this->user);
        Filament::auth()->logout();
        $this->postJson($this->url, $this->callbackPayload($run), $this->headers())->assertOk();
    }

    public function test_recipient_moved_to_another_organization_does_not_receive_private_results(): void
    {
        $run = app(RunLeadIntelligenceAction::class)->execute($this->user);
        $this->user->forceFill(['organization_id' => User::factory()->create()->organization_id])->save();
        $this->postJson($this->url, $this->callbackPayload($run), $this->headers())->assertOk();
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_persistence_failure_is_safe_and_callback_can_be_retried(): void
    {
        $run = app(RunLeadIntelligenceAction::class)->execute($this->user);
        $this->mock(ScoreProspect::class, function (MockInterface $mock): void {
            $mock->shouldReceive('execute')->once()->andThrow(new RuntimeException('sensitive-storage-detail'));
        });
        $this->postJson($this->url, $this->callbackPayload($run), $this->headers())
            ->assertStatus(503)->assertDontSee('sensitive-storage-detail');
        $this->assertDatabaseCount('prospects', 0);
        $this->assertDatabaseCount('buying_signals', 0);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertSame('running', $run->fresh()->status);
        app()->forgetInstance(ScoreProspect::class);
        $this->postJson($this->url, $this->callbackPayload($run), $this->headers())->assertOk();
        $this->assertDatabaseCount('prospects', 1);
    }

    public function test_failure_callback_arriving_before_acknowledgement_does_not_announce_success(): void
    {
        Http::fake(function (Request $request) {
            app(CompleteLeadIntelligenceRun::class)->fail(AgentRun::findOrFail($request['run_id']), 'The agent run timed out. Please try again.');

            return Http::response(['run_id' => $request['run_id'], 'status' => 'running'], 202);
        });
        Livewire::test(Overview::class)->callAction('runLeadAgent')->assertNotified('The agent run timed out. Please try again.');
        $this->assertSame('failed', $this->user->organization->agentRuns()->firstOrFail()->status);
        $this->assertDatabaseCount('prospects', 0);
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->token];
    }

    /** @return array<string, mixed> */
    private function callbackPayload(AgentRun $run): array
    {
        return ['run_id' => $run->id, 'status' => 'completed', 'prospects' => [[
            'company_name' => 'Callback Logistics', 'icp_fit' => 'high', 'product_relevance' => 'high',
            'evidence_quality' => 'high', 'why_now' => 'Hiring operations staff now.',
            'buying_signals' => [['type' => 'hiring_growth', 'strength' => 'high', 'evidence' => '18 roles listed.', 'source_url' => null]],
            'contact_info' => [
                'company_emails' => [['email' => 'info@example.com', 'confidence' => 90, 'verification_status' => 'valid']],
                'company_phone_numbers' => ['+20 100 000 0000'],
                'people' => [[
                    'first_name' => 'Mona', 'last_name' => 'Ali',
                    'email' => 'hr.director@example.com', 'position' => 'HR Director',
                    'seniority' => 'senior', 'department' => 'hr',
                    'linkedin_url' => 'https://www.linkedin.com/in/mona-ali',
                    'phone_number' => null, 'confidence' => 95,
                    'verification_status' => 'valid',
                ]],
            ],
        ]]];
    }
}
