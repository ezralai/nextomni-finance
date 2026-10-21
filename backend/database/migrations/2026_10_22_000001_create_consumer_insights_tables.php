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
        Schema::create('user_insights_consents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->boolean('is_opted_in')->default(true);
            $table->string('state_code', 10)->default('MY-10'); // Default: Selangor
            $table->string('income_tier', 10)->default('M40');   // B40, M40, T20
            $table->timestamp('consented_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_opted_in']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_insights_consents');
    }
};
