<?php

namespace App\Application\LeadIntelligence;

use App\Infrastructure\LeadIntelligence\FastApiLeadIntelligenceClient;
use App\Infrastructure\LeadIntelligence\LeadIntelligenceFailure;
use App\Models\AgentRun;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class RunLeadIntelligenceAction
{
    public function __construct(private FastApiLeadIntelligenceClient $client, private CompleteLeadIntelligenceRun $completion) {}

    public function execute(User $user): AgentRun
    {
        $organization = $user->organization;
        if (! $organization) {
            throw new LeadIntelligenceFailure('An organization is required to run the agent.');
        }
        $icp = $organization->icpConfiguration()->first();
        if (! $icp) {
            throw new LeadIntelligenceFailure('Configure your ICP before running the agent.');
        }
        $token = Str::random(64);
        $run = $organization->agentRuns()->make(['status' => 'running', 'started_at' => now()]);
        $run->forceFill(['initiated_by_user_id' => $user->id, 'callback_token_hash' => hash('sha256', $token)])->save();

        try {
            $callbackUrl = rtrim((string) config('services.lead_intelligence.callback_base_url', config('app.url')), '/')
                .route('agent-runs.result', ['run' => $run->id], false);
            $result = $this->client->run($run->id, $icp, ['url' => $callbackUrl, 'token' => $token]);
            if ($result !== null) {
                return $this->completion->complete($run, $result);
            }
        } catch (Throwable $exception) {
            $message = $exception instanceof LeadIntelligenceFailure ? $exception->getMessage() : 'The agent run could not be saved. Please try again.';
            $run = $this->completion->fail($run, $message);
            if ($run->status === 'completed') {
                return $run;
            }
            Log::warning('Lead intelligence run failed', ['run_id' => $run->id, 'error_type' => $exception::class, 'error_file' => $exception->getFile(), 'error_line' => $exception->getLine()]);
            throw new LeadIntelligenceFailure($message);
        }

        $run = $run->fresh();
        if ($run->status === 'failed') {
            throw new LeadIntelligenceFailure($run->error_message ?? 'The agent could not complete its research. Please try again.');
        }

        return $run;
    }
}
