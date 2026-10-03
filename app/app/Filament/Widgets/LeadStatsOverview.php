<?php

namespace App\Filament\Widgets;

use App\Filament\DashboardData;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class LeadStatsOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected int|array|null $columns = ['default' => 1, 'sm' => 2, 'xl' => 4];

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $metrics = app(DashboardData::class)->metrics();

        return [
            Stat::make('Total Prospects', $metrics['Total Prospects'] ?? 0)
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->description('Company opportunities discovered'),
            Stat::make('High Priority', $metrics['High Priority'] ?? 0)
                ->icon(Heroicon::OutlinedStar)
                ->description('Scores of 80–100 · strongest fit')
                ->descriptionColor('primary')
                ->extraAttributes(['class' => 'li-priority-stat']),
            Stat::make('Medium Priority', $metrics['Medium Priority'] ?? 0)
                ->icon(Heroicon::OutlinedBolt)
                ->description('Scores of 60–79 · worth exploring'),
            Stat::make('Agent Runs', $metrics['Agent Runs'] ?? 0)
                ->icon(Heroicon::OutlinedCommandLine)
                ->description('Research workflows recorded'),
        ];
    }

    #[On('intelligence-updated')]
    public function refreshIntelligence(): void
    {
        $this->cachedStats = null;
    }
}
