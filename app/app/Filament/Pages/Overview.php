<?php

namespace App\Filament\Pages;

use App\Application\LeadIntelligence\RunLeadIntelligenceAction;
use App\Filament\Widgets\LeadStatsOverview;
use App\Filament\Widgets\OpportunityScoreChart;
use App\Filament\Widgets\RecentAgentActivity;
use App\Filament\Widgets\TopOpportunities;
use App\Infrastructure\LeadIntelligence\LeadIntelligenceFailure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

class Overview extends Dashboard
{
    protected static ?string $title = 'Lead Intelligence';

    protected static ?string $navigationLabel = 'Overview';

    protected string $view = 'filament.pages.overview';

    /**
     * @return array<class-string<Widget>>
     */
    public function getWidgets(): array
    {
        return [
            LeadStatsOverview::class,
            OpportunityScoreChart::class,
            TopOpportunities::class,
            RecentAgentActivity::class,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function getColumns(): array
    {
        return ['default' => 1, 'xl' => 3];
    }

    public function refreshAgentResults(): void
    {
        $this->dispatch('intelligence-updated');
    }

    public function getSubheading(): ?string
    {
        return 'Find companies worth contacting now — and understand WHY NOW.';
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('runLeadAgent')
                ->label('Run Lead Agent')
                ->icon(Heroicon::OutlinedPlay)
                ->action(function (): void {
                    try {
                        $run = app(RunLeadIntelligenceAction::class)->execute(Filament::auth()->user());
                        Notification::make()->title($run->status === 'completed' ? 'Research completed' : 'Agent started')
                            ->body($run->status !== 'completed'
                                ? 'We’ll notify you when the agent finishes. You can continue using the dashboard.'
                                : ($run->prospects_qualified > 0
                                ? $run->prospects_qualified.' qualified opportunities are ready to review.'
                                : 'No qualified opportunities found. Refine your ICP and try again.'))
                            ->success()->send();
                    } catch (LeadIntelligenceFailure $exception) {
                        $hasIcp = Filament::auth()->user()->organization->icpConfiguration()->exists();
                        Notification::make()->title($exception->getMessage())->danger()
                            ->actions($hasIcp
                                ? [Action::make('viewRuns')->label('View run history')->url(AgentRuns::getUrl(panel: 'app'))]
                                : [Action::make('configureIcp')->label('Configure ICP')->url(IcpConfiguration::getUrl(panel: 'app'))])
                            ->send();
                    }
                    $this->dispatch('intelligence-updated');
                }),
        ];
    }
}
