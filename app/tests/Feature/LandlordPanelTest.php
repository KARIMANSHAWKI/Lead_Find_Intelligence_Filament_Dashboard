<?php

namespace Tests\Feature;

use App\Filament\Landlord\Pages\Organizations;
use App\Filament\Landlord\Pages\Users;
use App\Filament\Pages\Overview;
use App\Filament\Pages\ViewProspect;
use App\Models\Organization;
use App\Models\Prospect;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LandlordPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Filament::setCurrentPanel(Filament::getPanel('landlord'));
        Http::preventStrayRequests();
    }

    public function test_platform_admin_can_list_all_users_and_organizations(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->actingAs($admin);
        $this->get(Users::getUrl(panel: 'landlord'))->assertOk()->assertSee($first->email)->assertSee($second->email);
        Livewire::test(Users::class)->assertCanSeeTableRecords([$admin, $first, $second]);
        Livewire::test(Organizations::class)->assertCanSeeTableRecords([$admin->organization, $first->organization, $second->organization]);
        $this->get(Organizations::getUrl(panel: 'landlord'))->assertOk()->assertSee($first->organization->name)->assertSee($second->organization->name);
        $items = collect(Filament::getNavigation())->flatMap(fn ($group) => $group->getItems());
        $this->assertSame(['Users', 'Organizations'], $items->map(fn ($item) => $item->getLabel())->all());
        $this->assertSame(url('/landlord'), Users::getUrl(panel: 'landlord'));
        Http::assertNothingSent();
    }

    public function test_regular_users_cannot_open_landlord_lists_or_livewire_components(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->get(Users::getUrl(panel: 'landlord'))->assertForbidden();
        $this->get(Organizations::getUrl(panel: 'landlord'))->assertForbidden();
        Livewire::test(Users::class)->assertForbidden();
        Livewire::test(Organizations::class)->assertForbidden();
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('landlord')));
    }

    public function test_guests_redirect_to_landlord_login(): void
    {
        $this->get(Users::getUrl(panel: 'landlord'))->assertRedirect(Filament::getLoginUrl());
        $this->get(Organizations::getUrl(panel: 'landlord'))->assertRedirect(Filament::getLoginUrl());
        $this->get(Filament::getLoginUrl())->assertOk()->assertSee('Platform Admin');
    }

    public function test_platform_admin_login_uses_existing_guard_and_lands_on_users(): void
    {
        $admin = User::factory()->platformAdmin()->create(['password' => 'test-admin-password']);
        Livewire::test(Login::class)->set('data.email', $admin->email)->set('data.password', 'test-admin-password')
            ->call('authenticate')->assertHasNoErrors()->assertRedirect(Users::getUrl(panel: 'landlord'));
        $this->assertAuthenticatedAs($admin, 'web');
    }

    public function test_regular_accounts_cannot_sign_in_to_landlord_panel(): void
    {
        $user = User::factory()->create(['password' => 'test-ordinary-password']);
        Livewire::test(Login::class)->set('data.email', $user->email)->set('data.password', 'test-ordinary-password')
            ->call('authenticate')->assertHasErrors(['data.email']);
    }

    public function test_admin_flag_is_not_mass_assignable(): void
    {
        $user = User::factory()->create();
        $user->update(['is_platform_admin' => true]);
        $this->assertFalse($user->fresh()->is_platform_admin);
        $this->actingAs($user)->get(Users::getUrl(panel: 'landlord'))->assertForbidden();
    }

    public function test_revoked_platform_access_is_checked_again_on_livewire_updates(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $this->actingAs($admin);
        $page = Livewire::test(Users::class)->assertOk();
        $admin->forceFill(['is_platform_admin' => false])->save();
        $page->call('$refresh')->assertForbidden();
    }

    public function test_landlord_search_and_organization_filter_work_across_tenants(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $first = User::factory()->create(['name' => 'Alpha Admin Directory']);
        $second = User::factory()->create(['name' => 'Beta Admin Directory']);
        $this->actingAs($admin);
        $page = Livewire::test(Users::class)->searchTable('Alpha Admin Directory')
            ->assertCanSeeTableRecords([$first])->assertCanNotSeeTableRecords([$second]);
        $page->searchTable('')->filterTable('organization_id', $second->organization_id)
            ->assertCanSeeTableRecords([$second])->assertCanNotSeeTableRecords([$first]);
        Livewire::test(Organizations::class)->searchTable($second->organization->name)
            ->assertCanSeeTableRecords([$second->organization]);
    }

    public function test_platform_admin_does_not_bypass_tenant_business_scope(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $own = Prospect::factory()->forOrganization($admin->organization)->create(['score' => 90, 'company_name' => 'Own Organization Company']);
        $other = Prospect::factory()->create(['score' => 100, 'company_name' => 'Other Organization Private Company']);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->get(Overview::getUrl(panel: 'app'))->assertOk()->assertSee($own->company_name)->assertDontSee($other->company_name);
        $this->get(ViewProspect::getUrl(['record' => $other->id], panel: 'app'))->assertNotFound();
        Livewire::test(Users::class)->assertForbidden();
    }

    public function test_console_provisioning_requires_explicit_platform_admin_flag(): void
    {
        $organization = Organization::factory()->create();
        $this->artisan('make:filament-user', [
            '--panel' => 'landlord', '--organization' => $organization->id, '--platform-admin' => true,
            '--name' => 'Platform Administrator', '--email' => 'test-platform-admin@example.test',
            '--password' => 'test-command-password', '--no-interaction' => true,
        ])->assertSuccessful();
        $admin = User::query()->where('email', 'test-platform-admin@example.test')->firstOrFail();
        $this->assertTrue($admin->is_platform_admin);
        $this->assertSame($organization->id, $admin->organization_id);
        $this->artisan('make:filament-user', [
            '--panel' => 'app', '--name' => 'Normal Account', '--email' => 'test-normal-account@example.test',
            '--password' => 'test-command-password', '--no-interaction' => true,
        ])->assertSuccessful();
        $this->assertFalse(User::query()->where('email', 'test-normal-account@example.test')->firstOrFail()->is_platform_admin);
    }

    public function test_admin_can_create_an_organization_and_add_a_user_who_can_log_in(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $this->actingAs($admin);
        Livewire::test(Organizations::class)->callAction('createOrganization', data: ['name' => 'Acme Logistics'])
            ->assertHasNoActionErrors()->assertNotified('Organization created');
        $organization = Organization::where('name', 'Acme Logistics')->firstOrFail();
        Livewire::test(Users::class)->callAction('createUser', data: $this->newUserData($organization))
            ->assertHasNoActionErrors()->assertNotified('User created');
        $user = User::where('email', 'new.user@example.test')->firstOrFail();
        $this->assertSame($organization->id, $user->organization_id);
        $this->assertFalse($user->is_platform_admin);
        $this->assertTrue(Hash::check('new-account-password', $user->password));
        $this->assertNotSame('new-account-password', $user->password);
        $this->assertSame(1, $organization->users()->count());
        Livewire::test(Users::class)->assertCanSeeTableRecords([$user]);
        Livewire::test(Organizations::class)->assertSee('Acme Logistics');
        Filament::auth()->logout();
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Livewire::test(Login::class)->set('data.email', $user->email)->set('data.password', 'new-account-password')
            ->call('authenticate')->assertHasNoErrors()->assertRedirect(Overview::getUrl(panel: 'app'));
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('landlord')));
        Http::assertNothingSent();
    }

    public function test_new_accounts_cannot_inject_platform_privileges_or_verified_status(): void
    {
        $this->actingAs(User::factory()->platformAdmin()->create());
        $organization = Organization::factory()->create();
        Livewire::test(Users::class)->callAction('createUser', data: array_merge($this->newUserData($organization), [
            'is_platform_admin' => true, 'email_verified_at' => now()->toDateTimeString(),
        ]))->assertHasNoActionErrors();
        $user = User::where('email', 'new.user@example.test')->firstOrFail();
        $this->assertFalse($user->is_platform_admin);
        $this->assertNull($user->email_verified_at);
        $this->assertSame($organization->id, $user->organization_id);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidNewUsers(): array
    {
        return [
            'missing name' => [['name' => ''], 'name'],
            'blank name' => [['name' => '   '], 'name'],
            'invalid email' => [['email' => 'not-an-email'], 'email'],
            'missing organization' => [['organization_id' => null], 'organization_id'],
            'unknown organization' => [['organization_id' => 999999], 'organization_id'],
            'weak password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
            'different confirmation' => [['password_confirmation' => 'different-password'], 'password'],
        ];
    }

    #[DataProvider('invalidNewUsers')]
    public function test_user_creation_validates_input_without_creating_records(array $changes, string $field): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $this->actingAs($admin);
        Livewire::test(Users::class)->callAction('createUser', data: array_replace($this->newUserData($admin->organization), $changes))
            ->assertHasActionErrors([$field]);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_user_email_is_normalized_and_duplicate_email_is_rejected(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $this->actingAs($admin);
        $data = $this->newUserData($admin->organization);
        $data['email'] = 'NEW.USER@EXAMPLE.TEST';
        Livewire::test(Users::class)->callAction('createUser', data: $data)->assertHasNoActionErrors();
        $this->assertDatabaseHas('users', ['email' => 'new.user@example.test']);
        Livewire::test(Users::class)->callAction('createUser', data: $data)->assertHasActionErrors(['email']);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_blank_organization_names_are_rejected(): void
    {
        $this->actingAs(User::factory()->platformAdmin()->create());
        Livewire::test(Organizations::class)->callAction('createOrganization', data: ['name' => '   '])
            ->assertHasActionErrors(['name']);
        $this->assertDatabaseCount('organizations', 1);
    }

    public function test_revoked_admin_cannot_submit_a_previously_opened_creation_form(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $this->actingAs($admin);
        $users = Livewire::test(Users::class)->mountAction('createUser')->setActionData($this->newUserData($admin->organization));
        $organizations = Livewire::test(Organizations::class)->mountAction('createOrganization')->setActionData(['name' => 'Forbidden organization']);
        $admin->forceFill(['is_platform_admin' => false])->save();
        $users->callMountedAction()->assertForbidden();
        $organizations->callMountedAction()->assertForbidden();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('organizations', 1);
    }

    /** @return array<string, mixed> */
    private function newUserData(Organization $organization): array
    {
        return [
            'name' => 'New Organization User', 'email' => 'new.user@example.test',
            'organization_id' => $organization->id,
            'password' => 'new-account-password', 'password_confirmation' => 'new-account-password',
        ];
    }
}
