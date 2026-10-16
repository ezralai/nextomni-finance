# Model Evaluation & Benchmark Report: Expense Forecasting

**Project**: NextOmni Personal Finance Intelligence & Spending Forecasting  
**Author / Engineer**: Data Science & ML Core  
**Dataset**: 18-Month Standardized Ledger (`transactions_sample.csv`)  
**Timeline**: January 2025 – June 2026 (18 Aggregate Monthly Periods)  
**Evaluation Methodology**: Fixed Chronological Out-of-Sample Holdout (6 Months Test) & Walk-Forward Validation  

---

## 1. Executive Summary & Core Conclusion

> [!IMPORTANT]
> **Honest Machine Learning Finding**:  
> In personal finance time series with small sample sizes ($N \le 18$), **heuristic baseline models significantly outperform multivariable regression models**.
> - **Best Performing Model**: **3-Month Moving Average Baseline** ($\text{MAE} = \text{MYR } 1,063.61$, $\text{MAPE} = 36.35\%$).
> - **Second Best Model**: **Naive Prior Month Baseline** ($\text{MAE} = \text{MYR } 1,069.00$, $\text{MAPE} = 37.51\%$).
> - **Machine Learning Regressors**: Ordinary Least Squares ($\text{MAE} = \text{MYR } 4,090.72$) and Ridge Regression ($\text{MAE} = \text{MYR } 3,296.44$) suffered from high variance and small-sample coefficient instability.

Rather than hiding this result or selecting an artificially favorable test set, we document the mathematical and statistical reasons for this outcome below. Establishing this benchmark prevents false claims of "AI precision" and provides a reliable default forecaster for the application.

---

## 2. Standardized Out-of-Sample Comparison Table

Evaluated on the exact same 6-month holdout period (**February 2026 – July 2026**):

| Model Category | Model Name | Test Size | MAE (MYR) ↓ | RMSE (MYR) ↓ | MAPE (%) ↓ | Max Error (MYR) |
| :--- | :--- | :---: | :---: | :---: | :---: | :---: |
| **Heuristic Baseline** | **3-Month Moving Average (`rolling_3m`)** | **6** | **1,063.61** | **1,755.77** | **36.35%** | **4,150.93** |
| **Heuristic Baseline** | **Naive Prior Month ($y_{t-1}$)** | 6 | 1,069.00 | 1,840.60 | 37.51% | 4,379.74 |
| **Heuristic Baseline** | Expanding Historical Mean | 6 | 1,509.04 | 2,099.83 | 46.09% | 4,612.24 |
| **Machine Learning** | Ridge Regression ($\alpha = 5.0$) | 6 | 3,296.44 | 3,743.75 | 76.19% | 6,337.98 |
| **Machine Learning** | Ridge Regression ($\alpha = 1.0$) | 6 | 3,845.58 | 4,671.95 | 83.39% | 9,131.72 |
| **Machine Learning** | Linear Regression (OLS, $\alpha = 0.0$) | 6 | 4,090.72 | 5,187.34 | 86.26% | 10,598.06 |

---

## 3. Month-by-Month Detailed Test Ledger

| Period | Actual Expense | Naive Baseline | 3M Moving Avg | Expanding Mean | OLS Linear Reg | Ridge ($\alpha=1.0$) | Ridge ($\alpha=5.0$) |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **2026-02** | MYR 6,072.40 | MYR 6,322.68 | MYR 6,167.94 | MYR 7,263.22 | MYR 16,670.46 | MYR 15,204.12 | MYR 12,410.38 |
| **2026-03** | MYR 5,477.90 | MYR 6,072.40 | MYR 6,238.37 | MYR 7,154.96 | MYR 6,721.68 | MYR 6,962.75 | MYR 7,316.34 |
| **2026-04** | MYR 6,273.44 | MYR 5,477.90 | MYR 5,957.66 | MYR 7,015.21 | MYR 10,221.28 | MYR 9,893.45 | MYR 9,179.27 |
| **2026-05** | MYR 6,374.90 | MYR 6,273.44 | MYR 5,941.25 | MYR 6,958.15 | MYR 7,284.24 | MYR 7,455.67 | MYR 7,664.64 |
| **2026-06** | MYR 6,667.38 | MYR 6,374.90 | MYR 6,042.08 | MYR 6,916.49 | MYR 10,143.97 | MYR 9,831.90 | MYR 9,144.47 |
| **2026-07** | MYR 2,287.64* | MYR 6,667.38 | MYR 6,438.57 | MYR 6,899.88 | MYR 6,656.38 | MYR 6,879.27 | MYR 7,217.21 |

*\*Note: 2026-07 represents a partial / mid-month cycle in the synthetic generation, which tests how forecasters handle cut-off boundaries.*

---

## 4. Deep-Dive: Why Did Machine Learning Underperform the Baselines?

### 1. Degrees of Freedom & The "Curse of Dimensionality" in Small Time Series
- The training split contains 9 usable monthly observations (after dropping initial lag warmup rows).
- Fitting 5 features (`lag_1`, `lag_2`, `lag_3`, `rolling_3m_mean`, `days_in_month`) on only 9 data points leaves only **3 degrees of freedom** ($9 - 5 - 1 = 3$).
- In such small sample spaces, standard OLS regression overfits noise and collinearities between lag features.

### 2. Spurious Calendar Correlation (`days_in_month`)
- Look at the learned coefficients for OLS vs Ridge:
  - OLS `days_in_month` coefficient: **-1,617.29**
  - Ridge ($\alpha=1.0$) `days_in_month` coefficient: **-1,337.02**
  - Ridge ($\alpha=5.0$) `days_in_month` coefficient: **-822.11**
- Because February has 28 days (a negative outlier in standard calendar length), the unregularized model multiplied the large negative coefficient by a standardized negative z-score, causing the February 2026 forecast to skyrocket to **MYR 16,670.46**!
- Ridge regression with higher L2 penalty ($\alpha = 5.0$) shrank the extreme coefficients, reducing February's miss from MYR 16,670 down to MYR 12,410, and cutting MAE by nearly MYR 800.

### 3. Presence of Isolated Discretionary Outliers in History
- In Month 8 (August 2025), a non-recurring hardware upgrade (MYR 7,999.00) occurred.
- Because OLS minimizes squared residuals ($(\text{actual} - \text{pred})^2$), the model aggressively skewed its intercept and lag slopes upward to accommodate that single historical peak.
- In contrast, the **3-Month Moving Average** quickly "flushes" old outliers out of its 3-month window once 90 days elapse, rendering it naturally robust to historical shocks.

---

## 5. Pedagogical Takeaways for Entry-Level Data Science

1. **Always Establish Heuristic Baselines First**:  
   Never train an ML model without comparing it against a simple, explainable persistence rule (e.g. "next month equals last month" or "rolling 3-month average"). If your complex model cannot beat the baseline, deploying it into production degrades user experience.
2. **Beware of Calendar Feature Collinearity**:  
   Calendar features like `days_in_month` must only be introduced when there are multiple annual cycles (36+ months). On 12–18 months of data, a single 28-day February creates artificial statistical leverage.
3. **The Role of Regularization (L2 / Ridge)**:  
   When features outnumber or rival observations, L2 regularization ($\lambda / \alpha$) prevents coefficients from exploding. As demonstrated, increasing $\alpha$ from 0.0 to 5.0 decreased MAE from MYR 4,090.72 to MYR 3,296.44.
4. **Targeting Discretionary vs. Fixed Expenses**:  
   Total monthly spending is composed of deterministic fixed outgoings (Rent: MYR 1,850/mo, Utilities: MYR 304/mo) plus variable living expenses. A better predictive architecture is a **hybrid forecaster**:
   $$\hat{y}_{\text{total}} = \text{Fixed Inelastic Floor} + \hat{y}_{\text{variable (rolling 3m)}}$$

---

## 6. Recommended Production Architecture for the Streamlit App

For the interactive Streamlit user application:
1. **Primary Forecaster**: Default to the **3-Month Moving Average (`rolling_3m`)** as the production recommendation, displaying its observed error band ($\pm \text{MYR } 1,063$).
2. **Model Transparency**: Provide a toggle enabling users and hiring managers to compare the Baseline against Ridge Regression and Linear Regression, accompanied by this transparent explanation of why simpler heuristics win on individual personal finance data.
