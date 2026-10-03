<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('agent_run_id')->index();
            $table->string('company_name');
            $table->string('website', 2048)->nullable();
            $table->string('source')->nullable();
            $table->string('icp_fit', 16);
            $table->string('product_relevance', 16);
            $table->string('evidence_quality', 16)->nullable();
            $table->text('why_now');
            $table->unsignedTinyInteger('score')->nullable()->index();
            $table->string('status', 32)->nullable()->index();
            $table->timestamps();
            $table->foreign(['agent_run_id', 'user_id'])
                ->references(['id', 'user_id'])->on('agent_runs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospects');
    }
};
