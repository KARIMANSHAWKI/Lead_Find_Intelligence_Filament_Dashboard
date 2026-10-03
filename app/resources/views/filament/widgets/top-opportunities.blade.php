<x-filament-widgets::widget>
    <x-filament::section heading="Top Opportunities" description="Your strongest company opportunities, with a clear reason WHY NOW.">
        <ol class="li-opportunities">
            @forelse ($opportunities as $opportunity)
                <li class="li-opportunity">
                    <div class="li-opportunity-heading">
                        <h3><x-filament::link :href="$opportunity['url']">{{ $opportunity['company'] }}</x-filament::link></h3>
                        <div class="li-score" aria-label="Score {{ $opportunity['score'] }} out of 100">
                            {{ $opportunity['score'] }}<span>/ 100</span>
                        </div>
                    </div>
                    <div class="li-opportunity-meta">
                        <x-filament::badge :color="$opportunity['color']">{{ $opportunity['priority'] }}</x-filament::badge>
                        <span>ICP fit: <strong>{{ $opportunity['fit'] }}</strong></span>
                        <span class="li-signal">
                            <x-filament::icon icon="heroicon-o-bolt" class="li-small-icon" />
                            <span>Strongest signal: {{ $opportunity['signal'] }}</span>
                        </span>
                        @if ($opportunity['contacts'] > 0)
                            <x-filament::link :href="$opportunity['url'].'#contacts'" icon="heroicon-o-user-group">{{ $opportunity['contacts'] }} {{ \Illuminate\Support\Str::plural('contact', $opportunity['contacts']) }}</x-filament::link>
                        @endif
                    </div>
                    <p class="li-why-now"><span>WHY NOW</span> {{ $opportunity['why_now'] }}</p>
                </li>
            @empty
                <li>
                    <x-filament::empty-state :contained="false" :compact="true" icon="heroicon-o-building-office-2"
                        heading="No opportunities yet" description="Configure your ICP and run the agent to find companies worth contacting now." />
                </li>
            @endforelse
        </ol>
    </x-filament::section>
</x-filament-widgets::widget>
