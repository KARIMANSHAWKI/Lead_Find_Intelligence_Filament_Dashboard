<?php

namespace Tests\Feature;

use App\Models\IcpConfiguration;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class OrganizationOwnershipMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_forward_migration_preserves_legacy_data_and_can_restore_original_ownership(): void
    {
        $migration = require database_path('migrations/2026_10_03_140405_move_business_ownership_to_organizations.php');
        $migration->down();
        $date = '2026-01-15 10:00:00';
        foreach ([10, 30] as $id) {
            DB::table('users')->insert([
                'id' => $id, 'name' => 'Legacy User '.$id, 'email' => 'legacy'.$id.'@example.test',
                'password' => 'existing-hash', 'created_at' => $date, 'updated_at' => $date,
            ]);
            DB::table('icp_configurations')->insert([
                'id' => $id, 'user_id' => $id, 'product' => 'Legacy Product '.$id,
                'target_industries' => '["Software"]', 'location' => 'Egypt',
                'created_at' => $date, 'updated_at' => $date,
            ]);
            DB::table('agent_runs')->insert([
                'id' => $id, 'user_id' => $id, 'status' => 'completed', 'started_at' => $date,
                'completed_at' => '2026-01-15 10:05:00', 'candidates_found' => 16, 'prospects_qualified' => 1,
                'created_at' => $date, 'updated_at' => $date,
            ]);
            DB::table('prospects')->insert([
                'id' => $id, 'user_id' => $id, 'agent_run_id' => $id, 'company_name' => 'Legacy Company '.$id,
                'icp_fit' => 'high', 'product_relevance' => 'high', 'evidence_quality' => 'medium',
                'why_now' => 'Preserved original evidence.', 'score' => 85,
                'created_at' => $date, 'updated_at' => $date,
            ]);
            DB::table('buying_signals')->insert([
                'id' => $id, 'prospect_id' => $id, 'type' => 'hiring_growth',
                'evidence' => 'Preserved original signal.', 'strength' => 'high',
                'created_at' => $date, 'updated_at' => $date,
            ]);
        }
        $migration->up();
        foreach ([10, 30] as $id) {
            $organizationId = DB::table('users')->where('id', $id)->value('organization_id');
            $this->assertNotSame($id, $organizationId);
            foreach (['icp_configurations', 'agent_runs', 'prospects'] as $table) {
                $this->assertSame($organizationId, DB::table($table)->where('id', $id)->value('organization_id'));
                $this->assertFalse(Schema::hasColumn($table, 'user_id'));
                $this->assertSame($date, DB::table($table)->where('id', $id)->value('created_at'));
            }
            $this->assertSame('Legacy Product '.$id, DB::table('icp_configurations')->where('id', $id)->value('product'));
            $this->assertSame(85, DB::table('prospects')->where('id', $id)->value('score'));
            $this->assertSame('Preserved original signal.', DB::table('buying_signals')->where('id', $id)->value('evidence'));
        }
        $this->assertNotSame(DB::table('users')->where('id', 10)->value('organization_id'), DB::table('users')->where('id', 30)->value('organization_id'));
        foreach (['users', 'organizations', 'icp_configurations', 'agent_runs', 'prospects', 'buying_signals'] as $table) {
            $this->assertDatabaseCount($table, 2);
        }
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $migration->down();
        foreach (['icp_configurations', 'agent_runs', 'prospects'] as $table) {
            $this->assertSame([10, 30], DB::table($table)->orderBy('id')->pluck('user_id')->all());
            $this->assertFalse(Schema::hasColumn($table, 'organization_id'));
        }
        $this->assertFalse(Schema::hasColumn('users', 'organization_id'));
        $this->assertDatabaseCount('buying_signals', 2);
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $migration->up();
        $this->assertDatabaseCount('prospects', 2);
    }

    public function test_rollback_refuses_to_invent_a_user_owner_for_shared_organization_records(): void
    {
        $organization = Organization::factory()->create();
        User::factory()->for($organization)->create();
        $teammate = User::factory()->for($organization)->create();
        $icp = IcpConfiguration::factory()->for($organization)->create();
        $migration = require database_path('migrations/2026_10_03_140405_move_business_ownership_to_organizations.php');
        try {
            $migration->down();
            $this->fail('Rollback must not invent a user owner.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('shared or ownerless', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('icp_configurations', 'organization_id'));
        $this->assertFalse(Schema::hasColumn('icp_configurations', 'user_id'));
        $this->assertModelExists($icp);
        $teammate->delete();
    }

    public function test_final_schema_has_required_organization_keys_and_useful_indexes(): void
    {
        foreach (['users', 'icp_configurations', 'agent_runs', 'prospects'] as $table) {
            $column = collect(Schema::getColumns($table))->firstWhere('name', 'organization_id');
            $this->assertFalse($column['nullable']);
            $this->assertTrue(collect(Schema::getIndexes($table))->contains(fn (array $index): bool => $index['columns'] === ['organization_id']));
            $this->assertTrue(collect(Schema::getForeignKeys($table))->contains(fn (array $key): bool => $key['columns'] === ['organization_id'] && $key['foreign_table'] === 'organizations'));
        }
        $this->assertTrue(collect(Schema::getIndexes('icp_configurations'))->contains(fn (array $index): bool => $index['columns'] === ['organization_id'] && $index['unique']));
        foreach (['agent_run_id', 'status', 'score'] as $column) {
            $this->assertTrue(collect(Schema::getIndexes('prospects'))->contains(fn (array $index): bool => $index['columns'] === [$column]));
        }
        $this->assertTrue(collect(Schema::getIndexes('agent_runs'))->contains(fn (array $index): bool => $index['columns'] === ['status']));
        $this->assertFalse(Schema::hasColumn('buying_signals', 'organization_id'));
    }
}
