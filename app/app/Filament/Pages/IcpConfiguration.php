<?php

namespace App\Filament\Pages;

use App\Models\Organization;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;

class IcpConfiguration extends Page
{
    protected static ?string $title = 'ICP Configuration';

    protected static ?string $slug = 'icp-configuration';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.icp-configuration';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * @var list<string>
     */
    private const FIELDS = [
        'product', 'target_industries', 'location',
        'company_size_min', 'company_size_max', 'ideal_customer_description',
    ];

    public function getSubheading(): ?string
    {
        return 'Tell the Lead Intelligence Agent what your ideal customer looks like.';
    }

    public function mount(): void
    {
        $configuration = $this->authenticatedOrganization()->icpConfiguration()->first();

        $this->form->fill($configuration?->only(self::FIELDS) ?? []);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Target Customer')
                    ->description('Define the companies your Lead Intelligence Agent should prioritize.')
                    ->schema([
                        TextInput::make('product')
                            ->label('Product / Service')
                            ->placeholder('Recruitment Management Software')
                            ->helperText('Describe the product or service you want to find customers for.')
                            ->required()
                            ->string()
                            ->trim()
                            ->maxLength(255),
                        TagsInput::make('target_industries')
                            ->label('Target Industries')
                            ->placeholder('Add an industry')
                            ->helperText('Type an industry and press Enter. Examples: Software, Logistics, Retail, Healthcare.')
                            ->required()
                            ->rules(['array', 'list', 'min:1'])
                            ->nestedRecursiveRules(['required', 'string', 'max:100', 'regex:/\S/u'])
                            ->validationMessages(['regex' => 'Each industry must contain a name.']),
                        TextInput::make('location')
                            ->placeholder('Cairo, Egypt')
                            ->helperText('Enter the country, city, or region you want to target.')
                            ->required()
                            ->string()
                            ->trim()
                            ->maxLength(255),
                    ]),
                Section::make('Company Profile')
                    ->description('Set an employee range, or leave either bound empty for an open range.')
                    ->columns(['default' => 1, 'sm' => 2])
                    ->schema([
                        TextInput::make('company_size_min')
                            ->label('Minimum Employees')
                            ->placeholder('50')
                            ->nullable()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(2147483647),
                        TextInput::make('company_size_max')
                            ->label('Maximum Employees')
                            ->placeholder('300')
                            ->nullable()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(2147483647)
                            ->rule('gte:data.company_size_min', fn (Get $get): bool => filled($get('company_size_min')) && filled($get('company_size_max')))
                            ->validationMessages(['gte' => 'Maximum employees must be greater than or equal to minimum employees.']),
                    ]),
                Section::make('Additional Context')
                    ->description('Help the agent recognize what makes a company a good fit.')
                    ->schema([
                        Textarea::make('ideal_customer_description')
                            ->label('Ideal Customer Description')
                            ->placeholder('Companies actively growing their teams and struggling with recruitment operations.')
                            ->helperText('This gives the Lead Intelligence Agent additional ICP context. Optional.')
                            ->nullable()
                            ->string()
                            ->trim()
                            ->maxLength(5000)
                            ->rows(4),
                    ]),
            ]);
    }

    public function save(): void
    {
        $organization = $this->authenticatedOrganization();
        $data = Arr::only($this->form->getState(), self::FIELDS);

        $organization->icpConfiguration()->updateOrCreate([], $data);
        $organization->unsetRelation('icpConfiguration');

        Notification::make()
            ->title('ICP configuration saved successfully.')
            ->success()
            ->send();
    }

    private function authenticatedOrganization(): Organization
    {
        $user = Filament::auth()->user();

        abort_unless($user instanceof User && $user->organization, 403);

        return $user->organization;
    }
}
