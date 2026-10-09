<?php

namespace App\Console\Commands;

use App\Services\Transactions\RecurringTransactionService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessRecurringTransactionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'finance:process-recurring {--date= : The target effective date (YYYY-MM-DD)}';

    /**
     * The console command description.
     */
    protected $description = 'Process and post all due recurring transactions and subscriptions into the ledger';

    /**
     * Execute the console command.
     */
    public function handle(RecurringTransactionService $recurringService): int
    {
        $dateOption = $this->option('date');
        $targetDate = $dateOption ? Carbon::parse($dateOption)->toDateString() : Carbon::today()->toDateString();

        $this->info("Processing due recurring transactions as of {$targetDate}...");

        $posted = $recurringService->processDueRecurringTransactions($targetDate);

        $this->info("Successfully posted {$posted} recurring transaction(s) to ledger.");

        return Command::SUCCESS;
    }
}
