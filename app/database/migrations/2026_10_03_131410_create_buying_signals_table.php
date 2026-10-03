<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buying_signals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prospect_id')->index()->constrained()->cascadeOnDelete();
            $table->string('type', 100);
            $table->text('evidence');
            $table->string('source_url', 2048)->nullable();
            $table->string('strength', 16);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buying_signals');
    }
};
