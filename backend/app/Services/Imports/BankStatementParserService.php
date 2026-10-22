<?php

namespace App\Services\Imports;

use App\Models\Account;
use App\Models\StatementImport;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Transactions\TransactionService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BankStatementParserService
{
    /**
     * Merchant-to-category pattern classification rules.
     */
    protected array $merchantRules = [
        'food-delivery' => ['grabfood', 'foodpanda', 'deliveroo', 'shopeefood', 'airasia food', 'beepit'],
        'ride-hailing' => ['grab', 'airasia ride', 'indrive', 'uber', 'lyft', 'mycar', 'maxim'],
        'groceries-supermarket' => [
            'lotus', 'tesco', 'jaya grocer', 'village grocer', 'aeon', 'giant', 'cold storage',
            'supermarket', 'grocery', 'bens independent', 'mygroser', 'econsave', '99 speedmart',
            'speedmart', 'hero market', 'nsk', 'segifresh',
        ],
        'restaurants-cafes' => [
            'starbucks', 'mcdonald', 'kfc', 'subway', 'nando', 'coffee bean', 'zus coffee',
            'bask bear', 'restaurant', 'cafe', 'bistro', 'dining', 'sushi', 'pizza', 'domino',
            'burger king', 'marrybrown', 'tealive', 'gong cha', 'chagee', 'koi the', 'liho',
            'boost juice', 'secret recipe', 'kenny rogers', 'bakery', 'bread',
        ],
        'hawker-mamak' => ['mamak', 'kopitiam', 'hawker', 'nasi kandar', 'warung', 'kedai kopi', 'restoran nasi'],
        'fuel-petrol' => ['petronas', 'shell', 'caltex', 'petron', 'bhp', 'fuel', 'petrol', 'mesra'],
        'tolls-parking' => [
            'touch n go', 'touch \'n go', 'tng', 'rfid', 'plus expressways', 'parking', 'jomrun',
            'kiple', 'wilayah parking', 'dbkl', 'mbpj', 'mbsa', 'smart selangor',
        ],
        'public-transit' => ['mrt', 'lrt', 'ktm', 'rapidkl', 'prasarana', 'monorail', 'erl', 'klia ekspres', 'transit'],
        'vehicle-maintenance' => [
            'car service', 'workshop', 'tyre', 'tayar', 'mechanic', 'auto service', 'honda service',
            'toyota service', 'perodua', 'proton service', 'car wash',
        ],
        'electricity-tnb' => ['tenaga', 'tnb', 'sesb', 'seb', 'electricity'],
        'water-sewerage' => ['air selangor', 'syabas', 'indah water', 'iwk', 'sains', 'laku', 'saj'],
        'home-broadband' => ['unifi', 'time dotcom', 'time internet', 'broadband', 'maxis broadband', 'astro'],
        'mobile-telco' => ['maxis', 'celcom', 'digi', 'umobile', 'u mobile', 'yes 5g', 'telco', 'postpaid', 'hotlink', 'xox', 'yoodo'],
        'entertainment-subscriptions' => [
            'netflix', 'spotify', 'youtube', 'disney', 'apple services', 'app store', 'google play',
            'google storage', 'prime video', 'patreon', 'steam', 'playstation', 'nintendo', 'cinema', 'gsc', 'tgv',
        ],
        'shopping-gadgets' => [
            'shopee', 'lazada', 'amazon', 'tiktok shop', 'taobao', 'ikea', 'uniqlo', 'zara',
            'h&m', 'padini', 'machines', 'switch', 'apple store', 'harvey norman', 'senheng', 'all it hypermarket',
        ],
        'fitness-sports' => ['anytime fitness', 'celebrity fitness', 'fitness first', 'gym', 'badminton', 'decathlon', 'sports direct', 'nike', 'adidas'],
        'personal-care' => ['watson', 'guardian', 'caring', 'sephora', 'barber', 'hair salon', 'saloon', 'spa'],
        'medical-clinic' => ['clinic', 'klinik', 'hospital', 'pharmacy', 'farmasi', 'pathlab', 'doctor', 'dental', 'gigi'],
        'insurance-takaful' => ['prudential', 'great eastern', 'allianz', 'aia', 'etiqa', 'takaful', 'zurich', 'manulife', 'insurance'],
        'salary-wages' => ['salary', 'payroll', 'wage', 'gaji', 'allowance', 'stipend', 'bonus'],
        'investments-dividends' => ['dividend', 'stashaway', 'wahed', 'rakuten', 'binance', 'luno', 'kenanga', 'bursa', 'asnb', 'unit trust', 'fund'],
        'rent-mortgage' => ['rental', 'sewa', 'mortgage', 'housing loan'],
        'card-settlement' => ['credit card payment', 'cc payment', 'card settlement', 'card payment'],
        'loan-repayment' => ['hire purchase', 'car loan', 'personal loan', 'repayment', 'ptptn'],
    ];

    public function __construct(
        protected TransactionService $transactionService,
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Parse an uploaded bank statement CSV and reconcile into user's account transactions.
     */
    public function parseAndImport(User $user, Account $account, UploadedFile $file): array
    {
        // Sanitize original filename (prevent path traversal, formula injection, and XSS)
        $rawFilename = basename($file->getClientOriginalName());
        $safeFilename = preg_replace('/[^a-zA-Z0-9_\-\. ]/', '', $rawFilename);
        $safeFilename = ltrim($safeFilename ?: 'statement', "=+-\t\r\n|%@");
        if (empty($safeFilename)) {
            $safeFilename = 'statement_' . date('Ymd_His') . '.csv';
        }

        $handle = fopen($file->getRealPath(), 'r');
        if (! $handle) {
            throw ValidationException::withMessages([
                'file' => ['Could not read the uploaded statement file.'],
            ]);
        }

        // Read and strip UTF-8 BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headerRow = fgetcsv($handle);
        if (! $headerRow) {
            fclose($handle);
            throw ValidationException::withMessages([
                'file' => ['The uploaded CSV file is empty.'],
            ]);
        }

        // Normalize header column names
        $headers = array_map(function ($h) {
            return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $h)));
        }, $headerRow);

        $dateIndex = $this->findColumnIndex($headers, ['date', 'txn_date', 'transaction_date', 'posting_date', 'value_date']);
        $descIndex = $this->findColumnIndex($headers, ['description', 'details', 'narrative', 'payee', 'memo', 'transaction_details', 'reference']);
        $amountIndex = $this->findColumnIndex($headers, ['amount', 'trans_amount', 'transaction_amount']);
        $typeIndex = $this->findColumnIndex($headers, ['type', 'txn_type', 'transaction_type', 'cr_dr', 'dr_cr']);
        $debitIndex = $this->findColumnIndex($headers, ['debit', 'withdrawal', 'outflow', 'debit_amount']);
        $creditIndex = $this->findColumnIndex($headers, ['credit', 'deposit', 'inflow', 'credit_amount']);
        $categoryIndex = $this->findColumnIndex($headers, ['category', 'cat', 'category_name', 'classification']);

        if ($dateIndex === null || ($amountIndex === null && $debitIndex === null && $creditIndex === null)) {
            fclose($handle);
            throw ValidationException::withMessages([
                'file' => ['Unable to detect required statement columns. The CSV must contain at least Date and Amount (or Debit/Credit) columns.'],
            ]);
        }

        // Pre-cache user accessible categories in memory for O(1) matching
        $categoryRecords = TransactionCategory::accessibleBy($user->id)->get();
        $categoriesBySlug = [];
        $categoriesByName = [];
        foreach ($categoryRecords as $cat) {
            $categoriesBySlug[$cat->slug] = $cat;
            $categoriesByName[strtolower($cat->name)] = $cat;
        }

        $totalRows = 0;
        $importedCount = 0;
        $duplicateCount = 0;
        $importedTransactions = [];

        while (($row = fgetcsv($handle)) !== false) {
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            $totalRows++;

            try {
                $rawDate = $row[$dateIndex] ?? null;
                $transactedAt = $this->parseDate($rawDate);

                $description = $descIndex !== null && isset($row[$descIndex])
                    ? trim($row[$descIndex])
                    : 'Bank Statement Posting';

                // Extract amount and direction (income vs expense)
                [$amount, $type] = $this->extractAmountAndType(
                    $row,
                    $amountIndex,
                    $debitIndex,
                    $creditIndex,
                    $typeIndex
                );

                if ($amount <= 0) {
                    continue;
                }

                // Resolve category from explicit column or merchant heuristics
                $rawCategory = $categoryIndex !== null && isset($row[$categoryIndex]) ? trim($row[$categoryIndex]) : null;
                $category = $this->classifyCategory($description, $type, $rawCategory, $categoriesBySlug, $categoriesByName);

                // Call TransactionService (atomic account balance update & deduplication check)
                $txn = $this->transactionService->createTransaction($user, [
                    'account_id' => $account->id,
                    'category_id' => $category?->id,
                    'type' => $type,
                    'amount' => (string) $amount,
                    'description' => $description,
                    'transacted_at' => $transactedAt->toDateString(),
                    'notes' => "Reconciled from bank statement import: {$safeFilename}",
                ]);

                $importedCount++;
                $importedTransactions[] = [
                    'id' => $txn->id,
                    'date' => $transactedAt->toDateString(),
                    'description' => $description,
                    'amount' => $amount,
                    'type' => $type,
                    'category_id' => $category?->id,
                    'category_name' => $category?->name,
                    'category_slug' => $category?->slug,
                ];
            } catch (ValidationException $e) {
                // If deduplication hash already exists, mark as duplicate
                $messages = implode(' ', array_merge(...array_values($e->errors())));
                if (str_contains(strtolower($messages), 'duplicate')) {
                    $duplicateCount++;
                } else {
                    Log::warning('Statement row rejected', ['row' => $row, 'error' => $messages]);
                }
            } catch (\Throwable $e) {
                Log::warning('Statement row parsing error', ['row' => $row, 'error' => $e->getMessage()]);
            }
        }

        fclose($handle);

        // Record statement import batch
        $importRecord = StatementImport::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'filename' => $safeFilename,
            'total_rows' => $totalRows,
            'imported_count' => $importedCount,
            'duplicate_count' => $duplicateCount,
            'status' => $importedCount > 0 ? 'completed' : ($duplicateCount > 0 ? 'completed' : 'failed'),
        ]);

        $this->auditLogService->log($user, 'statement.imported', $importRecord, null, [
            'import_id' => $importRecord->id,
            'filename' => $importRecord->filename,
            'account_id' => $account->id,
            'account_name' => $account->name,
            'total_rows' => $totalRows,
            'imported_count' => $importedCount,
            'duplicate_count' => $duplicateCount,
            'status' => $importRecord->status,
        ]);

        return [
            'import_id' => $importRecord->id,
            'filename' => $importRecord->filename,
            'account_id' => $account->id,
            'account_name' => $account->name,
            'total_rows' => $totalRows,
            'imported_count' => $importedCount,
            'duplicate_count' => $duplicateCount,
            'status' => $importRecord->status,
            'transactions' => $importedTransactions,
        ];
    }

    /**
     * Find header column matching any candidate aliases.
     */
    protected function findColumnIndex(array $headers, array $candidates): ?int
    {
        foreach ($headers as $index => $header) {
            foreach ($candidates as $cand) {
                if ($header === $cand || str_contains($header, $cand)) {
                    return $index;
                }
            }
        }
        return null;
    }

    /**
     * Parse date string handling varied bank statement representations.
     */
    protected function parseDate(?string $rawDate): Carbon
    {
        if (! $rawDate) {
            return Carbon::today();
        }

        $clean = trim($rawDate);
        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'd M Y', 'd-M-Y', 'Y/m/d'];

        foreach ($formats as $fmt) {
            try {
                return Carbon::createFromFormat($fmt, $clean)->startOfDay();
            } catch (\Throwable) {
                continue;
            }
        }

        return Carbon::parse($clean)->startOfDay();
    }

    /**
     * Extract normalized numeric amount and income/expense classification.
     */
    protected function extractAmountAndType(
        array $row,
        ?int $amountIndex,
        ?int $debitIndex,
        ?int $creditIndex,
        ?int $typeIndex
    ): array {
        // Case 1: Separate Debit and Credit columns
        if ($debitIndex !== null || $creditIndex !== null) {
            $debitRaw = $debitIndex !== null && isset($row[$debitIndex]) ? $this->cleanAmount($row[$debitIndex]) : 0.0;
            $creditRaw = $creditIndex !== null && isset($row[$creditIndex]) ? $this->cleanAmount($row[$creditIndex]) : 0.0;

            if ($creditRaw > 0) {
                return [$creditRaw, 'income'];
            }
            if ($debitRaw > 0) {
                return [$debitRaw, 'expense'];
            }
        }

        // Case 2: Single amount column
        $rawAmount = $amountIndex !== null && isset($row[$amountIndex]) ? $row[$amountIndex] : '0';
        $isNegative = str_contains($rawAmount, '-') || str_contains($rawAmount, '(');
        $num = $this->cleanAmount($rawAmount);

        // Explicit Type Column (e.g. CR/DR)
        if ($typeIndex !== null && isset($row[$typeIndex])) {
            $typeStr = strtoupper(trim($row[$typeIndex]));
            if (in_array($typeStr, ['CR', 'CREDIT', 'INCOME', 'INFLOW', 'DEPOSIT'])) {
                return [$num, 'income'];
            }
            if (in_array($typeStr, ['DR', 'DEBIT', 'EXPENSE', 'OUTFLOW', 'WITHDRAWAL'])) {
                return [$num, 'expense'];
            }
        }

        return $isNegative ? [$num, 'expense'] : [$num, 'income'];
    }

    /**
     * Resolve transaction category using explicit statement data or merchant heuristics.
     */
    protected function classifyCategory(
        string $description,
        string $type,
        ?string $rawCategory,
        array $categoriesBySlug,
        array $categoriesByName
    ): ?TransactionCategory {
        // 1. Explicit Category Matching from Statement
        if ($rawCategory !== null && $rawCategory !== '') {
            $cleaned = strtolower(trim($rawCategory));
            $slugged = Str::slug($rawCategory);

            if (isset($categoriesBySlug[$slugged])) {
                return $categoriesBySlug[$slugged];
            }
            if (isset($categoriesByName[$cleaned])) {
                return $categoriesByName[$cleaned];
            }
        }

        // 2. Intelligent Merchant Pattern Matching
        $normalizedDesc = strtolower($description);

        foreach ($this->merchantRules as $categorySlug => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($normalizedDesc, $pattern)) {
                    if (isset($categoriesBySlug[$categorySlug])) {
                        return $categoriesBySlug[$categorySlug];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Clean numeric string stripping currency symbols and punctuation.
     */
    protected function cleanAmount(string $val): float
    {
        $clean = trim($val);
        $clean = str_replace([',', 'RM', 'USD', 'SGD', '$', '(', ')', '+', ' '], '', $clean);
        $clean = str_replace('-', '', $clean);

        return abs((float) $clean);
    }
}
