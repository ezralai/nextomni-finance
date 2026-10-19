# Personal Finance Intelligence & Spending Forecasting System
**NextOmni Financial Intelligence Data Science & Machine Learning Engine**

An end-to-end, production-grade Data Science and Machine Learning project for personal finance analytics, time-series expense forecasting, dual-engine anomaly detection, and recurring subscription intelligence.

---

## Architecture Overview

```
ml/
├── app.py                      # Interactive Streamlit Web Application (Port 8501)
├── data/
│   ├── synthetic/              # Preloaded 18-month Malaysian Ringgit (MYR) sample ledger
│   └── raw/                    # User statement uploads (git-ignored for privacy)
├── docs/
│   ├── architecture.md         # Subsystem architecture, boundaries, & data contracts
│   └── learning_notes.md       # Beginner-friendly guide to Time-Series ML & Statistics
├── notebooks/                  # Reproducible, educational Jupyter Notebooks
│   ├── 01_data_exploration.ipynb     # Phase 2: Exploratory Data Analysis & Cash Flow
│   ├── 02_feature_engineering.ipynb   # Phase 3: Lag Features, Warmup & Leakage Prevention
│   ├── 03_model_evaluation.ipynb     # Phase 4: Baseline Benchmarks, Ridge & Residuals
│   └── 04_anomaly_detection.ipynb    # Phase 5: Dual-Engine IQR & Isolation Forest
├── src/
│   ├── data/                   # Ingestion, schema validation, canonical cleaning
│   ├── analytics/              # Spending summary, cash flow velocity, subscriptions
│   ├── features/               # Monthly time-series aggregation & zero-leakage lags
│   ├── models/                 # Heuristic baselines, Ridge regressors, anomaly detectors
│   └── utils/                  # Automated notebook generators & evaluation metrics
└── tests/                      # Pytest automated test suite (33 passing unit tests)
```

---

## 4 Educational Jupyter Notebooks

This project includes a progressive 4-part notebook curriculum designed for data science interviews and portfolio demonstrations:

| Notebook | Focus Area | Key Concepts & Techniques Taught |
| :--- | :--- | :--- |
| **`01_data_exploration.ipynb`** | **EDA & Cash Flow** | Class imbalance, log-normal expense skew, monthly cash flow velocity, top merchant concentration, and data cleaning audits. |
| **`02_feature_engineering.ipynb`** | **Time Series Features** | Temporal aggregation, zero-leakage `.shift(1)` lag features ($Y_{t-1}, Y_{t-2}, Y_{t-3}$), rolling windows, warmup drop, and chronological train/test splitting. |
| **`03_model_evaluation.ipynb`** | **Model Arena & Residuals** | Why K-Fold CV fails in time series, benchmarking against 3 heuristic baselines (Naive, 3M Moving Average, Expanding Mean), standardized Ridge regression weights ($\beta$), residual homoscedasticity diagnostics, and walk-forward validation. |
| **`04_anomaly_detection.ipynb`** | **Anomalies & Subscriptions** | Tukey's per-category IQR fences vs. unsupervised Multidimensional Isolation Forest (`IsolationForest`), precision/recall trade-offs, cadence regularity ($\sigma_{\Delta t} \le 6$ days), and Coefficient of Variation ($CV$). |

---

## Key Data Science Principles & Interview Talking Points

1. **Time-Series vs. Tabular Cross-Validation**:
   - Random shuffling violates temporal ordering and causes future data leakage.
   - We enforce strict **Chronological Splits** ($T_{\text{train}} \to T_{\text{test}}$) and **Walk-Forward Validation**.
2. **The Degrees of Freedom Gotcha in Small Financial Series**:
   - Personal finance data typically spans only 12 to 24 monthly periods ($N \approx 18$).
   - Complex models (Deep Learning, XGBoost) suffer from severe sample exhaustion and high variance. Simple heuristics and L2-regularized **Ridge Regression** consistently outperform complex models.
3. **The Baseline Rule (Occam's Razor)**:
   - A machine learning model has zero value if it cannot beat a zero-cost heuristic like **Naive Prior Month** or a **3-Month Moving Average**.
4. **Dual-Engine Anomaly Detection**:
   - **Tukey's IQR (1D)**: Explainable per-category bounds ($Q_3 + 2.0 \times \text{IQR}$).
   - **Isolation Forest (Multivariate)**: Unsupervised tree ensemble isolating unusual combinations of amount, day-of-week, and category in fewer decision splits.
   - All flags are labeled **`Requires Review`** to prevent user-alerting fatigue.

---

## Setup & Quickstart

### 1. Activate Python Environment
```bash
cd ml
source .venv/bin/activate
```

### 2. Run the Automated Unit Test Suite (33 Tests)
```bash
pytest -v tests/
```

### 3. Launch the Interactive Streamlit Web Application
```bash
streamlit run app.py
```
*Access `http://localhost:8501` to interact with:*
- **Ingestion & Audit**: Validation reports and excluded row auditing.
- **Visual Analytics**: Interactive Altair charts for cash-flow velocity and category concentration.
- **Forecaster Arena**: Out-of-sample holdout forecasting with Ridge regression and heuristic baselines.
- **Anomaly & Recurring Review**: Dual-engine outlier inspection and automated subscription discovery.

### 4. Regenerate or Re-run Notebooks
```bash
# Generate and verify notebooks
python src/utils/generate_feature_engineering_notebook.py
python src/utils/generate_model_evaluation_notebook.py
python src/utils/generate_anomaly_detection_notebook.py
```

---

## Canonical Data Pipeline Flow

```
[Raw Statement CSV] ──> [loader.py] ──> [validation.py] ──> [ValidationReport]
                                                │                   │
                                                ▼                   ▼
                                         [cleaning.py]      [Audit of Excluded Rows]
                                                │
                                                ▼
                                      [Canonical DataFrame]
                                                │
                     ┌──────────────────────────┴──────────────────────────┐
                     ▼                                                     ▼
           [features/engineering.py]                             [models/anomaly_detection.py]
                     │                                                     │
                     ▼                                                     ▼
           [models/forecasting.py]                                [Dual-Engine Review]
         (Ridge Regressor vs Baselines)                         (IQR + Isolation Forest)
```
