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
        Schema::create('transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('source_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUuid('destination_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUuid('source_transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignUuid('destination_transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->decimal('amount', 15, 4);
            $table->decimal('fee', 15, 4)->default(0.0000);
            $table->string('currency', 3)->default('MYR');
            $table->timestamp('transacted_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'transacted_at']);
            $table->index(['source_account_id', 'transacted_at']);
            $table->index(['destination_account_id', 'transacted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
