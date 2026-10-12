<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Goal;
use App\Models\Institution;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DemoAccountSeeder extends Seeder
{
    /**
     * Seed the demo account with realistic Malaysian financial portfolio data.
     */
    public function run(): void
    {
        $demoEmail = 'demo@nextomni.com';
        $demoPassword = 'NextOmniDemo2026!';

        // 1. Create or retrieve Demo User
        $user = User::updateOrCreate(
            ['email' => $demoEmail],
            [
                'name' => 'Demo Investor',
                'password' => Hash::make($demoPassword),
                'base_currency' => 'MYR',
            ]
        );

        // Ensure International & FinTech Institutions exist
        $mbb = Institution::firstOrCreate(['code' => 'MBB'], [
            'name' => 'Maybank',
            'country' => 'MYS',
            'website' => 'https://www.maybank2u.com.my',
        ]);

        $cimb = Institution::firstOrCreate(['code' => 'CIMB'], [
            'name' => 'CIMB Bank',
            'country' => 'MYS',
            'website' => 'https://www.cimbclicks.com.my',
        ]);

        $tng = Institution::firstOrCreate(['code' => 'TNG'], [
            'name' => "Touch 'n Go eWallet",
            'country' => 'MYS',
            'website' => 'https://www.touchngo.com.my',
        ]);

        $grab = Institution::firstOrCreate(['code' => 'GRAB'], [
            'name' => 'GrabPay',
            'country' => 'MYS',
            'website' => 'https://www.grab.com/my',
        ]);

        $wise = Institution::firstOrCreate(['code' => 'WISE'], [
            'name' => 'Wise (TransferWise)',
            'country' => 'GBR',
            'website' => 'https://wise.com',
        ]);

        $ibkr = Institution::firstOrCreate(['code' => 'IBKR'], [
            'name' => 'Interactive Brokers',
            'country' => 'USA',
            'website' => 'https://www.interactivebrokers.com',
        ]);

        // 2. Clean previous demo relational records to guarantee idempotent clean state
        Transaction::where('user_id', $user->id)->delete();
        Budget::where('user_id', $user->id)->delete();
        RecurringTransaction::where('user_id', $user->id)->delete();
        Goal::where('user_id', $user->id)->delete();

        // 3. Create Multi-Asset Financial Accounts
        $maybankAccount = Account::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'Maybank Premier Savings'],
            [
                'institution_id' => $mbb->id,
                'account_type' => 'bank',
                'currency' => 'MYR',
                'opening_balance' => '20000.0000',
                'current_balance' => '28450.5000',
                'account_number_masked' => '•••• 8821',
                'notes' => 'Primary salary and operational cash reserve account',
                'is_active' => true,
            ]
        );

        $cimbAccount = Account::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'CIMB Visa Infinite'],
            [
                'institution_id' => $cimb->id,
                'account_type' => 'credit',
                'currency' => 'MYR',
                'opening_balance' => '0.0000',
                'current_balance' => '-2450.0000',
                'account_number_masked' => '•••• 4519',
                'notes' => 'Everyday cash back & airline miles card',
                'is_active' => true,
            ]
        );

        $tngAccount = Account::updateOrCreate(
            ['user_id' => $user->id, 'name' => "Touch 'n Go eWallet"],
            [
                'institution_id' => $tng->id,
                'account_type' => 'wallet',
                'currency' => 'MYR',
                'opening_balance' => '500.0000',
                'current_balance' => '650.0000',
                'account_number_masked' => '•••• 0123',
                'notes' => 'RFID toll payments and LRT / MRT transit pass',
                'is_active' => true,
            ]
        );

        $grabAccount = Account::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'GrabPay Wallet'],
            [
                'institution_id' => $grab->id,
                'account_type' => 'wallet',
                'currency' => 'MYR',
                'opening_balance' => '200.0000',
                'current_balance' => '380.0000',
                'account_number_masked' => '•••• 9876',
                'notes' => 'Food delivery and ride-hailing balance',
                'is_active' => true,
            ]
        );

        $wiseAccount = Account::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'Wise SGD Savings'],
            [
                'institution_id' => $wise->id,
                'account_type' => 'bank',
                'currency' => 'SGD',
                'opening_balance' => '3500.0000',
                'current_balance' => '4500.0000',
                'account_number_masked' => '•••• 3311',
                'notes' => 'Cross-border ASEAN regional liquid balance',
                'is_active' => true,
            ]
        );

        $ibkrAccount = Account::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'Interactive Brokers USD'],
            [
                'institution_id' => $ibkr->id,
                'account_type' => 'investment',
                'currency' => 'USD',
                'opening_balance' => '10000.0000',
                'current_balance' => '12500.0000',
                'account_number_masked' => '•••• 7744',
                'notes' => 'S&P 500 ETF and global technology equity portfolio',
                'is_active' => true,
            ]
        );

        // Helper to find category by slug
        $getCategory = fn (string $slug) => TransactionCategory::where('slug', $slug)->first();

        $now = Carbon::now();

        // 4. Seed Realistic Transactions
        $transactionsData = [
            [
                'account' => $maybankAccount,
                'slug' => 'salary-wages',
                'type' => 'income',
                'amount' => '12500.0000',
                'currency' => 'MYR',
                'description' => 'Monthly Engineering Director Salary',
                'notes' => 'Net direct deposit from employer',
                'transacted_at' => $now->copy()->subDays(12)->setTime(9, 0),
            ],
            [
                'account' => $cimbAccount,
                'slug' => 'groceries-supermarket',
                'type' => 'expense',
                'amount' => '384.5000',
                'currency' => 'MYR',
                'description' => 'Jaya Grocer Bangsar Shopping Centre',
                'notes' => 'Weekly organic groceries & household items',
                'transacted_at' => $now->copy()->subDays(2)->setTime(16, 30),
            ],
            [
                'account' => $maybankAccount,
                'slug' => 'fuel-petrol',
                'type' => 'expense',
                'amount' => '120.0000',
                'currency' => 'MYR',
                'description' => 'Petronas Solaris Serdang Primax 97',
                'notes' => 'Full tank refill',
                'transacted_at' => $now->copy()->subDays(4)->setTime(8, 15),
            ],
            [
                'account' => $tngAccount,
                'slug' => 'hawker-mamak',
                'type' => 'expense',
                'amount' => '42.0000',
                'currency' => 'MYR',
                'description' => 'Village Park Restaurant Damansara Uptown',
                'notes' => 'Nasi Lemak Ayam Goreng with iced tea',
                'transacted_at' => $now->copy()->subDays(3)->setTime(12, 45),
            ],
            [
                'account' => $tngAccount,
                'slug' => 'public-transit',
                'type' => 'expense',
                'amount' => '8.8000',
                'currency' => 'MYR',
                'description' => 'RapidKL MRT Semantan to Pasar Seni',
                'notes' => 'Commuter transit fare',
                'transacted_at' => $now->copy()->subDays(1)->setTime(18, 20),
            ],
            [
                'account' => $grabAccount,
                'slug' => 'food-delivery',
                'type' => 'expense',
                'amount' => '55.4000',
                'currency' => 'MYR',
                'description' => 'GrabFood Dinner Delivery',
                'notes' => 'Pho & spring rolls order',
                'transacted_at' => $now->copy()->subDays(5)->setTime(19, 45),
            ],
            [
                'account' => $maybankAccount,
                'slug' => 'electricity-tnb',
                'type' => 'expense',
                'amount' => '245.8000',
                'currency' => 'MYR',
                'description' => 'Tenaga Nasional Berhad (TNB)',
                'notes' => 'Residential electricity bill',
                'transacted_at' => $now->copy()->subDays(8)->setTime(14, 0),
            ],
            [
                'account' => $maybankAccount,
                'slug' => 'home-broadband',
                'type' => 'expense',
                'amount' => '159.0000',
                'currency' => 'MYR',
                'description' => 'TM Unifi 300Mbps Home Fibre',
                'notes' => 'High-speed broadband auto-debit',
                'transacted_at' => $now->copy()->subDays(10)->setTime(10, 0),
            ],
            [
                'account' => $cimbAccount,
                'slug' => 'entertainment-subscriptions',
                'type' => 'expense',
                'amount' => '54.9000',
                'currency' => 'MYR',
                'description' => 'Netflix Premium 4K UHD',
                'notes' => 'Monthly family streaming subscription',
                'transacted_at' => $now->copy()->subDays(11)->setTime(11, 0),
            ],
            [
                'account' => $wiseAccount,
                'slug' => 'investments-dividends',
                'type' => 'income',
                'amount' => '500.0000',
                'currency' => 'SGD',
                'description' => 'ASEAN Real Estate Dividend Distribution',
                'notes' => 'Quarterly distribution payout',
                'transacted_at' => $now->copy()->subDays(6)->setTime(15, 30),
            ],
            [
                'account' => $ibkrAccount,
                'slug' => 'investments-dividends',
                'type' => 'income',
                'amount' => '320.0000',
                'currency' => 'USD',
                'description' => 'Vanguard S&P 500 ETF (VOO) Dividend',
                'notes' => 'Quarterly dividend reinvestment allocation',
                'transacted_at' => $now->copy()->subDays(14)->setTime(21, 0),
            ],
        ];

        foreach ($transactionsData as $tx) {
            $cat = $getCategory($tx['slug']);
            Transaction::create([
                'user_id' => $user->id,
                'account_id' => $tx['account']->id,
                'category_id' => $cat?->id,
                'type' => $tx['type'],
                'amount' => $tx['amount'],
                'currency' => $tx['currency'],
                'description' => $tx['description'],
                'notes' => $tx['notes'],
                'status' => 'posted',
                'transacted_at' => $tx['transacted_at'],
            ]);
        }

        // 5. Seed Budgets for Current Month
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $monthEnd = $now->copy()->endOfMonth()->toDateString();

        $budgets = [
            ['slug' => 'groceries-supermarket', 'amount' => '1500.0000'],
            ['slug' => 'restaurants-cafes', 'amount' => '800.0000'],
            ['slug' => 'fuel-petrol', 'amount' => '500.0000'],
            ['slug' => 'electricity-tnb', 'amount' => '400.0000'],
            ['slug' => 'entertainment-subscriptions', 'amount' => '250.0000'],
        ];

        foreach ($budgets as $b) {
            $cat = $getCategory($b['slug']);
            if ($cat) {
                Budget::create([
                    'user_id' => $user->id,
                    'category_id' => $cat->id,
                    'amount' => $b['amount'],
                    'currency' => 'MYR',
                    'period' => 'monthly',
                    'start_date' => $monthStart,
                    'end_date' => $monthEnd,
                    'rollover_enabled' => false,
                    'notes' => "Active monthly spending target for {$cat->name}",
                ]);
            }
        }

        // 6. Seed Recurring Transactions
        $recurringBills = [
            [
                'account' => $maybankAccount,
                'slug' => 'home-broadband',
                'type' => 'expense',
                'amount' => '159.0000',
                'currency' => 'MYR',
                'description' => 'TM Unifi 300Mbps Fibre',
                'notes' => 'Recurring home broadband',
                'frequency' => 'monthly',
                'start_date' => $now->copy()->subMonths(6)->toDateString(),
                'next_due_date' => $now->copy()->addDays(20)->toDateString(),
            ],
            [
                'account' => $maybankAccount,
                'slug' => 'electricity-tnb',
                'type' => 'expense',
                'amount' => '250.0000',
                'currency' => 'MYR',
                'description' => 'Tenaga Nasional Berhad Electricity',
                'notes' => 'Utility estimated auto-pay',
                'frequency' => 'monthly',
                'start_date' => $now->copy()->subMonths(12)->toDateString(),
                'next_due_date' => $now->copy()->addDays(18)->toDateString(),
            ],
            [
                'account' => $cimbAccount,
                'slug' => 'entertainment-subscriptions',
                'type' => 'expense',
                'amount' => '54.9000',
                'currency' => 'MYR',
                'description' => 'Netflix 4K UHD Subscription',
                'notes' => 'Entertainment streaming monthly charge',
                'frequency' => 'monthly',
                'start_date' => $now->copy()->subMonths(8)->toDateString(),
                'next_due_date' => $now->copy()->addDays(19)->toDateString(),
            ],
        ];

        foreach ($recurringBills as $rb) {
            $cat = $getCategory($rb['slug']);
            RecurringTransaction::create([
                'user_id' => $user->id,
                'account_id' => $rb['account']->id,
                'category_id' => $cat?->id,
                'type' => $rb['type'],
                'description' => $rb['description'],
                'notes' => $rb['notes'],
                'amount' => $rb['amount'],
                'currency' => $rb['currency'],
                'frequency' => $rb['frequency'],
                'start_date' => $rb['start_date'],
                'next_due_date' => $rb['next_due_date'],
                'auto_post' => true,
                'is_active' => true,
            ]);
        }

        // 7. Seed Financial Goals & Savings Milestones
        Goal::create([
            'user_id' => $user->id,
            'account_id' => $maybankAccount->id,
            'name' => '6-Month Emergency Fund',
            'description' => 'Liquid emergency reserve in high-yield Maybank savings',
            'target_amount' => '30000.0000',
            'current_amount' => '25000.0000',
            'currency' => 'MYR',
            'target_date' => $now->copy()->addMonths(6)->toDateString(),
            'color' => '#10b981',
            'icon' => 'heroicons:shield-check',
            'status' => 'in_progress',
        ]);

        Goal::create([
            'user_id' => $user->id,
            'account_id' => $maybankAccount->id,
            'name' => 'Damansara Property Downpayment',
            'description' => 'Target downpayment and legal fees for primary home purchase',
            'target_amount' => '100000.0000',
            'current_amount' => '45000.0000',
            'currency' => 'MYR',
            'target_date' => $now->copy()->addMonths(18)->toDateString(),
            'color' => '#6366f1',
            'icon' => 'heroicons:home',
            'status' => 'in_progress',
        ]);
    }
}
