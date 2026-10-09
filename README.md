# NextOmni Finance — Personal Finance & Budget Platform

> An enterprise-grade, portfolio-quality personal finance and budgeting platform built with **Laravel 11**, **Nuxt 3 (Vue 3 + TypeScript)**, **PostgreSQL 16**, **Redis**, and **Tailwind CSS**. Part of the NextOmni systems ecosystem.

---

## 1. System Overview

**NextOmni Finance** is engineered specifically for Malaysian and multi-currency personal finance. It provides a non-custodial, privacy-preserving workflow for consolidating bank accounts, e-wallets, credit cards, cash, and investments into a single unified analytical ledger.

### Key Engineering Highlights
- **Full-Stack Decoupled Architecture**: High-throughput Laravel 11 REST API gateway + reactive Nuxt 3 (TypeScript) frontend.
- **Financial Precision**: All monetary values calculated and stored in `NUMERIC(15, 4)` / `DECIMAL(15, 2)` — eliminating floating-point calculation drift.
- **Statement ETL Pipeline**: Non-custodial ingestion supporting CSV statements (Maybank M2U, CIMB Clicks, TNG eWallet, GrabPay).
- **Deterministic Deduplication**: Content-hash-based duplicate detection using SHA-256 fingerprinting.
- **Security & Multi-Tenancy**: Strict tenant isolation via user-scoped queries and Laravel authorization policies, authenticated via Laravel Sanctum.

---

## 2. Technology Stack

| Layer | Technology | Version | Purpose |
| :--- | :--- | :--- | :--- |
| **Backend** | Laravel / PHP | 11.x / 8.3 | REST API Gateway, Domain Services, Queues |
| **Frontend** | Nuxt 3 / Vue 3 | 4.x / 3.5 | Reactive UI, TypeScript, Pinia State |
| **Styling** | Tailwind CSS | 3.4 | Modern Financial Design System |
| **Database** | PostgreSQL | 16 | ACID-compliant relational persistence |
| **Queue & Cache**| Redis | 7 | Asynchronous jobs and statement import processing |
| **Auth** | Laravel Sanctum | 4.x | Token and SPA session authentication |
| **Testing** | PHPUnit / Pest | 12.x / 8.x | Backend unit and feature testing |

---

## 3. Quick Start (Local Development)

### Prerequisites
- **PHP 8.3+** & **Composer 2+**
- **Node.js 22+** & **pnpm** (or npm)
- **Redis** & **PostgreSQL** (or SQLite for zero-setup local dev)

### Backend Setup
```bash
cd backend

# Install dependencies (already installed)
composer install

# Environment setup
cp .env.example .env
php artisan key:generate

# Run database migrations
php artisan migrate

# Start local API server
php artisan serve --port=8000
```
Backend API will be running at `http://localhost:8000`.
Health check: `http://localhost:8000/api/v1/health`

### Frontend Setup
```bash
cd frontend

# Install dependencies
pnpm install

# Start development server
pnpm run dev
```
Frontend application will be accessible at `http://localhost:3000`.

---

## 4. Running Tests

### Backend Feature & Unit Tests
```bash
cd backend
php artisan test
```

Current test status:
```text
✓ HealthCheckTest > health check returns ok
✓ ExampleTest > that true is true
✓ ExampleTest > the application returns a successful response
Tests: 3 passed (12 assertions)
```

---

## 5. Docker Environment

To run the full stack with PostgreSQL and Redis via Docker Compose:
```bash
docker compose up -d
```
Services:
- **Frontend**: `http://localhost:3000`
- **Backend**: `http://localhost:8000`
- **PostgreSQL**: `localhost:5432`
- **Redis**: `localhost:6379`

---

## 6. Implementation Roadmap

- [x] **Phase 1: Project Foundation & Local Scaffolding**
  - [x] Laravel 11 backend with Sanctum & API v1 structure
  - [x] Nuxt 3 frontend with Tailwind CSS & Pinia
  - [x] Verified `/api/v1/health` endpoint and Feature test
  - [x] Docker Compose definition & system architecture docs
  - [x] Branding standardized to NextOmni Finance
- [ ] **Phase 2: Authentication & User Profile Management**
- [ ] **Phase 3: Financial Accounts & Institutions**
- [ ] **Phase 4: Categories & Transaction Domain**
- [ ] **Phase 5: CSV Statement Import & Normalization Pipeline**
- [ ] **Phase 6: Financial Dashboard & Visualizations**
- [ ] **Phase 7: Budgets & Overspending Alerts**
- [ ] **Phase 8: Financial Goals & Recurring Transactions**
- [ ] **Phase 9: Analytics & Reporting Engine**
- [ ] **Phase 10: Connector Architecture & Extensibility**
- [ ] **Phase 11: Fact-Grounded AI Capabilities**
- [ ] **Phase 12: Production Hardening & Documentation**
