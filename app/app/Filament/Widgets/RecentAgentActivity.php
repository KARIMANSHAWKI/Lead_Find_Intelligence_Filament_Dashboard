<?php

namespace App\Filament\Widgets;

use App\Filament\DashboardData;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class RecentAgentActivity extends Widget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 1;

    protected string $view = 'filament.widgets.recent-agent-activity';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['activity' => app(DashboardData::class)->activity()];
    }

    #[On('intelligence-updated')]
    public function refreshIntelligence(): void {}
}
