# NextOmni Finance — REST API Reference (v1)

## Base URL
- Local: `http://localhost:8000/api/v1`
- Content-Type: `application/json`
- Accept: `application/json`

## Endpoints

### 1. System Health
- **`GET /api/v1/health`**
- **Auth**: None (Public)
- **Response** (`200 OK`):
```json
{
  "status": "ok",
  "app": "NextOmni Finance API",
  "version": "1.0.0",
  "environment": "local",
  "timestamp": "2026-10-09T01:31:00+00:00",
  "database": {
    "status": "connected",
    "driver": "sqlite"
  }
}
```

### 2. User & Authentication (Sanctum)
- **`POST /api/v1/auth/register`**: Register new user account.
- **`POST /api/v1/auth/login`**: Authenticate and retrieve bearer token / session cookie.
- **`POST /api/v1/auth/logout`**: Revoke active token.
- **`GET /api/v1/user`**: Retrieve authenticated user profile and settings.

### 3. Financial Accounts
- **`GET /api/v1/accounts`**: List user financial accounts with current balances.
- **`POST /api/v1/accounts`**: Create a new bank/e-wallet/cash account.
- **`GET /api/v1/accounts/{id}`**: Get specific account details.
- **`PUT /api/v1/accounts/{id}`**: Update account attributes.
- **`DELETE /api/v1/accounts/{id}`**: Archive or soft-delete account.

### 4. Transactions & Transfers
- **`GET /api/v1/transactions`**: Filterable transaction list (`account_id`, `category_id`, `start_date`, `end_date`, `type`, `search`).
- **`POST /api/v1/transactions`**: Record an income, expense, or inter-account transfer.
- **`GET /api/v1/transactions/{id}`**: Get transaction detail.
- **`PUT /api/v1/transactions/{id}`**: Update transaction.
- **`DELETE /api/v1/transactions/{id}`**: Delete transaction and adjust account balance.

### 5. Statement Ingestion (CSV / Excel)
- **`POST /api/v1/imports/upload`**: Upload statement file (`multipart/form-data`) and detect headers.
- **`POST /api/v1/imports/preview`**: Preview mapped canonical rows with duplicate flags.
- **`POST /api/v1/imports/commit`**: Asynchronously process import batch via queue.
- **`GET /api/v1/imports/{id}/status`**: Check import job status and summary.

### 6. Budgets & Goals
- **`GET /api/v1/budgets`**: Current month budget allocation and real-time utilization.
- **`POST /api/v1/budgets`**: Set category monthly budget.
- **`GET /api/v1/goals`**: Track savings targets and progress.
