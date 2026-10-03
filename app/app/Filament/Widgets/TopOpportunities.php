<?php

namespace App\Filament\Widgets;

use App\Filament\DashboardData;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class TopOpportunities extends Widget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = ['default' => 1, 'xl' => 2];

    protected string $view = 'filament.widgets.top-opportunities';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['opportunities' => app(DashboardData::class)->opportunities()];
    }

    #[On('intelligence-updated')]
    public function refreshIntelligence(): void {}
}
