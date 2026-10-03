<?php

namespace App\Filament\Pages;

use App\Models\Prospect;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Panel;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

class ViewProspect extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'prospects/{record}';

    protected string $view = 'filament.pages.view-prospect';

    #[Locked]
    public int $prospectId;

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'prospect-detail';
    }

    public function mount(int $record): void
    {
        $this->prospectId = $record;
        $this->prospect;
    }

    #[Computed]
    public function prospect(): Prospect
    {
        return Prospect::query()->where('organization_id', Filament::auth()->user()->organization_id)
            ->with(['buyingSignals', 'agentRun'])->findOrFail($this->prospectId);
    }

    public function getTitle(): string
    {
        return $this->prospect->company_name;
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [Action::make('back')->label('Back to Prospects')->url(Prospects::getUrl(panel: 'app'))];
    }
}
