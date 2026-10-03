<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public $withinTransaction = false;

    private const BUSINESS_TABLES = ['icp_configurations', 'agent_runs', 'prospects'];

    public function up(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('organization_id')->nullable()->index()->constrained()->cascadeOnDelete();
            });

            DB::transaction(function (): void {
                DB::table('users')->orderBy('id')->chunkById(100, function ($users): void {
                    foreach ($users as $user) {
                        $organizationId = DB::table('organizations')->insertGetId([
                            'name' => Str::limit($user->name."'s Organization", 255, ''),
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                        DB::table('users')->where('id', $user->id)->update(['organization_id' => $organizationId]);
                    }
                });
            });

            Schema::table('prospects', function (Blueprint $table): void {
                $table->dropForeign(['agent_run_id', 'user_id']);
                $table->dropForeign(['user_id']);
                $table->dropIndex(['user_id']);
            });
            Schema::table('agent_runs', function (Blueprint $table): void {
                $table->dropForeign(['user_id']);
                $table->dropUnique(['id', 'user_id']);
                $table->dropIndex(['user_id']);
            });
            Schema::table('icp_configurations', function (Blueprint $table): void {
                $table->dropForeign(['user_id']);
                $table->dropUnique(['user_id']);
            });

            foreach (self::BUSINESS_TABLES as $name) {
                Schema::table($name, fn (Blueprint $table) => $table->renameColumn('user_id', 'organization_id'));
                DB::table($name)->orderBy('id')->chunkById(100, function ($records) use ($name): void {
                    foreach ($records as $record) {
                        $organizationId = DB::table('users')->where('id', $record->organization_id)->value('organization_id');
                        DB::table($name)->where('id', $record->id)->update(['organization_id' => $organizationId]);
                    }
                });
                Schema::table($name, function (Blueprint $table) use ($name): void {
                    $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
                    if ($name === 'icp_configurations') {
                        $table->unique('organization_id');
                    } else {
                        $table->index('organization_id');
                    }
                });
            }

            Schema::table('prospects', function (Blueprint $table): void {
                $table->foreign('agent_run_id')->references('id')->on('agent_runs')->cascadeOnDelete();
            });
            Schema::table('users', function (Blueprint $table): void {
                $table->unsignedBigInteger('organization_id')->nullable(false)->change();
            });
        });
    }

    public function down(): void
    {
        foreach (self::BUSINESS_TABLES as $name) {
            foreach (DB::table($name)->distinct()->pluck('organization_id') as $organizationId) {
                if (DB::table('users')->where('organization_id', $organizationId)->count() !== 1) {
                    throw new RuntimeException('Cannot restore user ownership for shared or ownerless organization data. Back up and resolve ownership before rolling back.');
                }
            }
        }

        Schema::withoutForeignKeyConstraints(function (): void {
            Schema::table('prospects', fn (Blueprint $table) => $table->dropForeign(['agent_run_id']));
            foreach (self::BUSINESS_TABLES as $name) {
                Schema::table($name, function (Blueprint $table) use ($name): void {
                    $table->dropForeign(['organization_id']);
                    if ($name === 'icp_configurations') {
                        $table->dropUnique(['organization_id']);
                    } else {
                        $table->dropIndex(['organization_id']);
                    }
                });
                Schema::table($name, fn (Blueprint $table) => $table->renameColumn('organization_id', 'user_id'));
                DB::table($name)->orderBy('id')->chunkById(100, function ($records) use ($name): void {
                    foreach ($records as $record) {
                        $userId = DB::table('users')->where('organization_id', $record->user_id)->value('id');
                        DB::table($name)->where('id', $record->id)->update(['user_id' => $userId]);
                    }
                });
                Schema::table($name, function (Blueprint $table) use ($name): void {
                    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                    if ($name === 'icp_configurations') {
                        $table->unique('user_id');
                    } else {
                        $table->index('user_id');
                    }
                });
            }
            Schema::table('agent_runs', fn (Blueprint $table) => $table->unique(['id', 'user_id']));
            Schema::table('prospects', function (Blueprint $table): void {
                $table->foreign(['agent_run_id', 'user_id'])->references(['id', 'user_id'])->on('agent_runs')->cascadeOnDelete();
            });
            Schema::table('users', function (Blueprint $table): void {
                $table->dropForeign(['organization_id']);
                $table->dropIndex(['organization_id']);
                $table->dropColumn('organization_id');
            });
        });
    }
};
