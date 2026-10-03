<?php

namespace App\Filament\Landlord\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class Users extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $title = 'Users';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.landlord.pages.users';

    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'landlord'
            && Filament::auth()->user()?->is_platform_admin === true;
    }

    public function getSubheading(): ?string
    {
        return 'All platform accounts and their organizations.';
    }

    public function table(Table $table): Table
    {
        abort_unless(static::canAccess(), 403);

        return $table->query(User::query()->with('organization'))
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable()->wrap(),
                TextColumn::make('organization.name')->label('Organization')->searchable()->sortable(),
                TextColumn::make('is_platform_admin')->label('Access')->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Platform admin' : 'Organization user')
                    ->color(fn (bool $state): string => $state ? 'primary' : 'gray'),
                TextColumn::make('created_at')->label('Joined')->dateTime('M j, Y')->sortable(),
            ])
            ->filters([SelectFilter::make('organization_id')->label('Organization')->relationship('organization', 'name')->searchable()->preload()])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No users found')
            ->emptyStateDescription('Try another search or organization filter.');
    }
}
