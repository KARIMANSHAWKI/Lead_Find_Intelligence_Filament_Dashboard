<x-filament-panels::page>
    @php($run = $this->run)
    <x-filament::section heading="Run Summary">
        <div class="flex flex-wrap items-center gap-4">
            <x-filament::badge :color="\App\Filament\IntelligencePresentation::color($run->status)">{{ \App\Filament\IntelligencePresentation::label($run->status) }}</x-filament::badge>
            <p>Duration: {{ \App\Filament\IntelligencePresentation::duration($run) }}</p>
        </div>
        <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            @foreach (['candidates_found' => 'Candidates Found', 'prospects_qualified' => 'Qualified Prospects'] as $field => $label)
                <div><dt>{{ $label }}</dt><dd class="text-2xl font-semibold">{{ $run->$field }}</dd></div>
            @endforeach
            @foreach (['started_at' => 'Started', 'completed_at' => 'Completed', 'failed_at' => 'Failed'] as $field => $label)
                <div><dt>{{ $label }}</dt><dd>{{ $run->$field?->format('M j, Y H:i') ?? '—' }}</dd></div>
            @endforeach
        </dl>
    </x-filament::section>
    @if ($run->status === 'failed')
        <x-filament::section heading="Run could not be completed" icon="heroicon-o-exclamation-triangle">
            <p>{{ \Illuminate\Support\Str::limit($run->error_message ?: 'The run could not be completed. Please try again.', 500) }}</p>
        </x-filament::section>
    @endif
    <x-filament::section heading="Related Prospects">
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
