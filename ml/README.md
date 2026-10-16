# NextOmni Personal Finance Intelligence & Spending Forecasting

An extensible, reproducible Data Science and Machine Learning module for personal finance analytics, time-series spending forecasting, and unusual transaction detection.

---

## Architecture Overview

```
ml/
├── data/
│   ├── synthetic/       # Labeled multi-month synthetic transaction datasets
│   └── raw/             # User-uploaded bank statement CSVs (git-ignored)
├── docs/                # Architecture notes & data dictionaries
├── notebooks/           # Interactive Jupyter notebooks for EDA and learning
├── src/
│   ├── data/            # Ingestion, schema validation, deduplication, cleaning
│   ├── analytics/       # Cash-flow velocity, category distributions
│   ├── features/        # Time-series lag features, calendar encodings
│   └── models/          # Baselines, regression forecasters, anomaly detectors
└── tests/               # Pytest automated test suite
```

---

## Setup & Quickstart

### 1. Activate Virtual Environment
```bash
cd ml
source .venv/bin/activate
```

### 2. Generate Synthetic Dataset (18 Months)
```bash
python -m src.data.synthetic
```

### 3. Run Automated Tests (25 Passing Tests)
```bash
pytest -v tests/
```

### 4. Launch Interactive Streamlit Application
```bash
streamlit run app.py
```
*(Runs on `http://localhost:8501` with tabs for Data Ingestion Audit, Visual Analytics, Spending Forecaster Arena, and Statistical Anomaly Detection).*

---

## Canonical Data Pipeline Flow

```
[Raw CSV File] ──> [loader.py] ──> [validation.py] ──> [ValidationReport]
                                           │                   │
                                           ▼                   ▼
                                    [cleaning.py]      [Audit of Excluded Rows]
                                           │
                                           ▼
                                 [Canonical DataFrame]
```
