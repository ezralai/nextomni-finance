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
        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('transaction_categories')->nullOnDelete();
            $table->string('type', 20); // 'income' or 'expense'
            $table->string('description', 255);
            $table->text('notes')->nullable();
            $table->decimal('amount', 15, 4);
            $table->string('currency', 3);
            $table->string('frequency', 20); // 'daily', 'weekly', 'biweekly', 'monthly', 'quarterly', 'yearly'
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_due_date');
            $table->timestamp('last_posted_at')->nullable();
            $table->boolean('auto_post')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active', 'next_due_date']);
            $table->index(['account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_transactions');
    }
};
