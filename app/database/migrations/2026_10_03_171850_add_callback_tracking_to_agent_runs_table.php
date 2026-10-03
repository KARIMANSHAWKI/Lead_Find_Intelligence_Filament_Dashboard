<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agent_runs', function (Blueprint $table): void {
            $table->foreignId('initiated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('callback_token_hash', 64)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_runs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('initiated_by_user_id');
            $table->dropColumn('callback_token_hash');
        });
    }
};
