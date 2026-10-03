<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icp_configurations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('product');
            $table->json('target_industries');
            $table->string('location');
            $table->unsignedInteger('company_size_min')->nullable();
            $table->unsignedInteger('company_size_max')->nullable();
            $table->text('ideal_customer_description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icp_configurations');
    }
};
