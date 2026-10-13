# NextOmni Finance — Production Cloud Deployment & Operations Runbook

> **Important**: This guide contains all essential infrastructure URLs, service dashboards, environment variable templates, and operational runbooks. Keep this document as your primary reference when managing the project from any computer.

---

## 🌐 1. Cloud Services Directory

NextOmni Finance runs on a 100% free, production-grade decoupled architecture:

| Component | Provider | Project / Service Name | URL / Dashboard |
| :--- | :--- | :--- | :--- |
| **Database** | Supabase | `umnnrnjdizwgxhskwpag` (Region: `ap-southeast-1` Singapore) | [Supabase Dashboard](https://supabase.com/dashboard/project/umnnrnjdizwgxhskwpag) |
| **API Backend** | Render | `nextomni-finance` (Docker Web Service) | [Render Dashboard](https://dashboard.render.com)<br/>Live URL: `https://nextomni-finance.onrender.com` |
| **API Docs** | Render / Scalar | Integrated interactive OpenAPI 3.1 documentation | Live Docs: `https://nextomni-finance.onrender.com/docs`<br/>Health: `https://nextomni-finance.onrender.com/api/v1/health` |
| **Frontend Web App** | Vercel | `nextomni-finance` (Nuxt 3 Edge SSR) | [Vercel Dashboard](https://vercel.com) |
| **Source Code** | GitHub | `ezralai/nextomni-finance` | [GitHub Repository](https://github.com/ezralai/nextomni-finance) |
| **Personal Portfolio** | Vercel | `ezralai/portfolio` (`portfolio-ezralai.vercel.app`) | [Portfolio GitHub](https://github.com/ezralai/portfolio) |

---

## 🗄️ 2. Database Configuration (Supabase PostgreSQL 16)

### Connection Parameters
- **Engine**: PostgreSQL 16
- **Region**: Southeast Asia (Singapore / `ap-southeast-1`)
- **Connection Mode**: PgBouncer Transaction Pooler *(Required for serverless/Render)*
- **Host**: `aws-0-ap-southeast-1.pooler.supabase.com`
- **Port**: `6543` *(Use `6543` for Render API; use direct port `5432` only for local desktop tools like TablePlus or DBeaver)*
- **Database Name**: `postgres`
- **Database User**: `postgres.umnnrnjdizwgxhskwpag`
- **SSL Mode**: `require`

### How to Check or Reset Database Password
1. Navigate to: [Supabase Project Settings → Database](https://supabase.com/dashboard/project/umnnrnjdizwgxhskwpag/settings/database).
2. Scroll to the **Database password** section.
3. Click **Reset Database Password** if you ever forget it.
4. *Remember to update `DB_PASSWORD` in your Render Environment Variables whenever you reset it.*

---

## 🚀 3. Backend API Service Configuration (Render)

### Service Setup
- **Service Type**: Web Service
- **Repository**: `https://github.com/ezralai/nextomni-finance`
- **Branch**: `main`
- **Root Directory**: `backend` *(Critical: Leave as `backend` because the repo is a monorepo)*
- **Runtime**: `Docker` *(Render will automatically build `backend/Dockerfile`)*
- **Instance Type**: Free

### Production Environment Variables on Render
Configure these under **Render Dashboard → nextomni-finance → Environment**:

```env
APP_NAME=NextOmni Finance
APP_ENV=production
APP_KEY=base64:u3fNOwmfgofR2otaCrOkIjIc2qpzQpwe6W8ZPJGU3m4=
APP_DEBUG=true
APP_URL=https://nextomni-finance.onrender.com

# Database Connection via Supabase Transaction Pooler (Port 6543)
DB_CONNECTION=pgsql
DB_HOST=aws-0-ap-southeast-1.pooler.supabase.com
DB_PORT=6543
DB_DATABASE=postgres
DB_USERNAME=postgres.umnnrnjdizwgxhskwpag
DB_PASSWORD=[YOUR_SUPABASE_DATABASE_PASSWORD]
DB_SSLMODE=require

# Database-Backed Drivers (Zero Redis Cost)
SESSION_DRIVER=database
SESSION_LIFETIME=120
CACHE_STORE=database
QUEUE_CONNECTION=database

# CORS Configuration (Allows Vercel subdomains and local dev)
CORS_ALLOWED_ORIGINS=https://*.vercel.app,http://localhost:3000
```

### Running Database Migrations & Seeders
Migrations run automatically on container startup via `docker-entrypoint.sh`. 

To run migrations manually or re-seed the demo portfolio at any time:
1. Go to **Render Dashboard** → click **nextomni-finance** → click **Shell** in the left menu.
2. Run:
   ```bash
   php artisan migrate --force && php artisan db:seed --force
   ```

---

## ⚡ 4. Frontend Client Deployment (Vercel)

### 1-Click Import & Setup
1. Log in to [Vercel Dashboard](https://vercel.com) with your GitHub account.
2. Click **Add New...** → **Project** → select `ezralai/nextomni-finance`.
3. In the project setup form, configure:
   - **Root Directory**: Click **Edit** and choose `frontend`. *(Crucial!)*
   - **Framework Preset**: `Nuxt.js` (auto-detected).
   - **Build Command**: `pnpm run build` (auto-detected).
   - **Output Directory**: `.output` (auto-detected).
4. Add the following **Environment Variable**:

| Variable Name | Value | Description |
| :--- | :--- | :--- |
| `NUXT_PUBLIC_API_BASE` | `https://nextomni-finance.onrender.com/api/v1` | Public API endpoint for client requests |

5. Click **Deploy**. Vercel will build and assign a global CDN URL (e.g. `https://nextomni-finance.vercel.app`).

---

## 🔑 5. Preloaded Demo Account Credentials

For hiring recruiters, evaluators, or your own testing:
- **Email**: `demo@nextomni.com`
- **Password**: `NextOmniDemo2026!`
- **Includes**: Preloaded Malaysian banking portfolio (Maybank, CIMB, Touch 'n Go, GrabPay, Wise SGD, IBKR USD), categorized transactions, active budgets, recurring expenses, and financial goals.

---

## 💻 6. Setting Up on a Fresh Computer

When switching to another computer:

### Step 1: Prerequisites
Install on the new machine:
- **Git**
- **PHP 8.3+** with extensions: `pdo_sqlite`, `pdo_pgsql`, `bcmath`, `curl`, `mbstring`
- **Composer 2+**
- **Node.js 22+**
- **pnpm**: `npm install -g pnpm`

### Step 2: Clone the Repository
```bash
git clone https://github.com/ezralai/nextomni-finance.git
cd nextomni-finance
```

### Step 3: Run Backend Locally
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve --port=8000
```
Backend API will be running at `http://localhost:8000`. Test via:
- Health check: `http://localhost:8000/api/v1/health`
- Scalar interactive docs: `http://localhost:8000/docs`

### Step 4: Run Frontend Locally (in another terminal)
```bash
cd frontend
pnpm install
pnpm run dev
```
Frontend Web App will be running at `http://localhost:3000`.

---

## 🛠️ 7. Troubleshooting Cheatsheet

### 1. Render Free Tier Cold Boot (Spin-down)
- **Symptom**: The first API request after 15 minutes of inactivity takes 40–50 seconds to respond.
- **Reason**: Render free tier spins down idle containers.
- **Solution**: Normal free-tier behavior. Subsequent requests respond in <100ms.

### 2. "Undefined table: relation cache does not exist" or "sessions does not exist"
- **Reason**: Database tables have not yet been migrated into Supabase PostgreSQL.
- **Fix**: Open Render Web Service → **Shell** tab → execute:
  ```bash
  php artisan migrate --force && php artisan db:seed --force
  ```

### 3. "The database configuration URL is malformed"
- **Reason**: Special characters in Supabase database password when parsed as a single `DATABASE_URL` string.
- **Fix**: Keep `'url' => null` in `backend/config/database.php` (already implemented) and use individual variables: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.

### 4. Supabase PgBouncer Advisory Lock Error (`--isolated` flag)
- **Reason**: Port `6543` operates in transaction pooling mode which does not support session advisory locks.
- **Fix**: Run `php artisan migrate --force` without the `--isolated` flag (already configured in `backend/docker-entrypoint.sh`).

---

## 💼 8. Portfolio Integration (`ezralai/portfolio`)

To link NextOmni Finance in your personal portfolio website ([portfolio-ezralai.vercel.app](https://portfolio-ezralai.vercel.app)):
Update `src/data/projects.js` in `ezralai/portfolio` with:

```javascript
{
  title: 'NextOmni Finance',
  category: 'Full-Stack Fintech Platform',
  description: 'Enterprise personal finance platform featuring non-custodial Malaysian bank statement ingestion (Maybank, CIMB, TnG, GrabPay), multi-currency precision arithmetic (bcmath), interactive OpenAPI 3.1 Scalar docs, and 100% free cloud deployment topology.',
  tags: ['Laravel 11', 'PHP 8.3', 'Nuxt 3', 'Vue 3', 'PostgreSQL 16', 'Docker', 'Vercel Edge', 'Render'],
  liveUrl: 'https://nextomni-finance.vercel.app', // Update with your live Vercel URL
  docsUrl: 'https://nextomni-finance.onrender.com/docs',
  githubUrl: 'https://github.com/ezralai/nextomni-finance',
  featured: true,
}
```
