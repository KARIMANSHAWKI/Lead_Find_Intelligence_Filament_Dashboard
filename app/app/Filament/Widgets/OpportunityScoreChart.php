<?php

namespace App\Filament\Widgets;

use App\Filament\DashboardData;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\On;

class OpportunityScoreChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Opportunity Score Distribution';

    protected ?string $maxHeight = '220px';

    protected ?string $emptyStateHeading = 'No scores to display yet';

    protected ?string $emptyStateDescription = 'Score distribution will appear when qualified opportunities are available.';

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $ranges = app(DashboardData::class)->scoreDistribution();

        if ($ranges === []) {
            return [];
        }

        return [
            'datasets' => [[
                'label' => 'Prospects',
                'data' => array_column($ranges, 'count'),
                'borderRadius' => 6,
                'borderSkipped' => false,
                'barThickness' => 22,
            ]],
            'labels' => array_column($ranges, 'range'),
        ];
    }

    public function getDescription(): ?string
    {
        $ranges = app(DashboardData::class)->scoreDistribution();

        if ($ranges === []) {
            return 'Higher scores indicate stronger opportunities.';
        }

        return implode(' · ', array_map(
            fn (array $range): string => $range['range'].': '.$range['count'].' prospects',
            $ranges,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'maintainAspectRatio' => false,
            'animation' => false,
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0], 'title' => ['display' => true, 'text' => 'Prospects']],
                'y' => ['grid' => ['display' => false], 'title' => ['display' => true, 'text' => 'Score range']],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    #[On('intelligence-updated')]
    public function refreshIntelligence(): void
    {
        $this->cachedData = null;
        $this->updateChartData();
    }
}
