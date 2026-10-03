<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->index()->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('pending')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('candidates_found')->default(0);
            $table->unsignedInteger('prospects_qualified')->default(0);
            $table->timestamps();
            $table->unique(['id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_runs');
    }
};
