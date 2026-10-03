<?php

namespace App\Application\LeadIntelligence;

use App\Application\Scoring\ScoreProspect;
use App\Filament\Pages\ViewAgentRun;
use App\Infrastructure\LeadIntelligence\LeadIntelligenceFailure;
use App\Models\AgentRun;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompleteLeadIntelligenceRun
{
    public function __construct(private ScoreProspect $scoring) {}

    public function complete(AgentRun $run, LeadIntelligenceResult $result): AgentRun
    {
        if ($result->runId !== $run->id) {
            throw new LeadIntelligenceFailure('The service returned a response for a different run.');
        }

        return DB::transaction(function () use ($run, $result): AgentRun {
            $run = AgentRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($run->status === 'completed') {
                return $run;
            }
            if ($run->status === 'failed') {
                throw new LeadIntelligenceFailure('This agent run has already finished.');
            }
            $organization = $run->organization;
            $seen = [];
            $qualified = 0;
            foreach ($result->prospects as $data) {
                $identity = $this->identity($data['website'], $data['company_name']);
                if (isset($seen[$identity])) {
                    continue;
                }
                $seen[$identity] = true;
                $prospect = $organization->prospects()->create([
                    'agent_run_id' => $run->id,
                    'company_name' => $data['company_name'], 'website' => $data['website'],
                    'source' => 'lead_intelligence', 'icp_fit' => $data['icp_fit'],
                    'product_relevance' => $data['product_relevance'], 'evidence_quality' => $data['evidence_quality'],
                    'why_now' => $data['why_now'], 'contact_info' => $data['contact_info'],
                    'status' => 'qualified',
                ]);
                foreach (collect($data['buying_signals'])->unique(fn (array $signal): string => json_encode($signal))->values() as $signal) {
                    $prospect->buyingSignals()->create($signal);
                }
                $this->scoring->execute($prospect);
                $qualified++;
            }
            $run->update([
                'status' => 'completed', 'completed_at' => now(),
                'candidates_found' => count($result->prospects), 'prospects_qualified' => $qualified,
            ]);
            $this->notify($run);

            return $run;
        });
    }

    public function fail(AgentRun $run, string $message): AgentRun
    {
        return DB::transaction(function () use ($run, $message): AgentRun {
            $run = AgentRun::query()->lockForUpdate()->findOrFail($run->id);
            if (in_array($run->status, ['completed', 'failed'], true)) {
                return $run;
            }
            $run->update(['status' => 'failed', 'failed_at' => now(), 'completed_at' => null, 'error_message' => $message]);
            $this->notify($run);

            return $run;
        });
    }

    private function notify(AgentRun $run): void
    {
        $recipient = User::query()->whereKey($run->initiated_by_user_id)->where('organization_id', $run->organization_id)->first();
        if (! $recipient) {
            return;
        }
        $notification = Notification::make()
            ->title($run->status === 'completed' ? 'Agent finished' : 'Agent run failed')
            ->body($run->status === 'completed' ? $run->prospects_qualified.' qualified opportunities are ready to review.' : $run->error_message)
            ->actions([Action::make('viewRun')->label('View results')->url(ViewAgentRun::getUrl(['record' => $run->id], isAbsolute: false, panel: 'app'))]);
        $run->status === 'completed' ? $notification->success() : $notification->danger();
        $recipient->notifyNow($notification->toDatabase());
    }

    private function identity(?string $website, string $company): string
    {
        if ($website) {
            $host = preg_replace('/^www\./i', '', (string) parse_url($website, PHP_URL_HOST));
            $port = parse_url($website, PHP_URL_PORT);
            $path = rtrim((string) parse_url($website, PHP_URL_PATH), '/');

            return 'website:'.Str::lower($host).($port && ! in_array($port, [80, 443], true) ? ':'.$port : '').$path;
        }

        return 'company:'.Str::lower(preg_replace('/\s+/u', ' ', trim($company)));
    }
}
