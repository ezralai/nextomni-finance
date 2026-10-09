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
        Schema::create('budgets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained('transaction_categories')->cascadeOnDelete();
            $table->decimal('amount', 15, 4);
            $table->string('currency', 3)->default('MYR');
            $table->string('period', 20)->default('monthly'); // monthly, yearly, custom
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('rollover_enabled')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Compound indexes for user budget performance lookups
            $table->index(['user_id', 'period', 'start_date', 'end_date']);
            $table->index(['user_id', 'category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
