<x-filament-panels::page>
    @php
        $prospect = $this->prospect;
        $website = \App\Filament\IntelligencePresentation::safeUrl($prospect->website);
        $sources = $prospect->buyingSignals->pluck('source_url')->map(fn ($url) => \App\Filament\IntelligencePresentation::safeUrl($url))->filter()->unique();
        $contactInfo = $prospect->contact_info ?? [];
        $people = $contactInfo['people'] ?? [];
        $companyEmails = $contactInfo['company_emails'] ?? [];
        $companyPhones = $contactInfo['company_phone_numbers'] ?? [];
    @endphp
    <x-filament::section heading="Opportunity">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0 space-y-2 break-words">
                @if ($website)
                    <x-filament::link :href="$website" target="_blank" rel="noopener noreferrer">{{ $prospect->website }}</x-filament::link>
                @elseif ($prospect->website)
                    <p class="break-all">{{ $prospect->website }}</p>
                @endif
                <div class="flex flex-wrap gap-2">
                    <x-filament::badge :color="$prospect->score !== null && $prospect->score >= 80 ? 'primary' : 'gray'">
                        {{ \App\Filament\IntelligencePresentation::priority($prospect->score) }}
                    </x-filament::badge>
                    @if ($prospect->status)
                        <x-filament::badge :color="\App\Filament\IntelligencePresentation::color($prospect->status)">{{ \App\Filament\IntelligencePresentation::label($prospect->status) }}</x-filament::badge>
                    @endif
                </div>
            </div>
            <p class="text-3xl font-semibold tabular-nums">{{ $prospect->score !== null ? $prospect->score.' / 100' : 'Not scored yet' }}</p>
        </div>
    </x-filament::section>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        @foreach (['icp_fit' => 'ICP Fit', 'product_relevance' => 'Product Relevance', 'evidence_quality' => 'Evidence Quality'] as $field => $label)
            <x-filament::section :heading="$label">
                <x-filament::badge :color="\App\Filament\IntelligencePresentation::color($prospect->$field)">{{ \App\Filament\IntelligencePresentation::label($prospect->$field) }}</x-filament::badge>
            </x-filament::section>
        @endforeach
    </div>
    <x-filament::section heading="Why now?" icon="heroicon-o-bolt" description="The reason this company is worth contacting now.">
        <p class="break-words whitespace-pre-line text-base leading-relaxed">{{ $prospect->why_now }}</p>
    </x-filament::section>
    <div id="contacts" class="scroll-mt-6">
        <x-filament::section heading="Decision-makers & contacts" icon="heroicon-o-user-group" description="Connect with the people behind this opportunity.">
            <div class="space-y-6">
                @if ($companyEmails || $companyPhones)
                    <div class="li-company-contacts">
                        <h3 class="font-semibold">Company contacts</h3>
                        <div class="flex flex-wrap gap-x-6 gap-y-3">
                            @foreach ($companyEmails as $contact)
                                @if ($emailUrl = \App\Filament\IntelligencePresentation::emailUrl($contact['email'] ?? null, $prospect->company_name))
                                    <x-filament::link :href="$emailUrl" icon="heroicon-o-envelope">{{ $contact['email'] }}</x-filament::link>
                                @endif
                            @endforeach
                            @foreach ($companyPhones as $phone)
                                @if ($phoneUrl = \App\Filament\IntelligencePresentation::phoneUrl($phone))
                                    <x-filament::link :href="$phoneUrl" icon="heroicon-o-phone">{{ $phone }}</x-filament::link>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
                <div class="li-contacts-grid">
                    @forelse ($people as $contact)
                        @php
                            $name = trim(implode(' ', array_filter([$contact['first_name'] ?? null, $contact['last_name'] ?? null])));
                            $email = $contact['email'] ?? null;
                            $emailUrl = \App\Filament\IntelligencePresentation::emailUrl($email, $prospect->company_name, $name);
                            $meetingUrl = \App\Filament\IntelligencePresentation::meetingUrl($email, $prospect->company_name);
                            $linkedin = \App\Filament\IntelligencePresentation::safeUrl($contact['linkedin_url'] ?? null);
                            $verification = $contact['verification_status'] ?? null;
                            $initials = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($contact['first_name'] ?? $email ?? '?', 0, 1).\Illuminate\Support\Str::substr($contact['last_name'] ?? '', 0, 1));
                        @endphp
                        <article class="li-contact-card">
                            <div class="li-contact-heading">
                                <span class="li-contact-avatar" aria-hidden="true">{{ $initials }}</span>
                                <div class="min-w-0">
                                    <h3 class="font-semibold break-words" dir="auto">{{ $name ?: ($email ?: 'Business contact') }}</h3>
                                    <p class="li-contact-role">{{ $contact['position'] ?? 'Role not supplied' }}</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @foreach (['department', 'seniority'] as $field)
                                    @if ($contact[$field] ?? null)
                                        <x-filament::badge color="gray">{{ \App\Filament\IntelligencePresentation::label($contact[$field]) }}</x-filament::badge>
                                    @endif
                                @endforeach
                            </div>
                            <div class="li-contact-details">
                                @if ($emailUrl)
                                    <x-filament::link :href="$emailUrl" icon="heroicon-o-envelope">{{ $email }}</x-filament::link>
                                @else
                                    <p>Email not supplied.</p>
                                @endif
                                @if ($phoneUrl = \App\Filament\IntelligencePresentation::phoneUrl($contact['phone_number'] ?? null))
                                    <x-filament::link :href="$phoneUrl" icon="heroicon-o-phone">{{ $contact['phone_number'] }}</x-filament::link>
                                @endif
                                @if ($linkedin)
                                    <x-filament::link :href="$linkedin" target="_blank" rel="noopener noreferrer">LinkedIn profile ↗</x-filament::link>
                                @endif
                            </div>
                            <div class="li-contact-quality">
                                <x-filament::badge :color="match ($verification) { 'valid' => 'success', 'accept_all' => 'warning', 'invalid' => 'danger', default => 'gray' }">{{ \App\Filament\IntelligencePresentation::emailVerification($verification) }}</x-filament::badge>
                                @if (($contact['confidence'] ?? null) !== null)
                                    <span>{{ $contact['confidence'] }}% provider confidence</span>
                                @endif
                            </div>
                            @if ($emailUrl)
                                <div class="li-contact-actions">
                                    <x-filament::button tag="a" :href="$emailUrl" icon="heroicon-o-envelope" size="sm" :aria-label="'Email '.($name ?: $email)">Email</x-filament::button>
                                    <x-filament::button tag="a" :href="$meetingUrl" target="_blank" rel="noopener noreferrer" icon="heroicon-o-calendar-days" color="gray" size="sm" :aria-label="'Book meeting with '.($name ?: $email)">Book meeting</x-filament::button>
                                    <x-filament::link :href="\App\Filament\IntelligencePresentation::emailUrl($email, $prospect->company_name, $name, meeting: true)">Request a time by email</x-filament::link>
                                </div>
                            @endif
                        </article>
                    @empty
                        <x-filament::empty-state :contained="false" :compact="true" icon="heroicon-o-user-group" heading="No people found yet" :description="$companyEmails || $companyPhones ? 'Use the company contacts above, or run the agent again to discover people to connect with.' : 'Run the agent again to discover people to connect with.'" />
                    @endforelse
                </div>
                @if ($people)
                    <p class="text-sm text-gray-500 dark:text-gray-400">Email opens a draft in your mail app. Book meeting opens Google Calendar; choose a time and confirm the invitation there. Accept-all domains do not confirm that a specific mailbox exists.</p>
                @endif
            </div>
        </x-filament::section>
    </div>
    <x-filament::section heading="Buying Signals" description="Evidence supporting this opportunity.">
        <div class="space-y-6">
            @forelse ($prospect->buyingSignals as $signal)
                <article class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-semibold">{{ \App\Filament\IntelligencePresentation::label($signal->type) }}</h3>
                        <x-filament::badge :color="\App\Filament\IntelligencePresentation::color($signal->strength)">{{ \App\Filament\IntelligencePresentation::label($signal->strength) }} strength</x-filament::badge>
                    </div>
                    <p class="break-words whitespace-pre-line leading-relaxed">{{ $signal->evidence }}</p>
                    @if ($source = \App\Filament\IntelligencePresentation::safeUrl($signal->source_url))
                        <x-filament::link :href="$source" target="_blank" rel="noopener noreferrer">Open evidence source</x-filament::link>
                    @else
                        <p class="text-sm text-gray-600 dark:text-gray-400">No source URL supplied.</p>
                    @endif
                </article>
            @empty
                <x-filament::empty-state :contained="false" :compact="true" heading="No buying signals yet" description="Run the agent again when new evidence is available." />
            @endforelse
        </div>
    </x-filament::section>
    <x-filament::section heading="Evidence Sources">
        <ul class="space-y-2">
            @forelse ($sources as $source)
                <li class="break-all"><x-filament::link :href="$source" target="_blank" rel="noopener noreferrer">{{ $source }}</x-filament::link></li>
            @empty
                <li>No evidence source links supplied.</li>
            @endforelse
        </ul>
    </x-filament::section>
    <x-filament::section heading="Agent Run Context">
        <p><x-filament::link :href="\App\Filament\Pages\ViewAgentRun::getUrl(['record' => $prospect->agent_run_id], panel: 'app')">Run #{{ $prospect->agent_run_id }}</x-filament::link> · {{ \App\Filament\IntelligencePresentation::label($prospect->agentRun->status) }} · {{ $prospect->agentRun->created_at->format('M j, Y H:i') }}</p>
    </x-filament::section>
</x-filament-panels::page>
