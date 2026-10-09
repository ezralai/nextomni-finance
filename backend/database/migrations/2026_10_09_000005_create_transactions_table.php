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
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('transaction_categories')->nullOnDelete();
            $table->string('type', 20); // income, expense, transfer
            $table->decimal('amount', 15, 4); // positive scalar
            $table->string('currency', 3)->default('MYR');
            $table->timestamp('transacted_at')->useCurrent();
            $table->text('description');
            $table->text('notes')->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->string('deduplication_hash', 64)->nullable();
            $table->string('status', 20)->default('posted'); // posted, pending, reconciled, void
            $table->timestamps();

            // Compound time-series and user-scoped query indexes
            $table->index(['user_id', 'transacted_at']);
            $table->index(['account_id', 'transacted_at']);
            $table->index(['user_id', 'deduplication_hash']);
            $table->index(['category_id', 'transacted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
