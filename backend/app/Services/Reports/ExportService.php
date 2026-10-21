<?php

namespace App\Services\Reports;

use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    /**
     * Generate an RFC 4180-compliant, formula-injection-safe CSV stream.
     */
    public function exportTransactionsCsv(User $user, array $filters = []): StreamedResponse
    {
        $query = $this->buildFilteredQuery($user, $filters);
        $timestamp = Carbon::now()->format('Ymd_His');
        $filename = "NextOmni_Transactions_{$timestamp}.csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Microsoft Excel compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // CSV Columns Header
            fputcsv($handle, [
                'Transaction ID',
                'Date & Time',
                'Account',
                'Account Type',
                'Institution',
                'Category',
                'Transaction Type',
                'Amount',
                'Currency',
                'Status',
                'Description',
                'Notes',
                'Reference Number',
            ]);

            // Formula injection sanitizer (CWE-1236)
            $sanitize = function (?string $val): string {
                if ($val === null) {
                    return '';
                }
                $clean = trim($val);
                if ($clean !== '' && in_array($clean[0], ['=', '+', '-', '@', "\t", "\r", "\n", '|', '%'])) {
                    return "'" . $clean;
                }
                return $clean;
            };

            // Process lazily in chunks to maintain constant memory bounds
            foreach ($query->lazy(500) as $txn) {
                fputcsv($handle, [
                    $txn->id,
                    $txn->transacted_at?->toIso8601String() ?? '',
                    $sanitize($txn->account?->name),
                    strtoupper($txn->account?->account_type ?? 'ACCOUNT'),
                    $sanitize($txn->account?->institution?->name ?? 'MANUAL'),
                    $sanitize($txn->category?->name ?? 'UNCATEGORIZED'),
                    strtoupper($txn->type),
                    $txn->amount,
                    $txn->currency,
                    strtoupper($txn->status),
                    $sanitize($txn->description),
                    $sanitize($txn->notes),
                    $sanitize($txn->reference_number),
                ]);
            }

            fclose($handle);
        }, $filename, $headers);
    }

    /**
     * Generate an Open Financial Exchange structured JSON statement.
     */
    public function exportTransactionsJson(User $user, array $filters = []): array
    {
        $query = $this->buildFilteredQuery($user, $filters);

        $baseCurrency = $user->base_currency ?? 'MYR';
        $totalInflow = Money::zero($baseCurrency);
        $totalOutflow = Money::zero($baseCurrency);

        $items = [];
        // Process in lazy cursor batches of 500 to maintain bounded memory
        foreach ($query->lazy(500) as $txn) {
            $money = Money::of($txn->amount, $txn->currency);

            if ($txn->type === 'income') {
                $totalInflow = $totalInflow->add($money);
            } elseif ($txn->type === 'expense') {
                $totalOutflow = $totalOutflow->add($money);
            }

            $items[] = [
                'id' => $txn->id,
                'transacted_at' => $txn->transacted_at?->toIso8601String(),
                'type' => $txn->type,
                'amount' => $txn->amount,
                'currency' => $txn->currency,
                'status' => $txn->status,
                'description' => $txn->description,
                'notes' => $txn->notes,
                'reference_number' => $txn->reference_number,
                'account' => [
                    'id' => $txn->account?->id,
                    'name' => $txn->account?->name,
                    'type' => $txn->account?->account_type,
                    'institution' => $txn->account?->institution?->name,
                ],
                'category' => [
                    'id' => $txn->category?->id,
                    'name' => $txn->category?->name,
                    'slug' => $txn->category?->slug,
                ],
            ];
        }

        return [
            'statement_metadata' => [
                'platform' => 'NextOmni Finance',
                'specification' => 'Enterprise Financial Statement v1',
                'generated_at' => now()->toIso8601String(),
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'base_currency' => $baseCurrency,
                ],
                'total_records' => count($items),
            ],
            'financial_summary' => [
                'total_inflows' => $totalInflow->toDecimal(),
                'total_inflows_formatted' => $totalInflow->format(),
                'total_outflows' => $totalOutflow->toDecimal(),
                'total_outflows_formatted' => $totalOutflow->format(),
                'net_cash_flow' => $totalInflow->subtract($totalOutflow)->toDecimal(),
                'net_cash_flow_formatted' => $totalInflow->subtract($totalOutflow)->format(),
                'currency' => $baseCurrency,
            ],
            'transactions' => $items,
        ];
    }

    /**
     * Build scoped query with criteria filters.
     */
    protected function buildFilteredQuery(User $user, array $filters)
    {
        $query = Transaction::where('user_id', $user->id)
            ->with(['account.institution', 'category'])
            ->orderBy('transacted_at', 'desc')
            ->orderBy('created_at', 'desc');

        if (! empty($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['start_date'])) {
            $query->whereDate('transacted_at', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('transacted_at', '<=', $filters['end_date']);
        }

        return $query;
    }
}
