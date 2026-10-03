<x-filament-panels::page>
    <div wire:poll.15s.visible="refreshAgentResults" aria-hidden="true"></div>
    @if (! \Filament\Facades\Filament::auth()->user()->organization->icpConfiguration()->exists())
        <x-filament::section heading="Complete your ICP to start discovering opportunities" description="Tell the agent what you sell and which companies to prioritize." icon="heroicon-o-adjustments-horizontal">
            <x-filament::button tag="a" :href="\App\Filament\Pages\IcpConfiguration::getUrl(panel: 'app')">Configure ICP</x-filament::button>
        </x-filament::section>
    @else
        <div class="flex flex-wrap items-center gap-3 text-sm text-gray-600 dark:text-gray-400">
            <x-filament::badge color="success" icon="heroicon-o-check-circle">ICP configured</x-filament::badge>
            <span>Ready to discover your next opportunity.</span>
            <x-filament::link :href="\App\Filament\Pages\IcpConfiguration::getUrl(panel: 'app')">Review ICP</x-filament::link>
        </div>
    @endif
    <p wire:loading wire:target="mountAction,callMountedAction" role="status" class="text-sm text-gray-600 dark:text-gray-400">Starting the agent…</p>
    {{ $this->content }}
</x-filament-panels::page>
