# NextOmni Finance — Database Design (PostgreSQL 16)

## 1. Schema Conventions
- **Primary Keys**: UUID (`id` via `gen_random_uuid()` or ULID) for distributed scalability and zero key enumeration vulnerabilities.
- **Monetary Precision**: `NUMERIC(15, 4)` for high-precision calculations with presentation formatted to 2 decimals.
- **Timestamps**: UTC `TIMESTAMPTZ` across all entities.
- **Foreign Keys**: Enforced referential integrity with cascading deletes only on child resources belonging to a single parent.

## 2. Entity Relational Diagram (Logical)

```
[ users ]
   ├──< [ accounts ] (FK: institution_id)
   │       └──< [ transactions ]
   │               ├── (FK: category_id) >── [ transaction_categories ]
   │               ├── (FK: merchant_id) >── [ merchants ]
   │               └── (FK: imported_file_id) >── [ imported_files ]
   ├──< [ transaction_categories ] (self-referencing parent_id)
   ├──< [ budgets ] (FK: category_id)
   ├──< [ financial_goals ]
   ├──< [ recurring_transactions ] (FK: account_id, category_id)
   └──< [ transfers ] (source_transaction_id, destination_transaction_id)
```

## 3. Key Entities

### `users`
- `id` (UUID PK)
- `name` (varchar 255)
- `email` (varchar 255 unique)
- `password` (varchar 255)
- `base_currency` (char 3, default 'MYR')
- `created_at`, `updated_at`

### `institutions`
- `id` (UUID PK)
- `name` (varchar 100) — e.g., 'Maybank', 'CIMB Bank', 'TNG Digital', 'GrabPay'
- `code` (varchar 50 unique) — e.g., 'MBB', 'CIMB', 'TNG', 'GRAB'
- `country` (char 3, default 'MYS')
- `website` (varchar 255 nullable)
- `created_at`, `updated_at`

### `accounts`
- `id` (UUID PK)
- `user_id` (UUID FK -> users.id)
- `institution_id` (UUID FK -> institutions.id)
- `name` (varchar 100) — e.g., 'Maybank Savings', 'TNG eWallet'
- `account_type` (enum: 'bank', 'credit_card', 'e_wallet', 'cash', 'investment', 'loan')
- `currency` (char 3, default 'MYR')
- `opening_balance` (NUMERIC 15,4)
- `current_balance` (NUMERIC 15,4)
- `is_active` (boolean, default true)
- `created_at`, `updated_at`

### `transaction_categories`
- `id` (UUID PK)
- `user_id` (UUID FK -> users.id nullable) — null for global seed categories
- `parent_id` (UUID FK -> transaction_categories.id nullable) — for hierarchical subcategories
- `name` (varchar 100)
- `slug` (varchar 100)
- `icon` (varchar 50)
- `color` (varchar 20)
- `type` (enum: 'income', 'expense', 'transfer')
- `created_at`, `updated_at`

### `transactions`
- `id` (UUID PK)
- `user_id` (UUID FK -> users.id)
- `account_id` (UUID FK -> accounts.id)
- `category_id` (UUID FK -> transaction_categories.id nullable)
- `merchant_id` (UUID FK -> merchants.id nullable)
- `type` (enum: 'income', 'expense', 'transfer')
- `amount` (NUMERIC 15,4) — positive scalar
- `currency` (char 3)
- `transacted_at` (TIMESTAMPTZ)
- `description` (text)
- `notes` (text nullable)
- `reference_number` (varchar 100 nullable)
- `deduplication_hash` (varchar 64) — SHA-256 for duplicate detection
- `status` (enum: 'posted', 'pending', 'reconciled', 'void')
- `imported_file_id` (UUID FK -> imported_files.id nullable)
- `created_at`, `updated_at`
- *Indexes*: `(account_id, transacted_at DESC)`, `(user_id, deduplication_hash)`
