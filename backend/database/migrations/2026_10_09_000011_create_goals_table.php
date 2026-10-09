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
        Schema::create('goals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->decimal('target_amount', 15, 4);
            $table->decimal('current_amount', 15, 4)->default(0);
            $table->string('currency', 3)->default('MYR');
            $table->date('target_date')->nullable();
            $table->string('color', 20)->default('#10b981');
            $table->string('icon', 50)->default('target');
            $table->string('status', 20)->default('in_progress'); // in_progress, achieved, paused
            $table->timestamps();

            $table->index(['user_id', 'status', 'target_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
