<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DocsController extends Controller
{
    /**
     * Render the interactive Scalar API documentation UI.
     */
    public function ui(): Response
    {
        $openapiUrl = url('/api/v1/openapi.json');

        $html = <<<HTML
<!doctype html>
<html lang="en">
  <head>
    <title>NextOmni Finance API — Interactive OpenAPI Specification</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%2310b981'><path d='M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5'/></svg>">
    <style>
      body {
        margin: 0;
        background-color: #0b0f19;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      }
    </style>
  </head>
  <body>
    <script
      id="api-reference"
      data-url="{$openapiUrl}"
      data-configuration='{
        "theme": "purple",
        "darkMode": true,
        "layout": "modern",
        "showSidebar": true,
        "hideModels": false,
        "defaultHttpClient": {
          "targetKey": "shell",
          "clientKey": "curl"
        }
      }'
    ></script>
    <script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
  </body>
</html>
HTML;

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Return the complete OpenAPI 3.1 specification for the NextOmni Finance API.
     */
    public function schema(): JsonResponse
    {
        $serverUrl = url('/api/v1');

        $spec = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'NextOmni Finance API',
                'version' => '1.0.0',
                'description' => 'Enterprise Multi-Currency Personal Finance & Non-Custodial Banking Platform. Engineered with Laravel 11, PostgreSQL 16 UUIDv7, and high-precision financial arithmetic.',
                'contact' => [
                    'name' => 'NextOmni Engineering Team',
                    'url' => 'https://github.com/ezralai/nextomni-finance',
                ],
            ],
            'servers' => [
                [
                    'url' => $serverUrl,
                    'description' => 'Current API Endpoint Server',
                ],
            ],
            'tags' => [
                ['name' => 'Health', 'description' => 'System observability and uptime metrics'],
                ['name' => 'Authentication', 'description' => 'User registration, login, and profile lifecycle'],
                ['name' => 'Institutions', 'description' => 'Supported financial institutions directory'],
                ['name' => 'Currencies', 'description' => 'Real-time FX rates and cross-currency conversion'],
                ['name' => 'Accounts', 'description' => 'Bank, eWallet, card, and investment portfolio accounts'],
                ['name' => 'Categories', 'description' => 'Hierarchical transaction category taxonomy'],
                ['name' => 'Transactions', 'description' => 'Income, expense, and ledger transaction records'],
                ['name' => 'Transfers', 'description' => 'Double-entry internal account transfers'],
                ['name' => 'Budgets', 'description' => 'Monthly spending limits and rollover tracking'],
                ['name' => 'Recurring', 'description' => 'Automated subscriptions and scheduled billing'],
                ['name' => 'Goals', 'description' => 'Savings goals, targets, and contribution milestones'],
                ['name' => 'Analytics', 'description' => 'Aggregated cash flow, net worth, and burn rate analysis'],
                ['name' => 'Reports', 'description' => 'Financial ledger export in CSV and JSON formats'],
                ['name' => 'Imports', 'description' => 'Multi-format bank statement ETL and duplicate skipping'],
            ],
            'components' => [
                'securitySchemes' => [
                    'SanctumBearer' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'Sanctum Token',
                        'description' => 'Authenticate using the Bearer token returned from /auth/login or /auth/register.',
                    ],
                ],
            ],
            'paths' => [
                '/health' => [
                    'get' => [
                        'tags' => ['Health'],
                        'summary' => 'System Health & Diagnostics',
                        'description' => 'Returns database connectivity, query latency (ms), cache responsiveness, and memory metrics.',
                        'responses' => [
                            '200' => ['description' => 'System is fully operational.'],
                            '503' => ['description' => 'Degraded operational state.'],
                        ],
                    ],
                ],
                '/institutions' => [
                    'get' => [
                        'tags' => ['Institutions'],
                        'summary' => 'List Financial Institutions',
                        'description' => 'Retrieve directory of supported banks, fintech wallets, and brokers.',
                        'responses' => [
                            '200' => ['description' => 'List of institutions.'],
                        ],
                    ],
                ],
                '/currencies/rates' => [
                    'get' => [
                        'tags' => ['Currencies'],
                        'summary' => 'Latest FX Exchange Rates',
                        'responses' => [
                            '200' => ['description' => 'Foreign exchange rates matrix.'],
                        ],
                    ],
                ],
                '/currencies/convert' => [
                    'post' => [
                        'tags' => ['Currencies'],
                        'summary' => 'Convert Currency Amount',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['from_currency', 'to_currency', 'amount'],
                                        'properties' => [
                                            'from_currency' => ['type' => 'string', 'example' => 'USD'],
                                            'to_currency' => ['type' => 'string', 'example' => 'MYR'],
                                            'amount' => ['type' => 'string', 'example' => '100.00'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Converted amount and effective exchange rate.'],
                        ],
                    ],
                ],
                '/auth/register' => [
                    'post' => [
                        'tags' => ['Authentication'],
                        'summary' => 'Register New Investor Account',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['name', 'email', 'password', 'base_currency'],
                                        'properties' => [
                                            'name' => ['type' => 'string', 'example' => 'John Doe'],
                                            'email' => ['type' => 'string', 'example' => 'john@example.com'],
                                            'password' => ['type' => 'string', 'example' => 'SecurePass123!'],
                                            'base_currency' => ['type' => 'string', 'example' => 'MYR'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => ['description' => 'Account registered with Sanctum bearer token.'],
                            '422' => ['description' => 'Validation error.'],
                        ],
                    ],
                ],
                '/auth/login' => [
                    'post' => [
                        'tags' => ['Authentication'],
                        'summary' => 'Authenticate Investor Credentials',
                        'description' => 'Demo account: demo@nextomni.com / NextOmniDemo2026!',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['email', 'password'],
                                        'properties' => [
                                            'email' => ['type' => 'string', 'example' => 'demo@nextomni.com'],
                                            'password' => ['type' => 'string', 'example' => 'NextOmniDemo2026!'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Authenticated successfully.'],
                            '401' => ['description' => 'Invalid credentials.'],
                        ],
                    ],
                ],
                '/auth/me' => [
                    'get' => [
                        'tags' => ['Authentication'],
                        'summary' => 'Current User Profile',
                        'security' => [['SanctumBearer' => []]],
                        'responses' => [
                            '200' => ['description' => 'Authenticated user profile.'],
                        ],
                    ],
                ],
                '/accounts' => [
                    'get' => [
                        'tags' => ['Accounts'],
                        'summary' => 'List Accounts & Consolidated Net Worth',
                        'security' => [['SanctumBearer' => []]],
                        'responses' => [
                            '200' => ['description' => 'User accounts with consolidated net worth.'],
                        ],
                    ],
                    'post' => [
                        'tags' => ['Accounts'],
                        'summary' => 'Create New Account',
                        'security' => [['SanctumBearer' => []]],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['name', 'account_type', 'currency', 'opening_balance'],
                                        'properties' => [
                                            'institution_id' => ['type' => 'string', 'format' => 'uuid'],
                                            'name' => ['type' => 'string', 'example' => 'Maybank Savings'],
                                            'account_type' => ['type' => 'string', 'enum' => ['bank', 'credit', 'wallet', 'investment']],
                                            'currency' => ['type' => 'string', 'example' => 'MYR'],
                                            'opening_balance' => ['type' => 'string', 'example' => '1000.00'],
                                            'account_number_masked' => ['type' => 'string', 'example' => '•••• 1234'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => ['description' => 'Account created.'],
                        ],
                    ],
                ],
                '/transactions' => [
                    'get' => [
                        'tags' => ['Transactions'],
                        'summary' => 'List Filtered Transactions',
                        'security' => [['SanctumBearer' => []]],
                        'responses' => [
                            '200' => ['description' => 'Paginated transactions list.'],
                        ],
                    ],
                    'post' => [
                        'tags' => ['Transactions'],
                        'summary' => 'Record New Transaction',
                        'security' => [['SanctumBearer' => []]],
                        'responses' => [
                            '201' => ['description' => 'Transaction created.'],
                        ],
                    ],
                ],
                '/transfers' => [
                    'post' => [
                        'tags' => ['Transfers'],
                        'summary' => 'Execute Account Transfer',
                        'security' => [['SanctumBearer' => []]],
                        'responses' => [
                            '201' => ['description' => 'Transfer executed.'],
                        ],
                    ],
                ],
                '/budgets' => [
                    'get' => [
                        'tags' => ['Budgets'],
                        'summary' => 'List Active Budgets & Consumption Progress',
                        'security' => [['SanctumBearer' => []]],
                        'responses' => [
                            '200' => ['description' => 'Budgets with progress metrics.'],
                        ],
                    ],
                ],
                '/recurring-transactions' => [
                    'get' => [
                        'tags' => ['Recurring'],
                        'summary' => 'List Recurring Subscriptions & Bills',
                        'security' => [['SanctumBearer' => []]],
                        'responses' => [
                            '200' => ['description' => 'Recurring transactions list.'],
                        ],
                    ],
                ],
                '/goals' => [
                    'get' => [
                        'tags' => ['Goals'],
                        'summary' => 'List Financial Goals & Milestones',
                        'security' => [['SanctumBearer' => []]],
                        'responses' => [
                            '200' => ['description' => 'Goals list with progress percentages.'],
                        ],
                    ],
                ],
                '/analytics/cash-flow' => [
                    'get' => [
                        'tags' => ['Analytics'],
                        'summary' => 'Monthly Cash Flow & Income vs Expense Aggregation',
                        'security' => [['SanctumBearer' => []]],
                        'responses' => [
                            '200' => ['description' => 'Cash flow analytics aggregation.'],
                        ],
                    ],
                ],
                '/reports/transactions/csv' => [
                    'get' => [
                        'tags' => ['Reports'],
                        'summary' => 'Export Filtered Transactions as CSV',
                        'security' => [['SanctumBearer' => []]],
                        'responses' => [
                            '200' => ['description' => 'CSV file download.'],
                        ],
                    ],
                ],
                '/imports/statements' => [
                    'post' => [
                        'tags' => ['Imports'],
                        'summary' => 'Import Bank Statement CSV',
                        'security' => [['SanctumBearer' => []]],
                        'responses' => [
                            '200' => ['description' => 'Ingestion report with imported and skipped counts.'],
                        ],
                    ],
                ],
            ],
        ];

        return response()->json($spec);
    }
}
