"""
Generator for Phase 3 Feature Engineering & Time-Series Preparation Jupyter Notebook.
Uses nbformat to construct an interactive, professionally annotated educational notebook.
"""

from pathlib import Path
import nbformat as nbf


def build_feature_engineering_notebook(
    output_path: Path | str = "notebooks/02_feature_engineering.ipynb",
) -> Path:
    nb = nbf.v4.new_notebook()

    cells = []

    # Title & Objectives
    cells.append(
        nbf.v4.new_markdown_cell(
            """# Phase 3: Time-Series Feature Engineering & Leakage Prevention
## Personal Finance Intelligence & Spending Forecasting

### Learning Objectives:
1. **From Transactions to Time-Series**: Transform event-driven transaction logs into a uniform monthly aggregate series.
2. **Understand Data Leakage**: Learn why standard `train_test_split(shuffle=True)` creates fake predictive accuracy and fails in production.
3. **Construct Temporal Lag Features**: Use Pandas `.shift()` to let the model look back 1, 2, and 3 months into history.
4. **Build Moving Average Windows**: Compute rolling 3-month statistics to capture short-term spending momentum without lookahead bias.
5. **Inspect Autocorrelation**: Calculate and plot autocorrelation to discover whether last month's spending actually predicts this month.
6. **Chronological Splitting**: Establish clean train and holdout evaluation periods.
"""
        )
    )

    # Imports & Setup
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
from src.models.evaluation import prepare_modeling_dataset, train_test_split_chronological

# Set visual styling for modern plots
plt.style.use("seaborn-v0_8-whitegrid" if "seaborn-v0_8-whitegrid" in plt.style.available else "default")
plt.rcParams["figure.dpi"] = 120
plt.rcParams["axes.titlesize"] = 13
plt.rcParams["axes.titleweight"] = "bold"

print("Libraries imported successfully!")
"""
        )
    )

    # Step 1: Loading Clean Data
    cells.append(
        nbf.v4.new_markdown_cell(
            """---
## Step 1: Loading & Inspecting Clean Transactions

Before engineering features, we load the standardized ledger generated during our data pipeline phase.
"""
        )
    )

    cells.append(
        nbf.v4.new_code_cell(
            """# Resolve path to sample data
data_path = root_dir / "data" / "synthetic" / "transactions_sample.csv"
clean_df, report = load_transaction_csv(data_path)

print(f"Total transactions loaded: {len(clean_df):,}")
print(f"Date range: {clean_df['transaction_date'].min().date()} to {clean_df['transaction_date'].max().date()}")
clean_df[['transaction_date', 'description', 'category', 'amount', 'type']].head()
"""
        )
    )

    # Step 2: Temporal Aggregation & Initial Inspection
    cells.append(
        nbf.v4.new_markdown_cell(
            """---
## Step 2: Aggregating Event Logs into Uniform Monthly Periods

Individual transactions occur irregularly. Machine learning models require regular intervals (e.g. Monthly) to forecast future time horizons.
Here, we group transactions by calendar month (`YYYY-MM`) and compute actual living expenses alongside initial time features.
"""
        )
    )

    cells.append(
        nbf.v4.new_code_cell(
            """# build_monthly_spending_series aggregates transactions and generates zero-leakage lag features
monthly_df = build_monthly_spending_series(clean_df)
print(f"Total historical monthly observations: {len(monthly_df)}")
monthly_df[['period', 'actual_expense', 'actual_income', 'days_in_month', 'lag_1', 'lag_2', 'lag_3', 'rolling_3m_mean']].head(6)
"""
        )
    )

    cells.append(
        nbf.v4.new_code_cell(
            """# Plot monthly spending trajectory
fig, ax = plt.subplots(figsize=(10, 4.5))
ax.plot(monthly_df['period'], monthly_df['actual_expense'], marker='o', color='#0284c7', linewidth=2.5, markersize=6)
ax.fill_between(monthly_df['period'], monthly_df['actual_expense'], color='#0284c7', alpha=0.15)
ax.set_title("Monthly Total Living Expenses (MYR)")
ax.set_ylabel("Total Spent (MYR)")
plt.xticks(rotation=45)
plt.tight_layout()
plt.show()
"""
        )
    )

    # Step 3: Lag Feature Engineering
    cells.append(
        nbf.v4.new_markdown_cell(
            """---
## Step 3: Engineering Temporal Lag Features & Understanding Warmup

### What is a Lag Feature?
A lag feature is simply past spending shifted forward in time:
- `lag_1`: What the user spent 1 month ago ($Y_{t-1}$)
- `lag_2`: What the user spent 2 months ago ($Y_{t-2}$)
- `lag_3`: What the user spent 3 months ago ($Y_{t-3}$)

### The Golden Rule of Time Series:
When predicting month $t$, you **must never** use information from month $t$ or later! Pandas `.shift(1)` ensures that the feature for month $t$ is strictly taken from month $t-1$.

### Notice the `NaN` Values in the First 3 Rows!
Because `lag_3` looks back 3 months, the first 3 rows cannot have full lag information.
This is called the **Warmup Period**. `prepare_modeling_dataset()` removes these initial incomplete rows.
"""
        )
    )

    cells.append(
        nbf.v4.new_code_cell(
            """clean_modeling_df = prepare_modeling_dataset(monthly_df)
print(f"Original monthly rows: {len(monthly_df)}")
print(f"Usable modeling observations after warmup: {len(clean_modeling_df)}")
clean_modeling_df[['period', 'actual_expense', 'lag_1', 'lag_2', 'lag_3', 'rolling_3m_mean']].head()
"""
        )
    )

    # Step 4: Correlation & Autocorrelation
    cells.append(
        nbf.v4.new_markdown_cell(
            """---
## Step 4: Correlation & Autocorrelation Analysis

Does historical spending actually correlate with future spending?
Let's compute the Pearson correlation matrix between our target (`actual_expense`) and our engineered lag features.
"""
        )
    )

    cells.append(
        nbf.v4.new_code_cell(
            """feature_cols = ['actual_expense', 'lag_1', 'lag_2', 'lag_3', 'rolling_3m_mean', 'days_in_month']
corr = clean_modeling_df[feature_cols].corr()

fig, ax = plt.subplots(figsize=(7, 5.5))
sns.heatmap(corr, annot=True, cmap="Blues", fmt=".2f", vmin=-1, vmax=1, ax=ax, cbar=True)
ax.set_title("Feature Correlation Matrix with Monthly Spending")
plt.tight_layout()
plt.show()
"""
        )
    )

    # Step 5: Chronological Train/Test Split
    cells.append(
        nbf.v4.new_markdown_cell(
            """---
## Step 5: Chronological Train/Test Split (Zero Leakage)

Unlike cross-sectional tabular data where random shuffling is standard, time series data **must be split chronologically**.
Here, we reserve the last 6 months as an unseen out-of-sample holdout test set to evaluate real-world forecasting accuracy.
"""
        )
    )

    cells.append(
        nbf.v4.new_code_cell(
            """train_df, test_df = train_test_split_chronological(clean_modeling_df, test_months=6)

print(f"Training observations: {len(train_df)} months ({train_df['period'].iloc[0]} to {train_df['period'].iloc[-1]})")
print(f"Testing observations:  {len(test_df)} months ({test_df['period'].iloc[0]} to {test_df['period'].iloc[-1]})")
"""
        )
    )

    cells.append(
        nbf.v4.new_code_cell(
            """# Visualizing the Temporal Split Boundary
fig, ax = plt.subplots(figsize=(10, 4.5))
ax.plot(train_df['period'], train_df['actual_expense'], marker='o', label="Training Set (History)", color='#059669', linewidth=2.5)
ax.plot(test_df['period'], test_df['actual_expense'], marker='s', label="Test Set (Unseen Future)", color='#e11d48', linewidth=2.5)
ax.axvline(x=len(train_df) - 0.5, color='#64748b', linestyle='--', label="Split Boundary")
ax.set_title("Chronological Train / Test Split Boundary")
ax.set_ylabel("Monthly Expense (MYR)")
ax.legend()
plt.xticks(rotation=45)
plt.tight_layout()
plt.show()
"""
        )
    )

    # Step 6: Summary & Next Steps
    cells.append(
        nbf.v4.new_markdown_cell(
            """---
## Summary of Key Takeaways:
1. **Aggregation**: Aggregating irregular transaction logs into a monthly calendar grid creates stationary time-series targets.
2. **Leakage Protection**: We applied `.shift(1)` so features only contain information available prior to the forecast date.
3. **Warmup Cost**: Using 3 lags consumes 3 initial historical observations.
4. **Chronological Splitting**: We split at month $T-6$, preparing our pipeline for Phase 4 (Baseline Benchmarking & Ridge Regression).
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
    out = build_feature_engineering_notebook()
    print(f"Successfully generated Feature Engineering Notebook at: {out.resolve()}")
