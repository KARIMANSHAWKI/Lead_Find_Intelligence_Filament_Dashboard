<?php

namespace App\Filament\Landlord\Pages;

use App\Models\Organization;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class Organizations extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $title = 'Organizations';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.landlord.pages.organizations';

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'landlord'
            && Filament::auth()->user()?->is_platform_admin === true;
    }

    public function getSubheading(): ?string
    {
        return 'All organizations using Lead Intelligence.';
    }

    public function table(Table $table): Table
    {
        abort_unless(static::canAccess(), 403);

        return $table->query(Organization::query()->withCount('users'))
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('users_count')->label('Users')->numeric()->sortable(),
                TextColumn::make('created_at')->label('Created')->dateTime('M j, Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No organizations found')
            ->emptyStateDescription('Try another organization search.');
    }
}
