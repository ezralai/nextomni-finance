# NextOmni Finance — Architecture Overview

## 1. System Vision
NextOmni Finance is an enterprise-grade, portfolio-ready Personal Finance & Budget Tracker designed with a focus on Malaysian and multi-currency personal finance. It provides a non-custodial, privacy-preserving platform for individuals to consolidate multiple bank accounts, e-wallets, credit cards, cash, and investments into a single unified analytical dashboard.

## 2. Architecture Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                   Nuxt 3 Client (SPA/SSR)                   │
│   (Vue 3, TypeScript, Tailwind CSS, Pinia, Chart.js)        │
└──────────────────────────────┬──────────────────────────────┘
                               │ HTTPS / JSON REST API
                               ▼
┌─────────────────────────────────────────────────────────────┐
│                   Laravel 11 API Gateway                    │
│   - Route Authentication (Sanctum) & Rate Limiting          │
│   - FormRequest Strict Validation & Schema Enforcement      │
│   - Policy-based Authorization (Tenant Data Isolation)      │
└──────────────┬──────────────────────────────┬───────────────┘
               │                              │
       Synchronous Queries            Async Dispatches
               ▼                              ▼
┌──────────────────────────────┐ ┌────────────────────────────┐
│      Application Domain      │ │     Redis Queue Worker     │
│ - AccountService             │ │ - CsvStatementImportJob    │
│ - TransactionService         │ │ - StatementNormalizerJob   │
│ - BudgetTrackingService      │ │ - RecurringTxGeneratorJob  │
│ - DuplicateDetector          │ │ - AnomalyDetectorJob       │
└──────────────┬───────────────┘ └────────────┬───────────────┘
               │                              │
               └──────────────┬───────────────┘
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                 PostgreSQL 16 Database                      │
│ - Users, Accounts, Transactions, Categories, Budgets        │
│ - Deduplication Expression Indexes & Numeric(15, 4) Amounts │
└─────────────────────────────────────────────────────────────┘
```

## 3. Core Design Principles

1. **Precision First**: No floating-point data types for currency. All monetary fields use `NUMERIC(15, 4)` / `DECIMAL(15, 2)` to eliminate floating-point calculation drift.
2. **User Data Isolation**: Every resource belongs strictly to a `user_id`. Tenant query scoping and Laravel authorization policies enforce data confidentiality.
3. **Non-Custodial Ingestion**: No unauthorized screen scraping or private banking reverse engineering. Ingestion is powered by user statement uploads (CSV/Excel) and explicit manual input, processed through an extensible connector contract.
4. **Deterministic Deduplication**: Ingestion employs SHA-256 idempotency hashing (`account_id` + `date` + `amount` + `reference_or_description`) to prevent duplicate entry across recurring statement uploads.
5. **Decoupled Contracts**: The frontend (Nuxt 3) communicates with the backend (Laravel 11) exclusively via versioned REST APIs (`/api/v1/*`).
