<x-filament-widgets::widget>
    <x-filament::section heading="Recent Agent Activity" description="Your research runs · most recent first">
        <ol class="li-activity">
            @forelse ($activity as $event)
                <li class="li-activity-event">
                    <div class="li-activity-icon" aria-hidden="true">
                        <x-filament::icon :icon="$event['icon']" class="li-small-icon" />
                    </div>
                    <div class="li-activity-content">
                        <h3><x-filament::link :href="$event['url']">{{ $event['title'] }}</x-filament::link></h3>
                        <p>{{ $event['detail'] }}</p>
                        <span class="li-activity-time">{{ $event['time'] }}</span>
                    </div>
                </li>
            @empty
                <li>
                    <x-filament::empty-state :contained="false" :compact="true" icon="heroicon-o-command-line"
                        heading="No activity yet" description="Start a run from Overview to see your research history." />
                </li>
            @endforelse
        </ol>
    </x-filament::section>
</x-filament-widgets::widget>
