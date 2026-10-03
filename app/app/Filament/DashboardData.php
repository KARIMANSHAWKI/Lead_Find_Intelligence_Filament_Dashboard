<?php

namespace App\Filament;

use App\Filament\Pages\ViewAgentRun;
use App\Filament\Pages\ViewProspect;
use App\Models\AgentRun;
use App\Models\Prospect;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DashboardData
{
    /** @return array<string, int> */
    public function metrics(): array
    {
        $summary = $this->prospects()->selectRaw('COUNT(*) as total, SUM(CASE WHEN score >= 80 THEN 1 ELSE 0 END) as high, SUM(CASE WHEN score BETWEEN 60 AND 79 THEN 1 ELSE 0 END) as medium')->first();

        return [
            'Total Prospects' => (int) $summary->total,
            'High Priority' => (int) $summary->high,
            'Medium Priority' => (int) $summary->medium,
            'Agent Runs' => AgentRun::query()->where('organization_id', Filament::auth()->user()->organization_id)->count(),
        ];
    }

    /** @return list<array{range: string, count: int}> */
    public function scoreDistribution(): array
    {
        $counts = $this->prospects()->selectRaw('COUNT(*) as total, SUM(CASE WHEN score BETWEEN 80 AND 100 THEN 1 ELSE 0 END) as high, SUM(CASE WHEN score BETWEEN 60 AND 79 THEN 1 ELSE 0 END) as medium, SUM(CASE WHEN score BETWEEN 40 AND 59 THEN 1 ELSE 0 END) as low, SUM(CASE WHEN score BETWEEN 0 AND 39 THEN 1 ELSE 0 END) as lowest, SUM(CASE WHEN score IS NULL THEN 1 ELSE 0 END) as unscored')->first();
        if (! $counts->total) {
            return [];
        }
        $ranges = [
            ['range' => '80–100', 'count' => (int) $counts->high],
            ['range' => '60–79', 'count' => (int) $counts->medium],
            ['range' => '40–59', 'count' => (int) $counts->low],
            ['range' => '0–39', 'count' => (int) $counts->lowest],
        ];
        if ($counts->unscored) {
            $ranges[] = ['range' => 'Not scored', 'count' => (int) $counts->unscored];
        }

        return $ranges;
    }

    /** @return list<array<string, mixed>> */
    public function opportunities(): array
    {
        return $this->prospects()->whereNotNull('score')->orderByDesc('score')->orderByDesc('id')->limit(5)
            ->with(['buyingSignals' => fn (HasMany $query): HasMany => $query->orderByRaw("CASE strength WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")->orderBy('id')])
            ->get()->map(fn (Prospect $prospect): array => [
                'company' => $prospect->company_name, 'score' => $prospect->score,
                'fit' => IntelligencePresentation::label($prospect->icp_fit),
                'signal' => $prospect->buyingSignals->first() ? IntelligencePresentation::label($prospect->buyingSignals->first()->type) : 'No verified signal',
                'priority' => IntelligencePresentation::priority($prospect->score),
                'color' => $prospect->score >= 80 ? 'primary' : 'gray',
                'why_now' => $prospect->why_now,
                'contacts' => count($prospect->contact_info['people'] ?? []),
                'url' => ViewProspect::getUrl(['record' => $prospect->id], panel: 'app'),
            ])->all();
    }

    /** @return list<array<string, string>> */
    public function activity(): array
    {
        return AgentRun::query()->where('organization_id', Filament::auth()->user()->organization_id)->latest()->limit(5)->get()
            ->map(fn (AgentRun $run): array => [
                'title' => 'Run #'.$run->id.' · '.IntelligencePresentation::label($run->status),
                'detail' => match ($run->status) {
                    'completed' => $run->prospects_qualified.' qualified opportunities · '.$run->candidates_found.' companies returned',
                    'failed' => 'Research could not be completed. View the run for details.',
                    'running' => 'Researching and qualifying companies.',
                    default => 'Waiting to start research.',
                },
                'time' => ($run->completed_at ?? $run->failed_at ?? $run->started_at ?? $run->created_at)->diffForHumans(),
                'icon' => match ($run->status) {
                    'completed' => 'heroicon-o-check-circle', 'failed' => 'heroicon-o-exclamation-triangle',
                    'running' => 'heroicon-o-magnifying-glass', default => 'heroicon-o-clock',
                },
                'url' => ViewAgentRun::getUrl(['record' => $run->id], panel: 'app'),
            ])->all();
    }

    /** @return Builder<Prospect> */
    private function prospects(): Builder
    {
        return Prospect::query()->where('organization_id', Filament::auth()->user()->organization_id);
    }
}
