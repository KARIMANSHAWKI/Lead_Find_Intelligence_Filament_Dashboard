<?php

namespace Tests\Feature;

use App\Models\AgentRun;
use App\Models\BuyingSignal;
use App\Models\Organization;
use App\Models\Prospect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CorePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_relationships_and_organization_isolation(): void
    {
        Http::preventStrayRequests();
        $first = Organization::factory()->create();
        $second = Organization::factory()->create();
        $run = AgentRun::factory()->for($first)->create();
        $otherRun = AgentRun::factory()->for($second)->create();
        $prospect = Prospect::factory()->for($run)->create();
        $otherProspect = Prospect::factory()->for($otherRun)->create();
        $signal = BuyingSignal::factory()->for($prospect)->create();

        $this->assertTrue($run->organization->is($first));
        $this->assertTrue($prospect->organization->is($first));
        $this->assertTrue($prospect->agentRun->is($run));
        $this->assertTrue($signal->prospect->is($prospect));
        $this->assertSame([$run->id], $first->agentRuns()->pluck('id')->all());
        $this->assertSame([$prospect->id], $first->prospects()->pluck('id')->all());
        $this->assertSame([$prospect->id], $run->prospects()->pluck('id')->all());
        $this->assertSame([$signal->id], $prospect->buyingSignals()->pluck('id')->all());
        $this->assertSame([$otherProspect->id], $second->prospects()->pluck('id')->all());
        Http::assertNothingSent();
    }

    public function test_authenticated_relationship_creation_ignores_mass_assigned_ownership(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user);
        $run = $user->organization->agentRuns()->create(['status' => 'pending', 'organization_id' => $other->organization_id]);
        $prospect = $user->organization->prospects()->create([
            'agent_run_id' => $run->id, 'organization_id' => $other->organization_id,
            'company_name' => 'Owned Company', 'icp_fit' => 'high', 'product_relevance' => 'high', 'why_now' => 'Hiring.',
        ]);
        $this->assertSame($user->organization_id, $run->organization_id);
        $this->assertSame($user->organization_id, $prospect->organization_id);
        $this->assertNull($prospect->score);
    }

    public function test_cross_organization_run_assignment_is_rejected(): void
    {
        $user = User::factory()->create();
        $run = AgentRun::factory()->create();
        $this->expectException(ValidationException::class);
        Prospect::factory()->for($run)->create(['organization_id' => $user->organization_id]);
    }

    public function test_deleting_a_run_cascades_to_prospects_and_signals_only_for_that_run(): void
    {
        $signal = BuyingSignal::factory()->create();
        $other = BuyingSignal::factory()->create();
        $signal->prospect->agentRun->delete();
        $this->assertModelMissing($signal);
        $this->assertModelExists($other);
        $this->assertDatabaseCount('prospects', 1);
    }

    public function test_deleting_a_prospect_cascades_to_signals_but_keeps_its_run(): void
    {
        $signal = BuyingSignal::factory()->create();
        $run = $signal->prospect->agentRun;
        $signal->prospect->delete();
        $this->assertModelMissing($signal);
        $this->assertModelExists($run);
    }

    public function test_deleting_an_organization_cascades_to_its_graph_only(): void
    {
        $signal = BuyingSignal::factory()->create();
        $other = BuyingSignal::factory()->create();
        $signal->prospect->organization->delete();
        $this->assertModelMissing($signal);
        $this->assertModelExists($other);
        $this->assertDatabaseCount('agent_runs', 1);
        $this->assertDatabaseCount('prospects', 1);
    }

    /** @return array<string, array{string}> */
    public static function classifications(): array
    {
        return ['high' => ['high'], 'medium' => ['medium'], 'low' => ['low']];
    }

    #[DataProvider('classifications')]
    public function test_classifications_persist_as_strings(string $value): void
    {
        $prospect = Prospect::factory()->create(['icp_fit' => $value, 'product_relevance' => $value, 'evidence_quality' => $value]);
        $signal = BuyingSignal::factory()->for($prospect)->create(['strength' => $value]);
        $this->assertSame($value, $prospect->fresh()->icp_fit);
        $this->assertSame($value, $prospect->fresh()->product_relevance);
        $this->assertSame($value, $prospect->fresh()->evidence_quality);
        $this->assertSame($value, $signal->fresh()->strength);
    }

    public function test_defaults_nullable_fields_and_date_casts(): void
    {
        $run = AgentRun::factory()->create()->fresh();
        $this->assertSame('pending', $run->status);
        $this->assertSame(0, $run->candidates_found);
        $this->assertSame(0, $run->prospects_qualified);
        $this->assertNull($run->started_at);
        $this->assertNull($run->completed_at);
        $this->assertNull($run->failed_at);
        $completed = AgentRun::factory()->completed()->create()->fresh();
        $failed = AgentRun::factory()->failed()->create()->fresh();
        $this->assertInstanceOf(Carbon::class, $completed->started_at);
        $this->assertInstanceOf(Carbon::class, $completed->completed_at);
        $this->assertInstanceOf(Carbon::class, $failed->failed_at);
        $this->assertSame('2026-01-15 10:05:00', $completed->completed_at->format('Y-m-d H:i:s'));
        $prospect = Prospect::factory()->for($run)->create(['evidence_quality' => null])->fresh();
        $this->assertNull($prospect->score);
        $this->assertNull($prospect->evidence_quality);
        $this->assertInstanceOf(Carbon::class, $prospect->created_at);
    }

    public function test_factory_for_organization_keeps_ownership_consistent(): void
    {
        $user = User::factory()->create();
        $prospect = Prospect::factory()->forOrganization($user->organization)->create();
        $this->assertSame($user->organization_id, $prospect->organization_id);
        $this->assertSame($user->organization_id, $prospect->agentRun->organization_id);
    }

    public function test_business_tables_only_have_organization_ownership(): void
    {
        foreach (['icp_configurations', 'agent_runs', 'prospects'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'organization_id'));
            $this->assertFalse(Schema::hasColumn($table, 'user_id'));
        }
        $this->assertFalse(Schema::hasColumn('buying_signals', 'organization_id'));
    }
}
