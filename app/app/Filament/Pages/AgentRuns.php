<?php

namespace App\Filament\Pages;

use App\Filament\IntelligencePresentation as Presentation;
use App\Models\AgentRun;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AgentRuns extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $title = 'Agent Runs';

    protected static ?string $slug = 'agent-runs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.agent-runs';

    public function getSubheading(): ?string
    {
        return 'Review your research workflows, results, and execution history.';
    }

    public function table(Table $table): Table
    {
        return $table->query(AgentRun::query()->where('organization_id', Filament::auth()->user()->organization_id))
            ->columns([
                TextColumn::make('id')->label('Run')->formatStateUsing(fn (int $state): string => 'Run #'.$state)->sortable(),
                TextColumn::make('status')->badge()->formatStateUsing(Presentation::label(...))->color(Presentation::color(...)),
                TextColumn::make('started_at')->label('Started')->dateTime('M j, Y H:i')->placeholder('—'),
                TextColumn::make('completed_at')->label('Completed')->dateTime('M j, Y H:i')->placeholder('—'),
                TextColumn::make('candidates_found')->label('Candidates Found'),
                TextColumn::make('prospects_qualified')->label('Qualified Prospects'),
                TextColumn::make('duration')->getStateUsing(Presentation::duration(...)),
            ])
            ->filters([SelectFilter::make('status')->options(['pending' => 'Pending', 'running' => 'Running', 'completed' => 'Completed', 'failed' => 'Failed'])])
            ->recordUrl(fn (AgentRun $record): string => ViewAgentRun::getUrl(['record' => $record->id], panel: 'app'))
            ->recordActions([Action::make('view')->label('View run')->url(fn (AgentRun $record): string => ViewAgentRun::getUrl(['record' => $record->id], panel: 'app'))])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No agent runs yet')
            ->emptyStateDescription('Configure your ICP, then run the Lead Intelligence Agent from Overview.')
            ->emptyStateIcon(Heroicon::OutlinedCommandLine)
            ->emptyStateActions([Action::make('overview')->label('Go to Overview')->url(Overview::getUrl(panel: 'app'))]);
    }
}
