<?php

namespace App\Filament\Pages;

use App\Filament\IntelligencePresentation as Presentation;
use App\Models\AgentRun;
use App\Models\Prospect;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

class ViewAgentRun extends Page implements HasTable
{
    use InteractsWithTable;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'agent-runs/{record}';

    protected string $view = 'filament.pages.view-agent-run';

    #[Locked]
    public int $runId;

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'agent-run-detail';
    }

    public function mount(int $record): void
    {
        $this->runId = $record;
        $this->run;
    }

    #[Computed]
    public function run(): AgentRun
    {
        return AgentRun::query()->where('organization_id', Filament::auth()->user()->organization_id)->findOrFail($this->runId);
    }

    public function getTitle(): string
    {
        return 'Run #'.$this->run->id;
    }

    public function table(Table $table): Table
    {
        return $table->query(Prospect::query()->where('organization_id', Filament::auth()->user()->organization_id)->where('agent_run_id', $this->runId))
            ->columns([
                TextColumn::make('company_name')->label('Company')->wrap(),
                TextColumn::make('score')->placeholder('—')->sortable(),
                TextColumn::make('icp_fit')->label('ICP Fit')->badge()->formatStateUsing(Presentation::label(...))->color(Presentation::color(...)),
                TextColumn::make('why_now')->label('WHY NOW')->limit(100)->wrap(),
            ])
            ->recordUrl(fn (Prospect $record): string => ViewProspect::getUrl(['record' => $record->id], panel: 'app'))
            ->recordActions([Action::make('view')->label('View prospect')->url(fn (Prospect $record): string => ViewProspect::getUrl(['record' => $record->id], panel: 'app'))])
            ->defaultSort('score', 'desc')
            ->emptyStateHeading('No qualified prospects for this run')
            ->emptyStateDescription('Review the run status, refine your ICP, or start another run from Overview.');
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [Action::make('back')->label('Back to Agent Runs')->url(AgentRuns::getUrl(panel: 'app'))];
    }
}
