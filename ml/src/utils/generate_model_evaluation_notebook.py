"""
Automated Generator for Jupyter Notebook 03: Model Evaluation & Residual Diagnostics.

Generates: ml/notebooks/03_model_evaluation.ipynb
A beginner-friendly, educational notebook designed for the Personal Finance
Intelligence & Spending Forecasting portfolio project.
"""

from pathlib import Path
import nbformat as nbf


def build_model_evaluation_notebook(output_path: str = "notebooks/03_model_evaluation.ipynb") -> Path:
    nb = nbf.v4.new_notebook()
    cells = []

    # Title & Overview
    cells.append(
        nbf.v4.new_markdown_cell(
            """# Notebook 03: Model Evaluation, Baseline Benchmarking & Residual Diagnostics
**Personal Finance Intelligence & Spending Forecasting System**

---

### Objectives for this Notebook:
1. **Understand Time Series Evaluation**: Learn why standard K-Fold cross validation is forbidden in time series and why Chronological Holdouts are mandatory.
2. **Benchmark Heuristic Baselines vs Machine Learning**: Compare simple rules (Naive Prior Month, 3M Moving Average) against Ridge Regression.
3. **Inspect Feature Coefficients**: Interpret standardized regression weights ($\beta$) to understand what drives model predictions.
4. **Conduct Residual Diagnostics**: Analyze prediction errors ($e_t = Y_t - \\hat{Y}_t$) to check for systematic bias and non-constant variance.
5. **Simulate Production with Walk-Forward Validation**: Test expanding-window re-training month by month.
"""
        )
    )

    # Step 0: Environment Setup
    cells.append(
        nbf.v4.new_code_cell(
            """import sys
from pathlib import Path

# Add project root to sys.path
root_dir = Path.cwd().parent if Path.cwd().name == "notebooks" else Path.cwd()
if str(root_dir) not in sys.path:
    sys.path.insert(0, str(root_dir))

import pandas as pd
import numpy as np
import matplotlib.pyplot as plt
import seaborn as sns

from src.data.loader import load_transaction_csv
from src.features.engineering import build_monthly_spending_series
from src.models.evaluation import (
    prepare_modeling_dataset,
    train_test_split_chronological,
    benchmark_all_models,
    run_walk_forward_validation,
)
from src.models.forecasting import ExpenseRegressionForecaster

# Set clean styling for high-quality portfolio figures
plt.style.use("seaborn-v0_8-whitegrid" if "seaborn-v0_8-whitegrid" in plt.style.available else "default")
plt.rcParams["figure.dpi"] = 120
plt.rcParams["axes.titlesize"] = 13
plt.rcParams["axes.titleweight"] = "bold"

print("Libraries imported successfully!")
"""
        )
    )

    # Step 1: Data Preparation
    cells.append(
        nbf.v4.new_markdown_cell(
            """---
## Step 1: Loading Data and Preparing Chronological Modeling Set

We load our standardized transaction ledger, aggregate into monthly periods with leakage-free lag features, and split chronologically reserving the final 6 months as an out-of-sample holdout test set.
"""
        )
    )

    cells.append(
        nbf.v4.new_code_cell(
            """# Ingest clean transactions and engineer features
data_path = root_dir / "data" / "synthetic" / "transactions_sample.csv"
clean_df, report = load_transaction_csv(data_path)

# Aggregate monthly time series with lags (warmup filtered)
monthly_df = build_monthly_spending_series(clean_df)
modeling_df = prepare_modeling_dataset(monthly_df)

# Chronological Split: 10 months training, 6 months out-of-sample holdout
train_df, test_df = train_test_split_chronological(modeling_df, test_months=6)

print(f"Total usable monthly periods: {len(modeling_df)}")
print(f"Train period: {train_df['period'].iloc[0]} to {train_df['period'].iloc[-1]} ({len(train_df)} months)")
print(f"Test period:  {test_df['period'].iloc[0]} to {test_df['period'].iloc[-1]} ({len(test_df)} months)")
"""
        )
    )

    # Step 2: Multi-Model Benchmark
    cells.append(
        nbf.v4.new_markdown_cell(
            """---
## Step 2: Multi-Model Benchmark (Baselines vs Machine Learning)

### Why Compare Against Heuristic Baselines?
In real-world data science, a complex machine learning model has **zero value** unless it can consistently beat simple, zero-cost heuristic baselines:
1. **Naive Prior Month**: Assumes next month's spending equals last month's spending ($Y_t = Y_{t-1}$).
2. **3-Month Moving Average**: Assumes next month's spending equals the mean of the past 3 months.
3. **Expanding Mean**: Assumes next month's spending equals the lifetime average spending.

Let's execute `benchmark_all_models` to score all approaches on the exact same holdout period.
"""
        )
    )

    cells.append(
        nbf.v4.new_code_cell(
            """summary_df, predictions_df = benchmark_all_models(train_df, test_df)

print("=== OUT-OF-SAMPLE BENCHMARK RESULTS (Ranked by MAE) ===")
summary_df
"""
        )
    )

    cells.append(
        nbf.v4.new_code_cell(
            """# Visualizing Out-of-Sample Predictions vs Actual Spending
fig, ax = plt.subplots(figsize=(11, 5))

# Plot actual holdout spending
ax.plot(predictions_df['period'], predictions_df['actual_expense'], 
        marker='o', color='#0f172a', linewidth=3, label="Actual Ground Truth", zorder=5)

# Plot key models
model_colors = {
    'pred_Baseline: Naive Prior Month': ('#94a3b8', '--', 'Naive Prior Month'),
    'pred_Baseline: 3M Moving Average': ('#f59e0b', '-.', '3M Moving Average'),
    'pred_ML: Ridge Regression (α=1.0)': ('#0284c7', '-', 'Ridge Regression (α=1.0)'),
}

for col, (color, ls, label) in model_colors.items():
    if col in predictions_df.columns:
        ax.plot(predictions_df['period'], predictions_df[col], 
                marker='s', color=color, linestyle=ls, linewidth=2, label=label)

ax.set_title("Out-of-Sample Test Period: Predictions vs. Actual Living Expenses")
ax.set_ylabel("Monthly Spending (MYR)")
ax.legend(frameon=True, facecolor="white", edgecolor="#e2e8f0")
plt.xticks(rotation=30)
plt.tight_layout()
plt.show()
"""
        )
    )

    # Step 3: Coefficient Inspection
    cells.append(
        nbf.v4.new_markdown_cell(
            """---
## Step 3: Model Interpretability & Feature Weights

Because our model uses `Ridge` regression with standardized features (`StandardScaler`), the resulting regression coefficients ($\beta$) are directly comparable in magnitude.

- **Positive $\\beta$**: As this feature increases, predicted spending increases.
- **Negative $\\beta$**: As this feature increases, predicted spending decreases.
- **Magnitude $|\\beta|$**: The relative importance of the feature.
"""
        )
    )

    cells.append(
        nbf.v4.new_code_cell(
            """# Train a Ridge regressor and inspect coefficients
ridge_model = ExpenseRegressionForecaster(model_type="ridge", alpha=1.0, scale_features=True)
ridge_model.fit(train_df, train_df["actual_expense"])

# Extract coefficients into a clean dataframe
feature_importance = pd.DataFrame({
    "Feature": ridge_model.feature_cols,
    "Standardized_Coefficient": ridge_model.model.coef_,
    "Absolute_Importance": np.abs(ridge_model.model.coef_)
}).sort_values(by="Absolute_Importance", ascending=True)

feature_importance
"""
        )
    )

    cells.append(
        nbf.v4.new_code_cell(
            """# Plot standardized feature coefficients
fig, ax = plt.subplots(figsize=(8, 4))
colors = ['#10b981' if c > 0 else '#f43f5e' for c in feature_importance['Standardized_Coefficient']]

bars = ax.barh(feature_importance['Feature'], feature_importance['Standardized_Coefficient'], color=colors, height=0.55)
ax.axvline(0, color='#64748b', linestyle='--', linewidth=0.8)
ax.set_title("Standardized Ridge Regression Coefficients (α=1.0)")
ax.set_xlabel("Impact on Predicted Monthly Expense (Standardized)")
plt.tight_layout()
plt.show()
"""
        )
    )

    # Step 4: Residual Diagnostics
    cells.append(
        nbf.v4.new_markdown_cell(
            """---
## Step 4: Residual Diagnostics & Error Analysis

A regression model is only trustworthy if its residuals:
$$e_t = Y_t - \\hat{Y}_t$$
satisfy foundational statistical properties:
1. **Zero Mean**: The model should not exhibit systematic over-forecasting ($e < 0$) or under-forecasting ($e > 0$).
2. **No Autocorrelation**: A prediction error in month $t$ should not predict the error in month $t+1$.
3. **Homoscedasticity**: Error variance should remain stable regardless of spending magnitude.
"""
        )
    )

    cells.append(
        nbf.v4.new_code_cell(
            """# Calculate residuals for Ridge Regression on the test set
y_actual = test_df["actual_expense"].to_numpy()
y_pred_ridge = ridge_model.predict(test_df)
residuals = y_actual - y_pred_ridge

diag_df = pd.DataFrame({
    "period": test_df["period"],
    "actual": y_actual,
    "predicted": y_pred_ridge,
    "residual": residuals,
    "abs_residual": np.abs(residuals),
    "pct_error": (residuals / y_actual) * 100
})

print(f"Mean Residual (Bias): {residuals.mean():.2f} MYR")
print(f"Median Absolute Residual: {np.median(np.abs(residuals)):.2f} MYR")
diag_df
"""
        )
    )

    cells.append(
        nbf.v4.new_code_cell(
            """# Multi-panel Residual Diagnostic Plots
fig, axes = plt.subplots(1, 2, figsize=(12, 4.5))

# Plot 1: Residuals over Time
axes[0].axhline(0, color='#ef4444', linestyle='--', linewidth=1.2)
axes[0].plot(diag_df['period'], diag_df['residual'], marker='o', color='#0284c7', linewidth=2)
axes[0].set_title("Residuals Over Time (Error Trajectory)")
axes[0].set_ylabel("Error: Actual - Predicted (MYR)")
axes[0].tick_params(axis='x', rotation=30)

# Plot 2: Residuals vs Fitted Values (Homoscedasticity Check)
axes[1].axhline(0, color='#ef4444', linestyle='--', linewidth=1.2)
axes[1].scatter(diag_df['predicted'], diag_df['residual'], color='#8b5cf6', s=60, edgecolors='black', linewidth=0.5)
axes[1].set_title("Residuals vs. Fitted Values")
axes[1].set_xlabel("Fitted Expense (MYR)")
axes[1].set_ylabel("Residual (MYR)")

plt.tight_layout()
plt.show()
"""
        )
    )

    # Step 5: Walk-Forward Validation
    cells.append(
        nbf.v4.new_markdown_cell(
            """---
## Step 5: Walk-Forward Expanding Window Validation

In real life, we don't make a single static 6-month prediction and stop. Each month, the user logs new transactions, expanding our training dataset.

**Walk-Forward Validation** mimics this continuous monthly cycle:
- At month $t$, train on all available history up to $t-1$.
- Forecast month $t$.
- At month $t+1$, incorporate month $t$'s actual data into the training set and repeat.
"""
        )
    )

    cells.append(
        nbf.v4.new_code_cell(
            """wf_results = run_walk_forward_validation(modeling_df, min_train_months=8)

print(f"Walk-forward evaluated across {len(wf_results)} sequential months:")
wf_results[['period', 'actual', 'pred_ridge', 'pred_naive', 'abs_error_ridge', 'abs_error_naive']]
"""
        )
    )

    cells.append(
        nbf.v4.new_code_cell(
            """# Compare Cumulative Absolute Error in Walk-Forward Simulation
wf_results['cum_error_ridge'] = wf_results['abs_error_ridge'].cumsum()
wf_results['cum_error_naive'] = wf_results['abs_error_naive'].cumsum()

fig, ax = plt.subplots(figsize=(9, 4.5))
ax.plot(wf_results['period'], wf_results['cum_error_ridge'], marker='o', color='#0284c7', linewidth=2.5, label="Ridge Regression (α=1.0)")
ax.plot(wf_results['period'], wf_results['cum_error_naive'], marker='s', color='#f43f5e', linewidth=2.5, linestyle='--', label="Naive Prior Month")

ax.set_title("Cumulative Absolute Forecasting Error Over Time")
ax.set_ylabel("Cumulative Error (MYR)")
ax.legend(frameon=True, facecolor="white", edgecolor="#e2e8f0")
plt.xticks(rotation=30)
plt.tight_layout()
plt.show()
"""
        )
    )

    # Step 6: Interview Summary
    cells.append(
        nbf.v4.new_markdown_cell(
            """---
## Key Takeaways & Interview Discussion Points:

1. **Occam's Razor in Small-Sample Forecasting**:
   When training data spans only 10 to 18 monthly periods, complex non-linear models (e.g. Deep Learning, XGBoost) suffer from high variance and degrees-of-freedom collapse. Heuristics like **Moving Average** and constrained **L2 Ridge Regression** consistently achieve lower out-of-sample error.

2. **Why K-Fold Cross-Validation Fails in Time Series**:
   K-Fold randomly shuffles observations, allowing future data to leak into past predictions. Chronological splitting or walk-forward validation is the only scientifically valid method for temporal data.

3. **Interpretable Coefficients**:
   Standardized Ridge regression reveals the exact statistical influence of each historical lag and calendar property on future spending, providing financial explainability without black-box opacity.
"""
        )
    )

    nb.cells = cells
    output_file = Path(output_path)
    output_file.parent.mkdir(parents=True, exist_ok=True)
    with open(output_file, "w", encoding="utf-8") as f:
        nbf.write(nb, f)

    return output_file


if __name__ == "__main__":
    out = build_model_evaluation_notebook()
    print(f"Successfully generated Model Evaluation Notebook at: {out.resolve()}")
