<?php

namespace App\Services\Transactions;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\Transfer;
use App\Models\User;
use App\Repositories\Contracts\AccountRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    public function __construct(
        protected TransactionRepositoryInterface $transactionRepository,
        protected AccountRepositoryInterface $accountRepository,
        protected AuditLogService $auditLogService
    ) {}

    /**
     * List user transactions with filters.
     */
    public function getTransactions(User $user, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->transactionRepository->getForUser($user->id, $filters, $perPage);
    }

    /**
     * Create an individual transaction (income or expense) and atomically update account balance.
     */
    public function createTransaction(User $user, array $data): Transaction
    {
        return DB::transaction(function () use ($user, $data) {
            $account = Account::where('user_id', $user->id)
                ->lockForUpdate()
                ->findOrFail($data['account_id']);
            $currency = $account->currency ?? 'MYR';
            $amountMoney = Money::of($data['amount'], $currency);

            if ($amountMoney->isZero() || $amountMoney->isNegative()) {
                throw ValidationException::withMessages([
                    'amount' => ['Transaction amount must be strictly greater than 0.'],
                ]);
            }

            $type = strtolower($data['type']); // 'income' or 'expense'
            if (! in_array($type, ['income', 'expense'])) {
                throw ValidationException::withMessages([
                    'type' => ['Invalid transaction type. Must be income or expense.'],
                ]);
            }

            $transactedAt = isset($data['transacted_at'])
                ? Carbon::parse($data['transacted_at'])
                : Carbon::now();

            $description = trim($data['description']);

            // Deduplication Fingerprinting (SHA-256)
            $dateString = $transactedAt->toDateString();
            $hash = hash('sha256', implode('|', [
                $user->id,
                $account->id,
                $dateString,
                $amountMoney->getAmount(),
                strtolower($description),
            ]));

            // Prevent duplicate double-clicks or duplicate automated postings
            $existing = $this->transactionRepository->findByDeduplicationHash($user->id, $hash);
            if ($existing) {
                throw ValidationException::withMessages([
                    'amount' => ['A duplicate transaction with the exact same amount, account, date, and description already exists.'],
                ]);
            }

            // Adjust Account Balance
            $this->applyBalanceDelta($account, $type, $amountMoney);

            try {
                $transaction = $this->transactionRepository->create([
                    'user_id' => $user->id,
                    'account_id' => $account->id,
                    'category_id' => $data['category_id'] ?? null,
                    'type' => $type,
                    'amount' => $amountMoney->getAmount(),
                    'currency' => $currency,
                    'transacted_at' => $transactedAt,
                    'description' => $description,
                    'notes' => $data['notes'] ?? null,
                    'reference_number' => $data['reference_number'] ?? null,
                    'deduplication_hash' => $hash,
                    'status' => $data['status'] ?? 'posted',
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                if (str_contains($e->getMessage(), 'deduplication_hash') || $e->getCode() == 23505 || $e->getCode() == 19) {
                    throw ValidationException::withMessages([
                        'amount' => ['A duplicate transaction with the exact same amount, account, date, and description already exists.'],
                    ]);
                }
                throw $e;
            }

            $this->auditLogService->log($user, 'transaction.created', $transaction, null, $transaction->toArray());

            return $transaction;
        });
    }

    /**
     * Create double-entry transfer between two accounts with atomic balance updates.
     */
    public function createTransfer(User $user, array $data): Transfer
    {
        return DB::transaction(function () use ($user, $data) {
            $sourceId = $data['source_account_id'];
            $destId = $data['destination_account_id'];

            if ($sourceId === $destId) {
                throw ValidationException::withMessages([
                    'destination_account_id' => ['Source and destination accounts must be distinct.'],
                ]);
            }

            // Deadlock prevention: Lock accounts in deterministic sorted string order
            $accountIds = [$sourceId, $destId];
            sort($accountIds, SORT_STRING);

            $lockedAccounts = Account::where('user_id', $user->id)
                ->whereIn('id', $accountIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $sourceAccount = $lockedAccounts->get($sourceId);
            $destinationAccount = $lockedAccounts->get($destId);

            if (! $sourceAccount || ! $destinationAccount) {
                throw ValidationException::withMessages([
                    'source_account_id' => ['One or both accounts could not be found or access is denied.'],
                ]);
            }

            $amountMoney = Money::of($data['amount'], $sourceAccount->currency);
            $feeMoney = Money::of($data['fee'] ?? '0.0000', $sourceAccount->currency);

            if ($amountMoney->isZero() || $amountMoney->isNegative()) {
                throw ValidationException::withMessages([
                    'amount' => ['Transfer amount must be strictly greater than 0.'],
                ]);
            }

            $transactedAt = isset($data['transacted_at'])
                ? Carbon::parse($data['transacted_at'])
                : Carbon::now();

            $notes = $data['notes'] ?? null;

            // Find or use default Transfer category
            $transferCategory = TransactionCategory::where('slug', 'internal-transfer')->first();
            $categoryId = $transferCategory?->id;

            // 1. Source Account Debit/Withdrawal
            // Expense / Outflow from source account: amount + fee
            $totalOutflow = $amountMoney->add($feeMoney);
            $this->applyBalanceDelta($sourceAccount, 'expense', $totalOutflow);

            $sourceTransaction = $this->transactionRepository->create([
                'user_id' => $user->id,
                'account_id' => $sourceAccount->id,
                'category_id' => $categoryId,
                'type' => 'transfer',
                'amount' => $amountMoney->getAmount(),
                'currency' => $sourceAccount->currency,
                'transacted_at' => $transactedAt,
                'description' => "Transfer to {$destinationAccount->name}",
                'notes' => $notes,
                'status' => 'posted',
            ]);

            // 2. Destination Account Credit/Deposit
            $this->applyBalanceDelta($destinationAccount, 'income', $amountMoney);

            $destTransaction = $this->transactionRepository->create([
                'user_id' => $user->id,
                'account_id' => $destinationAccount->id,
                'category_id' => $categoryId,
                'type' => 'transfer',
                'amount' => $amountMoney->getAmount(),
                'currency' => $destinationAccount->currency,
                'transacted_at' => $transactedAt,
                'description' => "Transfer from {$sourceAccount->name}",
                'notes' => $notes,
                'status' => 'posted',
            ]);

            // 3. Persist Transfer record
            $transfer = Transfer::create([
                'user_id' => $user->id,
                'source_account_id' => $sourceAccount->id,
                'destination_account_id' => $destinationAccount->id,
                'source_transaction_id' => $sourceTransaction->id,
                'destination_transaction_id' => $destTransaction->id,
                'amount' => $amountMoney->getAmount(),
                'fee' => $feeMoney->getAmount(),
                'currency' => $sourceAccount->currency,
                'transacted_at' => $transactedAt,
                'notes' => $notes,
            ]);

            $this->auditLogService->log($user, 'transfer.created', $transfer, null, $transfer->toArray());

            return $transfer;
        });
    }

    /**
     * Delete transaction and reverse its balance effects atomically.
     */
    public function deleteTransaction(Transaction $transaction): bool
    {
        return DB::transaction(function () use ($transaction) {
            // Check if part of a transfer
            $sourceTransfer = Transfer::where('source_transaction_id', $transaction->id)->first();
            $destTransfer = Transfer::where('destination_transaction_id', $transaction->id)->first();
            $transfer = $sourceTransfer ?? $destTransfer;

            if ($transfer) {
                return $this->deleteTransfer($transfer);
            }

            // Normal transaction balance reversal with pessimistic lock
            $account = Account::where('id', $transaction->account_id)
                ->lockForUpdate()
                ->findOrFail($transaction->account_id);

            $amountMoney = Money::of($transaction->amount, $transaction->currency);

            // Reverse effect: if it was income, reverse as expense; if expense, reverse as income
            $reversalType = $transaction->type === 'income' ? 'expense' : 'income';
            $this->applyBalanceDelta($account, $reversalType, $amountMoney);

            $this->auditLogService->log($transaction->user, 'transaction.deleted', $transaction, $transaction->toArray(), null);

            return $this->transactionRepository->delete($transaction);
        });
    }

    /**
     * Delete transfer and restore both account balances atomically.
     */
    public function deleteTransfer(Transfer $transfer): bool
    {
        return DB::transaction(function () use ($transfer) {
            // Lock accounts in deterministic sorted string order
            $accountIds = [$transfer->source_account_id, $transfer->destination_account_id];
            sort($accountIds, SORT_STRING);

            $lockedAccounts = Account::whereIn('id', $accountIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $sourceAccount = $lockedAccounts->get($transfer->source_account_id);
            $destinationAccount = $lockedAccounts->get($transfer->destination_account_id);

            $amountMoney = Money::of($transfer->amount, $transfer->currency);
            $feeMoney = Money::of($transfer->fee, $transfer->currency);
            $totalOutflow = $amountMoney->add($feeMoney);

            if ($sourceAccount) {
                // Revert source: add totalOutflow back to source
                $this->applyBalanceDelta($sourceAccount, 'income', $totalOutflow);
            }

            if ($destinationAccount) {
                // Revert destination: remove amount from destination
                $this->applyBalanceDelta($destinationAccount, 'expense', $amountMoney);
            }

            // Delete transactions
            $sourceTxn = $transfer->sourceTransaction;
            $destTxn = $transfer->destinationTransaction;
            $transferData = $transfer->toArray();
            $user = $transfer->user;

            $transfer->delete();

            if ($sourceTxn) {
                $sourceTxn->delete();
            }
            if ($destTxn) {
                $destTxn->delete();
            }

            if ($user) {
                $this->auditLogService->log($user, 'transfer.deleted', $transfer, $transferData, null);
            }

            return true;
        });
    }

    /**
     * Adjust account balance considering asset vs liability account types.
     */
    protected function applyBalanceDelta(Account $account, string $direction, Money $delta): void
    {
        $currentBalance = Money::of($account->current_balance ?? '0.0000', $account->currency);
        $isLiability = in_array($account->account_type, ['credit_card', 'loan']);

        if ($direction === 'income') {
            // Money coming in: assets increase, liabilities (debt) decrease
            $newBalance = $isLiability
                ? $currentBalance->subtract($delta)
                : $currentBalance->add($delta);
        } else {
            // Money going out: assets decrease, liabilities (debt) increase
            $newBalance = $isLiability
                ? $currentBalance->add($delta)
                : $currentBalance->subtract($delta);
        }

        $account->update(['current_balance' => $newBalance->getAmount()]);
    }
}
