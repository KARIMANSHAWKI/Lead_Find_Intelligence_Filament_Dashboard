<?php

namespace App\Filament\Landlord\Pages;

use App\Models\Organization;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

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

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('createUser')
                ->label('Add user')
                ->icon(Heroicon::OutlinedUserPlus)
                ->authorize(fn (): bool => static::canAccess())
                ->modalHeading('Add organization user')
                ->modalDescription('Create an account for the application dashboard. Users in the same organization share their customer intelligence.')
                ->modalSubmitActionLabel('Create user')
                ->schema([
                    TextInput::make('name')->label('Full name')->required()->string()->trim()->maxLength(255)->rules(['regex:/\S/u']),
                    TextInput::make('email')->label('Email address')->email()->required()->trim()->maxLength(255)
                        ->mutateStateForValidationUsing(fn (?string $state): string => Str::lower(trim($state ?? '')))
                        ->dehydrateStateUsing(fn (string $state): string => Str::lower(trim($state)))
                        ->unique(User::class, 'email'),
                    Select::make('organization_id')->label('Organization')->required()->searchable()
                        ->options(fn (): array => Organization::query()->orderBy('name')->get()
                            ->mapWithKeys(fn (Organization $organization): array => [$organization->id => $organization->name.' · #'.$organization->id])->all())
                        ->rules(['integer', 'exists:organizations,id'])
                        ->helperText('Create an organization on the Organizations page before adding its users.'),
                    TextInput::make('password')->label('Password')->password()->revealable()->required()
                        ->rule(Password::min(12))->maxLength(72)->confirmed()
                        ->helperText('Use 12–72 characters. Share the password directly with the user.'),
                    TextInput::make('password_confirmation')->label('Confirm password')->password()->revealable()->required()
                        ->dehydrated(false),
                ])
                ->action(function (array $data): void {
                    abort_unless(static::canAccess(), 403);
                    Organization::findOrFail($data['organization_id'])->users()->create([
                        'name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'],
                    ]);
                    $this->resetTable();
                    Notification::make()->title('User created')->body('The user can now sign in to the application dashboard.')->success()->send();
                }),
        ];
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
