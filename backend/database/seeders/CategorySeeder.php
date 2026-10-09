<?php

namespace Database\Seeders;

use App\Models\TransactionCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Income',
                'type' => 'income',
                'icon' => 'heroicons:banknotes',
                'color' => '#10b981',
                'children' => [
                    ['name' => 'Salary & Wages', 'slug' => 'salary-wages', 'icon' => 'heroicons:briefcase', 'color' => '#10b981'],
                    ['name' => 'Investments & Dividends', 'slug' => 'investments-dividends', 'icon' => 'heroicons:chart-bar-square', 'color' => '#059669'],
                    ['name' => 'Freelance & Side Income', 'slug' => 'freelance-side-income', 'icon' => 'heroicons:laptop', 'color' => '#34d399'],
                    ['name' => 'Rental Income', 'slug' => 'rental-income', 'icon' => 'heroicons:home-modern', 'color' => '#047857'],
                    ['name' => 'Other Income', 'slug' => 'other-income', 'icon' => 'heroicons:banknotes', 'color' => '#6ee7b7'],
                ],
            ],
            [
                'name' => 'Food & Dining',
                'type' => 'expense',
                'icon' => 'heroicons:sparkles',
                'color' => '#f59e0b',
                'children' => [
                    ['name' => 'Groceries & Supermarket', 'slug' => 'groceries-supermarket', 'icon' => 'heroicons:shopping-cart', 'color' => '#f59e0b'],
                    ['name' => 'Restaurants & Cafes', 'slug' => 'restaurants-cafes', 'icon' => 'heroicons:sparkles', 'color' => '#f97316'],
                    ['name' => 'Hawker & Mamak', 'slug' => 'hawker-mamak', 'icon' => 'heroicons:cake', 'color' => '#d97706'],
                    ['name' => 'Food Delivery (GrabFood/Foodpanda)', 'slug' => 'food-delivery', 'icon' => 'heroicons:truck', 'color' => '#ea580c'],
                ],
            ],
            [
                'name' => 'Transportation',
                'type' => 'expense',
                'icon' => 'heroicons:truck',
                'color' => '#ef4444',
                'children' => [
                    ['name' => 'Fuel & Petrol (RON95/97)', 'slug' => 'fuel-petrol', 'icon' => 'heroicons:fire', 'color' => '#ef4444'],
                    ['name' => 'Tolls & Parking (TNG/RFID)', 'slug' => 'tolls-parking', 'icon' => 'heroicons:credit-card', 'color' => '#dc2626'],
                    ['name' => 'Public Transit (LRT/MRT/KTM)', 'slug' => 'public-transit', 'icon' => 'heroicons:ticket', 'color' => '#b91c1c'],
                    ['name' => 'Ride Hailing (Grab/AirAsia)', 'slug' => 'ride-hailing', 'icon' => 'heroicons:map-pin', 'color' => '#991b1b'],
                    ['name' => 'Vehicle Maintenance & Roadtax', 'slug' => 'vehicle-maintenance', 'icon' => 'heroicons:wrench-screwdriver', 'color' => '#7f1d1d'],
                ],
            ],
            [
                'name' => 'Housing & Utilities',
                'type' => 'expense',
                'icon' => 'heroicons:home',
                'color' => '#6366f1',
                'children' => [
                    ['name' => 'Rent & Mortgage', 'slug' => 'rent-mortgage', 'icon' => 'heroicons:home', 'color' => '#6366f1'],
                    ['name' => 'Electricity (TNB)', 'slug' => 'electricity-tnb', 'icon' => 'heroicons:bolt', 'color' => '#4f46e5'],
                    ['name' => 'Water & Sewerage (Air Selangor/IWK)', 'slug' => 'water-sewerage', 'icon' => 'heroicons:beaker', 'color' => '#4338ca'],
                    ['name' => 'Home Broadband (Unifi/Time)', 'slug' => 'home-broadband', 'icon' => 'heroicons:wifi', 'color' => '#3730a3'],
                    ['name' => 'Mobile & Telco Plans', 'slug' => 'mobile-telco', 'icon' => 'heroicons:device-phone-mobile', 'color' => '#312e81'],
                ],
            ],
            [
                'name' => 'Lifestyle & Shopping',
                'type' => 'expense',
                'icon' => 'heroicons:shopping-bag',
                'color' => '#ec4899',
                'children' => [
                    ['name' => 'Shopping & Gadgets', 'slug' => 'shopping-gadgets', 'icon' => 'heroicons:shopping-bag', 'color' => '#ec4899'],
                    ['name' => 'Entertainment & Subscriptions', 'slug' => 'entertainment-subscriptions', 'icon' => 'heroicons:tv', 'color' => '#db2777'],
                    ['name' => 'Fitness & Sports', 'slug' => 'fitness-sports', 'icon' => 'heroicons:heart', 'color' => '#be185d'],
                    ['name' => 'Personal Care & Grooming', 'slug' => 'personal-care', 'icon' => 'heroicons:face-smile', 'color' => '#9d174d'],
                ],
            ],
            [
                'name' => 'Healthcare & Insurance',
                'type' => 'expense',
                'icon' => 'heroicons:shield-check',
                'color' => '#06b6d4',
                'children' => [
                    ['name' => 'Medical & Clinic', 'slug' => 'medical-clinic', 'icon' => 'heroicons:plus-circle', 'color' => '#06b6d4'],
                    ['name' => 'Life & Medical Insurance / Takaful', 'slug' => 'insurance-takaful', 'icon' => 'heroicons:shield-check', 'color' => '#0891b2'],
                ],
            ],
            [
                'name' => 'Transfers & Payments',
                'type' => 'transfer',
                'icon' => 'heroicons:arrows-right-left',
                'color' => '#64748b',
                'children' => [
                    ['name' => 'Internal Account Transfer', 'slug' => 'internal-transfer', 'icon' => 'heroicons:arrows-right-left', 'color' => '#64748b'],
                    ['name' => 'Credit Card Settlement', 'slug' => 'card-settlement', 'icon' => 'heroicons:credit-card', 'color' => '#475569'],
                    ['name' => 'Loan & Financing Repayment', 'slug' => 'loan-repayment', 'icon' => 'heroicons:currency-dollar', 'color' => '#334155'],
                ],
            ],
        ];

        foreach ($categories as $group) {
            $parent = TransactionCategory::updateOrCreate(
                [
                    'user_id' => null,
                    'slug' => Str::slug($group['name']),
                ],
                [
                    'name' => $group['name'],
                    'type' => $group['type'],
                    'icon' => $group['icon'],
                    'color' => $group['color'],
                    'is_active' => true,
                ]
            );

            foreach ($group['children'] as $child) {
                TransactionCategory::updateOrCreate(
                    [
                        'user_id' => null,
                        'slug' => $child['slug'],
                    ],
                    [
                        'parent_id' => $parent->id,
                        'name' => $child['name'],
                        'type' => $group['type'],
                        'icon' => $child['icon'],
                        'color' => $child['color'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
