<?php

namespace App\Filament\Pages;

use App\Filament\IntelligencePresentation as Presentation;
use App\Models\Prospect;
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
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prospects extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $title = 'Prospects';

    protected static ?string $slug = 'prospects';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.prospects';

    public function getSubheading(): ?string
    {
        return 'Qualified companies, buying signals, and clear reasons WHY NOW.';
    }

    public function table(Table $table): Table
    {
        $classifications = ['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'];

        return $table
            ->query(Prospect::query()->where('organization_id', Filament::auth()->user()->organization_id)
                ->with(['buyingSignals' => fn (HasMany $query): HasMany => $query
                    ->orderByRaw("CASE strength WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
                    ->orderBy('id')]))
            ->columns([
                TextColumn::make('company_name')->label('Company')
                    ->description(fn (Prospect $record): ?string => $record->website)
                    ->searchable(['company_name', 'website'])->sortable()->wrap(),
                TextColumn::make('score')->placeholder('—')->sortable()->description(fn (Prospect $record): string => Presentation::priority($record->score)),
                TextColumn::make('icp_fit')->label('ICP Fit')->badge()
                    ->formatStateUsing(Presentation::label(...))->color(Presentation::color(...)),
                TextColumn::make('product_relevance')->badge()
                    ->formatStateUsing(Presentation::label(...))->color(Presentation::color(...)),
                TextColumn::make('strongest_signal')->label('Strongest Signal')
                    ->getStateUsing(fn (Prospect $record): string => $record->buyingSignals->first()
                        ? Presentation::label($record->buyingSignals->first()->type) : 'No verified signal')->wrap(),
                TextColumn::make('status')->badge()->placeholder('—')
                    ->formatStateUsing(Presentation::label(...))->color(Presentation::color(...)),
                TextColumn::make('created_at')->label('Created At')->dateTime('M j, Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('icp_fit')->label('ICP Fit')->options($classifications),
                SelectFilter::make('product_relevance')->options($classifications),
                SelectFilter::make('agent_run_id')->label('Agent Run')
                    ->options(fn (): array => Filament::auth()->user()->organization->agentRuns()->latest()->get()
                        ->mapWithKeys(fn ($run): array => [$run->id => 'Run #'.$run->id.' · '.Presentation::label($run->status)])->all()),
                SelectFilter::make('status')->options(fn (): array => Filament::auth()->user()->organization->prospects()
                    ->whereNotNull('status')->distinct()->pluck('status')->mapWithKeys(fn (string $status): array => [$status => Presentation::label($status)])->all()),
            ])
            ->recordUrl(fn (Prospect $record): string => ViewProspect::getUrl(['record' => $record->id], panel: 'app'))
            ->recordActions([
                Action::make('contacts')->label('Contacts')->icon(Heroicon::OutlinedUserGroup)
                    ->visible(fn (Prospect $record): bool => ! empty($record->contact_info['people']) || ! empty($record->contact_info['company_emails']) || ! empty($record->contact_info['company_phone_numbers']))
                    ->url(fn (Prospect $record): string => ViewProspect::getUrl(['record' => $record->id], panel: 'app').'#contacts'),
                Action::make('view')->label('View')->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Prospect $record): string => ViewProspect::getUrl(['record' => $record->id], panel: 'app')),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No prospects yet')
            ->emptyStateDescription('Run the Lead Intelligence Agent to discover qualified opportunities.')
            ->emptyStateIcon(Heroicon::OutlinedBuildingOffice2)
            ->emptyStateActions([Action::make('overview')->label('Go to Overview')->url(Overview::getUrl(panel: 'app'))]);
    }
}
