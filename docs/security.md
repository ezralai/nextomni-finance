# NextOmni Finance — Security & Privacy Model

## 1. Threat Model & Financial Sensitivity
NextOmni Finance treats user transaction and balance data as private, sensitive financial information. The threat model accounts for:
- Insecure direct object reference (IDOR / Cross-Tenant Data Access).
- Malicious statement uploads (CSV injection, formula injection, zip bombs).
- Session hijacking and credential stuffing.
- Exposure of secrets in source control or client bundles.

## 2. Core Security Controls

### 2.1 Multi-Tenant Isolation
- All query operations apply strict user scoping (`where('user_id', Auth::id())`).
- Laravel Policies guard every route parameter mutation (`AccountPolicy`, `TransactionPolicy`).
- Database foreign keys cascade cleanly only within the authenticated user's graph.

### 2.2 CSV & Statement Ingestion Hardening
- **Formula Injection Mitigation**: Any transaction description beginning with formula triggers (`=`, `+`, `-`, `@`, `\t`, `\r`) is sanitized or prefixed with a single quote before database entry or export.
- **File Validation**: Strict MIME type checking (`text/csv`, `text/plain`, `application/vnd.ms-excel`), 10MB file limit, virus/entropy scanning hooks, and temporary files stored outside the public document root.
- **Deduplication Hashing**: Transactions compute a deterministic SHA-256 fingerprint:
  `hash = sha256(user_id + account_id + date + amount + sanitized_desc_or_ref)`
  Collision checks occur prior to batch insertion.

### 2.3 Authentication & Session Security
- Passwords hashed using **Argon2id** (or Bcrypt with cost factor 12).
- API authentication uses **Laravel Sanctum** with token expiration and revocability.
- SPA sessions utilize `SameSite=Lax` / `Strict` cookies with `HttpOnly` and `Secure` flags.
- Rate limiting enforced on auth routes (e.g. 5 failed login attempts per minute per IP).

### 2.4 Malaysian Regulatory & Privacy Compliance (PDPA)
- **Data Minimization**: The platform does not store full account numbers (masked to last 4 digits) or bank passwords/credentials.
- **Data Subject Rights**:
  - Full data export (JSON/CSV) available on demand.
  - Hard account deletion with complete cascade of all transactions and uploaded statements.
- **Disclaimer**: NextOmni Finance explicitly declares that it is a self-directed personal budgeting tool and **not** a licensed financial advisory service under Bank Negara Malaysia.
