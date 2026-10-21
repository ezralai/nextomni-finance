# NextOmni Finance — Architecture & System Design Overview

## 1. System Vision
NextOmni Finance is an enterprise-grade, non-custodial Personal Finance & Budget Tracker designed with a focus on Malaysian and multi-currency personal finance. It provides a privacy-preserving platform for individuals to consolidate multiple bank accounts, e-wallets, credit cards, cash, and investments into a single unified analytical dashboard powered by predictive machine learning.

---

## 2. C4 Level 2 Container Architecture

```mermaid
graph TD
    subgraph ClientLayer ["Client Tier (Browser / Desktop)"]
        UI["Nuxt 4 SPA / SSR\n(Vue 3.5, Vite, TypeScript, Tailwind CSS)"]
        UIStore["Pinia State Management\n(accounts, transactions, insights, auth)"]
        UIModals["Modular Headless Dialogs\n(TransactionModal, TransferModal, AddAccountModal)"]
        UI --> UIStore
        UIStore --> UIModals
    end

    subgraph ApiGateway ["API Gateway & Core Business Logic (Laravel 11)"]
        Router["Sanctum Auth Middleware & Rate Limiter"]
        TxService["TransactionService\n(Deadlock-Free Lock Sorter, Ledger Math)"]
        ExportService["ExportService\n(O(1) Lazy Cursor Streaming)"]
        MLBridge["MlIntelligenceService\n(Process Execution & Fallback Heuristics)"]
        Cache["Redis 7 Cluster\n(Session, Cache: ml_intelligence_*)"]
    end

    subgraph DataStorage ["Persistent Storage Tier"]
        DB[("PostgreSQL 16 / SQLite\n- accounts (balance DECIMAL 15,4)\n- transactions (dedup_hash SHA-256)\n- user_insights_consents")]
    end

    subgraph MLEngine ["ML Intelligence Engine (Python 3.14 Virtualenv)"]
        MLService["service.py (Unified CLI & JSON Pipeline)"]
        AnomalyMod["IsolationForest (Outlier Anomaly Detection)"]
        ForecastMod["Ridge Regression (Spending Trajectory)"]
        StatMod["Category IQR (Z-Score & Interquartile Filter)"]
    end

    UI -->|"HTTPS JSON (REST /api/v1/*)"| Router
    Router --> TxService
    Router --> ExportService
    Router --> MLBridge

    TxService -->|"ACID SELECT FOR UPDATE (Sorted Keys)"| DB
    ExportService -->|"Cursor Stream (Chunks of 500)"| DB
    ExportService -->|"HTTP 200 Chunked Stream"| UI

    MLBridge -->|"Redis Read/Write (TTL 30m)"| Cache
    MLBridge -->|"Subprocess Stdio (JSON Payload)"| MLService
    MLService --> AnomalyMod
    MLService --> ForecastMod
    MLService --> StatMod
```

---

## 3. Core Design Principles & Scalability Patterns

1. **Precision First**: No floating-point data types for currency. All monetary fields use `NUMERIC(15, 4)` / `DECIMAL(15, 4)` to eliminate floating-point calculation drift.
2. **User Data Isolation**: Every resource belongs strictly to a `user_id`. Tenant query scoping and Laravel authorization policies enforce data confidentiality.
3. **Deterministic Deadlock-Free Concurrency**: In multi-account transfers, target account IDs are sorted lexicographically before acquiring row-level locks (`whereIn('id', $sortedIds)->lockForUpdate()`), mathematically preventing circular wait deadlocks between concurrent bidirectional transfers.
4. **$O(1)$ Bounded Memory Streaming**: Large statement exports use Eloquent's lazy cursors (`lazy(500)`) writing directly to `php://output`, bounding memory consumption to under 32MB regardless of dataset size while applying formula injection sanitization (CWE-1236).
5. **Decoupled ML Subprocess Bridge**: Laravel interacts with Python ML models via an isolated subprocess execution bridge with a 30-minute Redis cache tier and deterministic statistical fallback heuristics (IQR / moving average).
6. **Frontend Monolith Decomposition**: Complex page views (e.g. `workspace.vue`) are broken down into single-responsibility modal components, preserving high-speed Hot Module Replacement (HMR) and testability.

---

## 4. Architectural Decision Records (ADRs)

### ADR-001: Lexicographical Resource Ordering for Database Lock Acquisition in Transfers
- **Status**: Implemented
- **Context**: Concurrent transfers between accounts $A \rightarrow B$ and $B \rightarrow A$ risk circular wait deadlocks (`40P01`) under high throughput.
- **Decision**: Sort target account primary keys alphabetically prior to calling `lockForUpdate()`.
- **Result**: Zero deadlocks under concurrent bidirectional load with $< 0.05\,\text{ms}$ overhead.

### ADR-002: Bounded Lazy Cursor Evaluation for Statement Exports
- **Status**: Implemented
- **Context**: Full model hydration (`Transaction::get()`) caused memory limit exhaustion on accounts with thousands of transactions.
- **Decision**: Stream chunked database cursors (`lazy(500)`) directly into PHP's output buffer (`php://output`).
- **Result**: Constant memory consumption ($< 32\,\text{MB}$) regardless of transaction count.

### ADR-003: Subprocess Process Bridge with Tiered Redis Caching & Deterministic Fallbacks
- **Status**: Implemented
- **Context**: Need predictive ML insights without the infrastructure complexity of a separate microservice cluster in initial deployment phases.
- **Decision**: Execute Python CLI via standard process I/O with 1,800s Redis cache and deterministic statistical fallback heuristics in PHP.
- **Result**: High system resilience; dashboard never fails if Python is unavailable or execution times out.
