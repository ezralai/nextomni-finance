# Phase 2 Exploratory Data Analysis (EDA) Report

**Project**: NextOmni Personal Finance Intelligence & Spending Forecasting  
**Author / Engineer**: Data Science & ML Core  
**Dataset**: 18-Month Standardized Ledger (`transactions_sample.csv`)  
**Timeline**: January 2025 – June 2026 (546 Days)  
**Notebook**: [ml/notebooks/01_data_exploration.ipynb](file:///Users/bluevypc3/projects/ml/notebooks/01_data_exploration.ipynb)

---

## 1. Dataset Hygiene & Health Summary

Before running analytical calculations, the dataset was ingested through the canonical validation pipeline:

- **Total Ingested Records**: 1,193
- **Valid Processed Records**: 1,192
- **Duplicate Records Isolated**: 1 (Detected via composite key `(date, amount, description)`)
- **Invalid Records / Corrupted Dates**: 0
- **Validation Pass Rate**: **99.92%**

---

## 2. High-Level Financial Overview

| Financial Metric | Deterministic Value | Analytical Interpretation |
| :--- | :--- | :--- |
| **Total Inflow (Salary)** | **MYR 122,400.00** | Stable monthly employment income (MYR 6,800/mo credited on 25th). |
| **Total Outflow (Expenses)**| **MYR 70,553.64** | Composite living, fixed obligations, and discretionary expenses. |
| **Cumulative Net Savings** | **MYR 51,846.36** | Positive wealth accumulation over 18 months. |
| **Overall Savings Rate** | **42.36%** | Healthy personal savings rate ($> 40\%$). |
| **Expense Count** | **1,174 debits** | Average of 2.15 expenditure transactions per day. |
| **Mean Expense** | **MYR 60.10** | Arithmetic average pulled upward by rent & electronics. |
| **Median Expense** | **MYR 31.95** | The typical everyday purchase (coffee, meal, toll). |
| **Expense Standard Deviation**| **MYR 284.14** | High variance driven by fixed rent cycles and high-ticket outliers. |

---

## 3. Key Data-Backed Findings

### Finding 1: Extreme Asymmetric Right Skew (Mean vs. Median Disparity)
- **Mathematical Evidence**: The arithmetic mean (MYR 60.10) is **88.1% higher** than the median (MYR 31.95). Over 68% of all transactions are below the mean.
- **Root Cause**: Daily living expenses (dining, groceries, transit) cluster tightly in the MYR 15.00 – MYR 85.00 bracket, while monthly rent (MYR 1,850.00) and occasional hardware upgrades (MYR 7,999.00) form an extreme right-tail skew.
- **ML Engineering Consequence**: Standard Ordinary Least Squares (OLS) models that minimize Mean Squared Error (MSE) will over-index on outliers, pulling daily spending estimates upwards. In Phase 3 and Phase 4, we must evaluate **Mean Absolute Error (MAE)** and consider **L1/L2 regularized Ridge/Huber regression** to prevent predictions from being distorted by non-standard debits.

---

### Finding 2: The Inelastic "Expense Floor" (Fixed vs. Variable Split)
- **Mathematical Evidence**: 
  - Rent (`rent`): **MYR 33,300.00 (47.20% of total spend)**
  - Utilities & Internet (`utilities`): **MYR 5,472.00 (7.76% of total spend)**
  - Fixed recurring floor: **MYR 38,772.00 (54.96% of all expenditure)**
- **Root Cause**: Over half of monthly outgoings are completely deterministic and posted on specific dates (1st, 5th, 15th).
- **ML Engineering Consequence**: An ML model should not be forced to learn deterministic calendar dates via trial and error. The forecasting architecture in Phase 3 should combine a deterministic rule-based component for known recurring obligations with a statistical regression model for variable discretionary spend.

---

### Finding 3: Day-of-Week Behavioral Velocity (The Weekend Surge)
- **Mathematical Evidence**:
  - Weekdays (Mon–Fri): Total variable spend = MYR 17,210.00 (Average ~MYR 3,442 / weekday).
  - Weekends (Sat–Sun): Total variable spend = MYR 12,042.00 (Average ~MYR 6,021 / weekend day).
  - Weekend days represent **28.5% of calendar time**, but capture **41.14% of variable retail/dining volume**.
- **Root Cause**: Weekly grocery shopping is concentrated on Saturday mornings; social dining and entertainment cluster on Friday evening through Sunday.
- **ML Engineering Consequence**: Time-series feature engineering in Phase 3 must incorporate `is_weekend`, `day_of_week`, and `rolling_7d_lag` features to capture this cyclical weekly rhythm.

---

## 4. Visualizations Generated in Notebook

1. **Monthly Cash-Flow Dual-Axis Chart**: Compares income vs. expense bars against the violet savings rate curve over 18 consecutive months.
2. **Category Concentration Pareto Chart**: Horizontal ranking alongside donut share showing Rent (47.2%), Groceries (21.4%), Dining (13.1%), and Shopping (9.5%).
3. **Day-of-Week Velocity Bar Chart**: Contrasts Monday–Friday muted volume against Saturday/Sunday green spikes.
4. **Distribution Histogram & Boxplot**: Clearly illustrates the dense cluster under MYR 100 and isolates the 3 injected anomalies (`TXN-ANOMALY-01` at MYR 7,999.00 and `TXN-ANOMALY-02` at MYR 1,650.00).

---

## 5. Dataset Limitations

1. **Synthetic Generation**: The current dataset models an urban professional with a single salaried income source. Freelancers or multi-income households would exhibit higher income volatility.
2. **Sample Granularity**: 18 months (~546 days) yields 18 monthly aggregate points. While sufficient for baseline regression and monthly moving averages, multi-year models (such as ARIMA or seasonal decomposition across multiple annual cycles) benefit from 36+ months.
