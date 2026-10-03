<?php

namespace Tests\Feature;

use App\Application\LeadIntelligence\RunLeadIntelligenceAction;
use App\Filament\IntelligencePresentation;
use App\Filament\Pages\Overview;
use App\Filament\Pages\Prospects;
use App\Filament\Pages\ViewProspect;
use App\Models\IcpConfiguration;
use App\Models\Prospect;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProspectContactsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Http::preventStrayRequests();
        config(['services.lead_intelligence.base_url' => 'http://lead-service.test']);
    }

    public function test_new_response_persists_people_and_displays_contact_actions(): void
    {
        IcpConfiguration::factory()->for($this->user->organization)->create();
        Http::fake(fn (Request $request) => Http::response([
            'run_id' => $request['run_id'], 'prospects' => [$this->payload()],
        ]));
        $run = app(RunLeadIntelligenceAction::class)->execute($this->user);
        $prospect = $run->prospects()->firstOrFail();
        $this->assertCount(3, $prospect->contact_info['people']);
        $this->assertSame('accept_all', $prospect->contact_info['people'][0]['verification_status']);
        $this->assertNull($prospect->contact_info['people'][2]['verification_status']);
        $this->assertSame(75, $prospect->contact_info['people'][0]['confidence']);
        $this->assertSame($this->user->organization_id, $prospect->organization_id);
        $this->get(ViewProspect::getUrl(['record' => $prospect->id], panel: 'app'))
            ->assertOk()->assertSee('Decision-makers & contacts')->assertSee('Khaled Wahab')
            ->assertSee('General Manager')->assertSee('Chief Officer')->assertSee('Engineer')
            ->assertSee('Executive')->assertSee('Senior')->assertSee('75% provider confidence')
            ->assertSee('Accept-all domain')->assertSee('Verification not supplied')
            ->assertSee('Book meeting')->assertSee('Request a time by email')
            ->assertSee(IntelligencePresentation::emailUrl('khaled@example.test', $prospect->company_name, 'Khaled Wahab'))
            ->assertSee(IntelligencePresentation::meetingUrl('khaled@example.test', $prospect->company_name));
        $this->get(Overview::getUrl(panel: 'app'))->assertSee('3 contacts')->assertSee('#contacts');
        Livewire::test(Prospects::class)->assertCanSeeTableRecords([$prospect])->assertSee('Contacts');
        Http::assertSentCount(1);
    }

    public function test_contact_details_are_isolated_between_organizations(): void
    {
        $own = Prospect::factory()->forOrganization($this->user->organization)->create(['contact_info' => $this->payload()['contact_info']]);
        $other = Prospect::factory()->create(['contact_info' => ['people' => [['email' => 'private@example.test']]]]);
        $this->get(ViewProspect::getUrl(['record' => $own->id], panel: 'app'))->assertOk()->assertSee('Khaled Wahab');
        $this->get(ViewProspect::getUrl(['record' => $other->id], panel: 'app'))->assertNotFound()->assertDontSee('private@example.test');
        Http::assertNothingSent();
    }

    public function test_older_prospects_have_a_clean_contact_empty_state(): void
    {
        $prospect = Prospect::factory()->forOrganization($this->user->organization)->create();
        $this->get(ViewProspect::getUrl(['record' => $prospect->id], panel: 'app'))
            ->assertOk()->assertSee('No people found yet')->assertDontSee('Book meeting');
        Http::assertNothingSent();
    }

    public function test_company_contacts_render_without_people(): void
    {
        $prospect = Prospect::factory()->forOrganization($this->user->organization)->create(['contact_info' => [
            'company_emails' => [['email' => 'hello@example.test']],
            'company_phone_numbers' => ['+20 123 456 789'], 'people' => [],
        ]]);
        $this->get(ViewProspect::getUrl(['record' => $prospect->id], panel: 'app'))
            ->assertOk()->assertSee('hello@example.test')->assertSee('tel:+20123456789', false);
    }

    /** @return array<string, array{string, mixed}> */
    public static function invalidContacts(): array
    {
        return [
            'email injection' => ['email', "person@example.test\r\nBcc: other@example.test"],
            'unsafe profile link' => ['linkedin_url', 'javascript:alert(1)'],
            'invalid confidence' => ['confidence', 101],
        ];
    }

    #[DataProvider('invalidContacts')]
    public function test_invalid_contact_data_does_not_persist_partial_results(string $field, mixed $value): void
    {
        IcpConfiguration::factory()->for($this->user->organization)->create();
        $payload = $this->payload();
        $payload['contact_info']['people'][0][$field] = $value;
        Http::fake(fn (Request $request) => Http::response(['run_id' => $request['run_id'], 'prospects' => [$payload]]));
        Livewire::test(Overview::class)->callAction('runLeadAgent')->assertNotified('The service returned an invalid response.');
        $this->assertDatabaseCount('prospects', 0);
    }

    public function test_contact_links_encode_content_and_never_invent_verified_status(): void
    {
        $url = IntelligencePresentation::emailUrl('person@example.test', "Company & Partners\r\nBcc: hidden@example.test", 'Alex', meeting: true);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame(['subject', 'body'], array_keys($query));
        $this->assertStringNotContainsString("\r\n", $query['subject']);
        $this->assertStringContainsString('short introductory meeting', $query['body']);
        $calendar = IntelligencePresentation::meetingUrl('person@example.test', 'Company & Partners');
        parse_str(parse_url($calendar, PHP_URL_QUERY), $event);
        $this->assertSame('calendar.google.com', parse_url($calendar, PHP_URL_HOST));
        $this->assertSame('person@example.test', $event['add']);
        $this->assertSame('Introduction — Company & Partners', $event['text']);
        $this->assertSame('TEMPLATE', $event['action']);
        $this->assertNull(IntelligencePresentation::emailUrl('javascript:alert(1)', 'Company'));
        $this->assertNull(IntelligencePresentation::meetingUrl("person@example.test\n", 'Company'));
        $this->assertNull(IntelligencePresentation::phoneUrl('no phone'));
        $this->assertSame('Accept-all domain', IntelligencePresentation::emailVerification('accept_all'));
        $this->assertSame('Verification not supplied', IntelligencePresentation::emailVerification(null));
        Http::assertNothingSent();
    }

    public function test_untrusted_stored_contact_markup_is_escaped_and_links_are_safe(): void
    {
        $contacts = $this->payload()['contact_info'];
        $contacts['people'][0]['first_name'] = '<script>alert(1)</script>';
        $contacts['people'][0]['linkedin_url'] = 'javascript:alert(1)';
        $prospect = Prospect::factory()->forOrganization($this->user->organization)->create(['contact_info' => $contacts]);
        $this->get(ViewProspect::getUrl(['record' => $prospect->id], panel: 'app'))
            ->assertOk()->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('href="javascript:', false);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'company_name' => 'شركة الملاحة الوطنية ⚓', 'website' => 'https://shipping.example.test',
            'icp_fit' => 'high', 'product_relevance' => 'high', 'why_now' => 'Hiring staff for two new vessels.',
            'buying_signals' => [['type' => 'hiring', 'evidence' => 'New career opportunities.', 'source_url' => null, 'strength' => 'high']],
            'contact_info' => ['company_emails' => [], 'company_phone_numbers' => [], 'people' => [
                ['first_name' => 'Khaled', 'last_name' => 'Wahab', 'email' => 'khaled@example.test', 'position' => 'General Manager', 'seniority' => 'senior', 'department' => 'executive', 'linkedin_url' => 'https://www.linkedin.com/in/khaled-example', 'phone_number' => null, 'confidence' => 75, 'verification_status' => 'accept_all'],
                ['first_name' => 'Walied', 'last_name' => 'Hamam', 'email' => 'walied@example.test', 'position' => 'Chief Officer', 'seniority' => 'executive', 'department' => 'executive', 'linkedin_url' => null, 'phone_number' => null, 'confidence' => 75, 'verification_status' => 'accept_all'],
                ['first_name' => 'Mohamed', 'last_name' => 'Noamani', 'email' => 'mohamed@example.test', 'position' => 'Engineer', 'seniority' => null, 'department' => 'it', 'linkedin_url' => null, 'phone_number' => null, 'confidence' => 85, 'verification_status' => null],
            ]],
        ];
    }
}
