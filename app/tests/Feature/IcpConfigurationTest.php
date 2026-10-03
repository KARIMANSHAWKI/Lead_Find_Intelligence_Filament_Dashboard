<?php

namespace Tests\Feature;

use App\Filament\Pages\IcpConfiguration as IcpConfigurationPage;
use App\Models\IcpConfiguration;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IcpConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        Http::preventStrayRequests();
    }

    public function test_authenticated_users_can_open_the_form(): void
    {
        $this->get(IcpConfigurationPage::getUrl(panel: 'app'))
            ->assertOk()
            ->assertSeeText('Target Customer')
            ->assertSeeText('Company Profile')
            ->assertSeeText('Additional Context')
            ->assertSeeText('Save ICP');

        Livewire::test(IcpConfigurationPage::class)
            ->assertSchemaStateSet(['product' => null, 'target_industries' => [], 'location' => null], 'form');
    }

    public function test_guests_are_redirected_to_the_panel_login(): void
    {
        Filament::auth()->logout();

        $this->get(IcpConfigurationPage::getUrl(panel: 'app'))
            ->assertRedirect(Filament::getLoginUrl());
    }

    public function test_user_can_create_an_icp_and_receives_success_feedback_without_http_requests(): void
    {
        Livewire::test(IcpConfigurationPage::class)
            ->fillForm($this->validData(), 'form')
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('ICP configuration saved successfully.');

        $this->assertDatabaseCount('icp_configurations', 1);
        $configuration = $this->user->fresh()->organization->icpConfiguration;
        $this->assertNotNull($configuration);
        $this->assertSame($this->user->organization_id, $configuration->organization_id);
        $this->assertTrue($configuration->organization->is($this->user->organization));
        $this->assertSame('Recruitment Management Software', $configuration->product);
        $this->assertSame(['Software', 'Logistics'], $configuration->target_industries);
        $this->assertSame(50, $configuration->company_size_min);
        $this->assertSame(300, $configuration->company_size_max);
        $stored = DB::table('icp_configurations')->value('target_industries');
        $this->assertSame(['Software', 'Logistics'], json_decode($stored, true, flags: JSON_THROW_ON_ERROR));
        Http::assertNothingSent();
    }

    /**
     * @return array<string, array{string, mixed, string}>
     */
    public static function invalidFields(): array
    {
        return [
            'missing product' => ['product', '', 'product'],
            'whitespace product' => ['product', '   ', 'product'],
            'long product' => ['product', str_repeat('a', 256), 'product'],
            'no industries' => ['target_industries', [], 'target_industries'],
            'empty industry' => ['target_industries', [''], 'target_industries.0'],
            'blank industry' => ['target_industries', ['   '], 'target_industries.0'],
            'non-string industry' => ['target_industries', [123], 'target_industries.0'],
            'long industry' => ['target_industries', [str_repeat('a', 101)], 'target_industries.0'],
            'missing location' => ['location', '', 'location'],
            'long location' => ['location', str_repeat('a', 256), 'location'],
            'negative minimum' => ['company_size_min', -1, 'company_size_min'],
            'negative maximum' => ['company_size_max', -1, 'company_size_max'],
            'fractional minimum' => ['company_size_min', 1.5, 'company_size_min'],
            'fractional maximum' => ['company_size_max', 300.5, 'company_size_max'],
            'text minimum' => ['company_size_min', 'large', 'company_size_min'],
            'text maximum' => ['company_size_max', 'large', 'company_size_max'],
            'long description' => ['ideal_customer_description', str_repeat('a', 5001), 'ideal_customer_description'],
        ];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_input_is_rejected_without_persisting(string $field, mixed $value, string $errorField): void
    {
        $data = array_replace($this->validData(), [$field => $value]);

        Livewire::test(IcpConfigurationPage::class)
            ->fillForm($data, 'form')
            ->call('save')
            ->assertHasFormErrors([$errorField]);

        $this->assertDatabaseCount('icp_configurations', 0);
        Http::assertNothingSent();
    }

    public function test_minimum_above_maximum_is_rejected_with_a_clear_error(): void
    {
        Livewire::test(IcpConfigurationPage::class)
            ->fillForm(array_replace($this->validData(), ['company_size_min' => 301]), 'form')
            ->call('save')
            ->assertHasFormErrors(['company_size_max' => 'gte'])
            ->assertSeeText('Maximum employees must be greater than or equal to minimum employees.');

        $this->assertDatabaseCount('icp_configurations', 0);
    }

    /**
     * @return array<string, array{?int, ?int}>
     */
    public static function validRanges(): array
    {
        return [
            'bounded' => [50, 300],
            'equal bounds' => [50, 50],
            'zero bounds' => [0, 0],
            'maximum only' => [null, 300],
            'minimum only' => [50, null],
            'no bounds' => [null, null],
        ];
    }

    #[DataProvider('validRanges')]
    public function test_valid_and_optional_company_size_ranges_are_accepted(?int $minimum, ?int $maximum): void
    {
        Livewire::test(IcpConfigurationPage::class)
            ->fillForm(array_replace($this->validData(), [
                'company_size_min' => $minimum,
                'company_size_max' => $maximum,
                'ideal_customer_description' => null,
            ]), 'form')
            ->call('save')
            ->assertHasNoFormErrors();

        $configuration = $this->user->fresh()->organization->icpConfiguration;
        $this->assertSame($minimum, $configuration->company_size_min);
        $this->assertSame($maximum, $configuration->company_size_max);
        $this->assertNull($configuration->ideal_customer_description);
    }

    public function test_existing_configuration_populates_the_form(): void
    {
        $configuration = IcpConfiguration::factory()->for($this->user->organization)->create();

        Livewire::test(IcpConfigurationPage::class)
            ->assertSchemaStateSet($configuration->only(array_keys($this->validData())), 'form');
    }

    public function test_saving_again_updates_the_same_record(): void
    {
        $page = Livewire::test(IcpConfigurationPage::class)
            ->fillForm($this->validData(), 'form')
            ->call('save')
            ->assertHasNoFormErrors();
        $id = $this->user->fresh()->organization->icpConfiguration->id;

        $page->fillForm(array_replace($this->validData(), [
            'product' => 'Operations Platform',
            'target_industries' => ['Manufacturing'],
            'location' => 'Alexandria, Egypt',
            'company_size_min' => null,
            'company_size_max' => null,
            'ideal_customer_description' => null,
        ]), 'form')
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('ICP configuration saved successfully.');

        $this->assertDatabaseCount('icp_configurations', 1);
        $configuration = IcpConfiguration::findOrFail($id);
        $this->assertSame('Operations Platform', $configuration->product);
        $this->assertSame(['Manufacturing'], $configuration->target_industries);
        $this->assertSame('Alexandria, Egypt', $configuration->location);
        $this->assertNull($configuration->company_size_min);
        $this->assertNull($configuration->company_size_max);
    }

    public function test_another_users_configuration_cannot_be_loaded_or_updated_by_manipulating_input(): void
    {
        $other = IcpConfiguration::factory()->create(['product' => 'Private product']);
        $own = IcpConfiguration::factory()->for($this->user->organization)->create();

        $this->get(IcpConfigurationPage::getUrl(panel: 'app', parameters: [
            'organization_id' => $other->organization_id, 'id' => $other->id,
        ]))->assertOk()->assertDontSee('Private product');

        Livewire::test(IcpConfigurationPage::class)
            ->fillForm(array_replace($this->validData(), ['product' => 'Updated own product']), 'form')
            ->set('data.organization_id', $other->organization_id)
            ->set('data.id', $other->id)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Private product', $other->fresh()->product);
        $this->assertSame('Updated own product', $own->fresh()->product);
        $this->assertSame($this->user->organization_id, $own->fresh()->organization_id);
        $this->assertDatabaseCount('icp_configurations', 2);
    }

    public function test_manipulated_ownership_is_ignored_when_creating_a_configuration(): void
    {
        $other = IcpConfiguration::factory()->create();

        Livewire::test(IcpConfigurationPage::class)
            ->fillForm($this->validData(), 'form')
            ->set('data.organization_id', $other->organization_id)
            ->set('data.id', $other->id)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseCount('icp_configurations', 2);
        $this->assertSame($this->user->organization_id, $this->user->fresh()->organization->icpConfiguration->organization_id);
    }

    public function test_database_enforces_one_configuration_per_organization(): void
    {
        IcpConfiguration::factory()->for($this->user->organization)->create();

        $this->expectException(UniqueConstraintViolationException::class);

        IcpConfiguration::factory()->for($this->user->organization)->create();
    }

    public function test_deleting_an_organization_removes_its_configuration(): void
    {
        IcpConfiguration::factory()->for($this->user->organization)->create();

        $this->user->organization->delete();

        $this->assertDatabaseCount('icp_configurations', 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function validData(): array
    {
        return [
            'product' => 'Recruitment Management Software',
            'target_industries' => ['Software', 'Logistics'],
            'location' => 'Egypt',
            'company_size_min' => 50,
            'company_size_max' => 300,
            'ideal_customer_description' => 'Companies actively growing their teams.',
        ];
    }
}
