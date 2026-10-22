<?php

namespace App\Services\Accounts;

use App\Models\Account;
use App\Models\User;
use App\Repositories\Contracts\AccountRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Services\Currencies\CurrencyConversionService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AccountService
{
    public function __construct(
        protected AccountRepositoryInterface $accountRepository,
        protected CurrencyConversionService $currencyConversionService,
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Provision a new financial account inside an explicit database transaction.
     */
    public function createAccount(User $user, array $data): Account
    {
        return DB::transaction(function () use ($user, $data) {
            $currency = strtoupper($data['currency'] ?? $user->base_currency ?? 'MYR');
            $openingBalanceMoney = Money::of($data['opening_balance'] ?? '0.0000', $currency);

            $account = $this->accountRepository->create([
                'user_id' => $user->id,
                'institution_id' => $data['institution_id'],
                'name' => trim($data['name']),
                'account_type' => $data['account_type'],
                'currency' => $currency,
                'opening_balance' => $openingBalanceMoney->getAmount(),
                'current_balance' => $openingBalanceMoney->getAmount(),
                'account_number_masked' => isset($data['account_number_masked']) ? trim($data['account_number_masked']) : null,
                'notes' => $data['notes'] ?? null,
                'is_active' => true,
            ]);

            $this->auditLogService->log($user, 'account.created', $account, null, $account->toArray());

            return $account;
        });
    }

    /**
     * Update account details.
     */
    public function updateAccount(Account $account, array $data): Account
    {
        return DB::transaction(function () use ($account, $data) {
            $payload = [];

            if (isset($data['name'])) {
                $payload['name'] = trim($data['name']);
            }
            if (isset($data['account_type'])) {
                $payload['account_type'] = $data['account_type'];
            }
            if (isset($data['account_number_masked'])) {
                $payload['account_number_masked'] = trim($data['account_number_masked']);
            }
            if (isset($data['notes'])) {
                $payload['notes'] = $data['notes'];
            }
            if (isset($data['is_active'])) {
                $payload['is_active'] = (bool) $data['is_active'];
            }

            $oldValues = $account->toArray();
            $updated = $this->accountRepository->update($account, $payload);

            if ($account->user) {
                $this->auditLogService->log($account->user, 'account.updated', $updated, $oldValues, $updated->toArray());
            }

            return $updated;
        });
    }

    /**
     * Remove an account.
     */
    public function deleteAccount(Account $account): bool
    {
        return DB::transaction(function () use ($account) {
            $oldValues = $account->toArray();
            $user = $account->user;

            $deleted = $this->accountRepository->delete($account);

            if ($deleted && $user) {
                $this->auditLogService->log($user, 'account.deleted', $account, $oldValues, null);
            }

            return $deleted;
        });
    }

    /**
     * Retrieve user accounts alongside aggregated net worth and type breakdowns.
     *
     * @return array{
     *   accounts: Collection<int, Account>,
     *   net_worth: string,
     *   net_worth_formatted: string,
     *   currency: string,
     *   breakdown: array<string, string>
     * }
     */
    public function getAccountsOverview(User $user): array
    {
        $accounts = $this->accountRepository->getForUser($user->id, ['is_active' => true]);
        $baseCurrency = $user->base_currency ?? 'MYR';

        $totalNetWorth = Money::zero($baseCurrency);
        $breakdowns = [
            'bank' => Money::zero($baseCurrency),
            'e_wallet' => Money::zero($baseCurrency),
            'cash' => Money::zero($baseCurrency),
            'credit_card' => Money::zero($baseCurrency),
            'investment' => Money::zero($baseCurrency),
            'loan' => Money::zero($baseCurrency),
        ];

        foreach ($accounts as $account) {
            $rawBalance = Money::of($account->current_balance ?? '0.0000', $account->currency);
            $convertedBalance = $this->currencyConversionService->convert($rawBalance, $baseCurrency);
            $type = $account->account_type;

            if (isset($breakdowns[$type])) {
                $breakdowns[$type] = $breakdowns[$type]->add($convertedBalance);
            }

            if (in_array($type, ['credit_card', 'loan'])) {
                $totalNetWorth = $totalNetWorth->subtract($convertedBalance);
            } else {
                $totalNetWorth = $totalNetWorth->add($convertedBalance);
            }
        }

        return [
            'accounts' => $accounts,
            'net_worth' => $totalNetWorth->toDecimal(),
            'net_worth_formatted' => $totalNetWorth->format(),
            'currency' => $baseCurrency,
            'breakdown' => array_map(fn (Money $m) => $m->toDecimal(), $breakdowns),
        ];
    }
}
