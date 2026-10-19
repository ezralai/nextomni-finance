# Architecture & Technical Design
## Personal Finance Intelligence & Spending Forecasting System

---

## 1. High-Level System Architecture

```
                                      [User Interface]
                              Streamlit Multi-Tab Dashboard
                                  (app.py / localhost:8501)
                                             │
                       ┌─────────────────────┴─────────────────────┐
                       ▼                                           ▼
            [Interactive Analytics]                     [Model Arena & Forecasts]
         - Cash-Flow Dynamics                        - Naive Prior Month Baseline
         - Pareto Category Analysis                  - 3M Moving Average Baseline
         - Day-of-Week Velocity                      - Ridge Regularized Regression
         - Recurring Cadence Stability               - Isolation Forest Anomaly
                       │                                           │
                       └─────────────────────┬─────────────────────┘
                                             │
                                   [Feature Engineering]
                               (src/features/engineering.py)
                           - Temporal Lag Encodings (t-1, t-2, t-3)
                           - Rolling Mean Windows (Zero Leakage)
                           - Calendar Dimensionality Features
                                             │
                                     [Data Foundation]
                              - Canonical Schema Validation
                              - Deterministic SHA-256 Deduplication
                              - Type Normalization & Sanitization
                                             │
                                     [Raw Data Sources]
                           - Preloaded 18M Synthetic Ledger (MYR)
                           - Untrusted User Bank Statement CSVs
```

---

## 2. Core Subsystem Boundaries

The project enforces strict modularity across isolated directories:

### 1. `src/data/` (Data Extraction, Validation & Cleaning)
- **Responsibility**: Takes raw CSV streams or sample data files and produces a canonical Pandas DataFrame.
- **Invariants**:
  - Never mutates data in place without recording validation logs.
  - Generates a structured `ValidationReport` accounting for total rows received, valid rows, duplicates, and invalid records.
  - Treats monetary values as positive absolute scalars with standardized direction enums (`income`, `expense`, `transfer`).

### 2. `src/analytics/` (Deterministic Financial Analytics)
- **Responsibility**: Computes verifiable mathematical summaries without using machine learning where statistical aggregation is appropriate.
- **Components**:
  - `spending.py`: Total inflow/outflow, savings rates, Pareto distribution across living categories, weekend-to-weekday spending velocity.
  - `recurring.py`: Detects subscriptions and scheduled bills by clustering merchant cadences (monthly, weekly) and analyzing coefficient of variation (CV).

### 3. `src/features/` (Time-Series Feature Engineering)
- **Responsibility**: Transforms event-driven transaction logs into uniform monthly aggregated features for forecasting.
- **Invariants**:
  - Strict data leakage prevention: Lag features and rolling statistics are calculated exclusively from historical periods ($t-1, t-2, \dots$).
  - Warmup handling: Explicitly strips `NaN` rows resulting from historical shift operations.

### 4. `src/models/` (Baselines, Regressors & Anomaly Detectors)
- **Responsibility**: Trains, compares, and evaluates predictive algorithms against heuristic baselines using chronological validation.
- **Components**:
  - `baselines.py`: Naive Prior Month, 3-Month Moving Average, Historical Expanding Mean.
  - `forecasting.py`: Scikit-Learn Linear Regression and Ridge Regression with L2 regularization.
  - `category_forecasting.py`: Per-category time-series regression.
  - `anomaly_detection.py`: Hybrid statistical IQR envelope and Scikit-Learn Isolation Forest.
  - `evaluation.py`: Chronological train/test splitting, walk-forward validation, and standard metric computations (MAE, RMSE, MAPE).

### 5. `tests/` (Automated Pytest Quality Assurance)
- **Responsibility**: Guarantees system correctness across 33+ unit tests with deterministic fixtures.

---

## 3. Data Flow & Leakage Prevention Pipeline

```
[Raw Event Records] (Irregular Date Grid)
       │
       ▼
[Monthly Aggregation] ──> Total Expense Series: [Y_1, Y_2, ..., Y_t]
       │
       ▼
[Feature Shifts] ──> Lag 1 = Y_(t-1), Lag 2 = Y_(t-2), Lag 3 = Y_(t-3)
       │
       ▼
[Rolling Summary] ──> Rolling 3M = Mean(Y_(t-1), Y_(t-2), Y_(t-3))
       │
       ▼
[Split Boundary] ──> Train: [Months 4 to 12]  |  Test: [Months 13 to 18]
       │
       ▼
[Model Fitting] ──> Fitted strictly on Train period (No lookahead)
       │
       ▼
[Out-of-Sample Eval] ──> Forecast Months 13-18 and compute MAE / RMSE
```

---

## 4. Security & Privacy Model

1. **Non-Custodial Design**: Does not store or request online banking credentials or passwords.
2. **Untrusted Input Sanitation**: Uploaded CSV files are subjected to strict byte limits (10 MB ceiling) and row caps (100,000 rows) before parsing.
3. **No External Telemetry**: Financial figures and transactions are never transmitted to third-party language models or external APIs without explicit user action.
4. **Data Privacy**: Raw statement uploads are kept in volatile memory or local ignored storage, never committed to version control.
