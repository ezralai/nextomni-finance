"""
Automated Generator for Jupyter Notebook 04: Anomaly Detection & Recurring Subscription Intelligence.

Generates: ml/notebooks/04_anomaly_detection.ipynb
A beginner-friendly, educational notebook designed for the Personal Finance
Intelligence & Spending Forecasting portfolio project.
"""

from pathlib import Path
import nbformat as nbf


def build_anomaly_detection_notebook(output_path: str = "notebooks/04_anomaly_detection.ipynb") -> Path:
    nb = nbf.v4.new_notebook()
    cells = []

    # Title & Overview
    cells.append(
        nbf.v4.new_markdown_cell(
            """# Notebook 04: Dual-Engine Anomaly Detection & Subscription Intelligence
**Personal Finance Intelligence & Spending Forecasting System**

---

### Objectives for this Notebook:
1. **Understand Financial Anomalies**: Learn the difference between statistical outliers, life events, and potential billing errors.
2. **Rule-Based Detection (Tukey's IQR)**: Apply category-specific non-parametric thresholding to detect extreme spikes.
3. **Machine Learning Anomaly Detection (Isolation Forest)**: Train an unsupervised ensemble of isolation trees across multidimensional transaction vectors.
4. **Compare Methods**: Contrast univariate statistical fences vs. multivariate tree isolation.
5. **Detect Recurring Subscriptions**: Automatically discover recurring commitments (Rent, Utilities, Subscriptions) using cadence and coefficient-of-variation ($CV$).
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
from src.models.anomaly_detection import detect_statistical_anomalies, IsolationForestAnomalyDetector
from src.analytics.recurring import detect_recurring_expenses

# Set clean styling for high-quality portfolio figures
plt.style.use("seaborn-v0_8-whitegrid" if "seaborn-v0_8-whitegrid" in plt.style.available else "default")
plt.rcParams["figure.dpi"] = 120
plt.rcParams["axes.titlesize"] = 13
plt.rcParams["axes.titleweight"] = "bold"

print("Libraries imported successfully!")
"""
        )
    )

    # Step 1: Ingestion & Exploration
    cells.append(
        nbf.v4.new_markdown_cell(
            """---
## Step 1: Loading Clean Data & Inspecting the Expense Distribution

In personal finance, transaction amounts are rarely normally distributed (Gaussian). Instead, they exhibit a heavy **log-normal** right tail: thousands of small daily purchases (RM 10 – RM 40 for food or transit) mixed with occasional large expenses (RM 1,500 for rent or flights).
"""
        )
    )

    cells.append(
        nbf.v4.new_code_cell(
            """# Load standardized transaction ledger
data_path = root_dir / "data" / "synthetic" / "transactions_sample.csv"
clean_df, report = load_transaction_csv(data_path)

expense_df = clean_df[clean_df['type'] == 'expense'].copy()
print(f"Total expense transactions: {len(expense_df):,}")
print(f"Median expense amount: MYR {expense_df['amount'].median():,.2f}")
print(f"95th percentile expense amount: MYR {expense_df['amount'].quantile(0.95):,.2f}")
print(f"Maximum expense amount: MYR {expense_df['amount'].max():,.2f}")
"""
        )
    )

    cells.append(
        nbf.v4.new_code_cell(
            """# Visualize Raw Amount vs Log-Transformed Distribution
fig, axes = plt.subplots(1, 2, figsize=(12, 4.5))

# Plot 1: Raw Right-Skewed Distribution
axes[0].hist(expense_df['amount'], bins=40, color='#0284c7', edgecolor='black', alpha=0.7)
axes[0].set_title("Raw Expense Distribution (Heavy Right Tail)")
axes[0].set_xlabel("Amount (MYR)")
axes[0].set_ylabel("Frequency")

# Plot 2: Log1p Transformed (Gaussian-like)
axes[1].hist(np.log1p(expense_df['amount']), bins=40, color='#059669', edgecolor='black', alpha=0.7)
axes[1].set_title("Log-Transformed Distribution: log(1 + Amount)")
axes[1].set_xlabel("log1p(Amount)")
axes[1].set_ylabel("Frequency")

plt.tight_layout()
plt.show()
"""
        )
    )

    # Step 2: Statistical Anomaly Detection (IQR)
    cells.append(
        nbf.v4.new_markdown_cell(
            """---
## Step 2: Engine 1 — Rule-Based Tukey's IQR Anomaly Detection

### Why Global Thresholds Fail:
A single global cutoff (e.g. "flag anything over RM 500") fails in real-world finance:
- **RM 500 for Groceries / Electronics**: Completely ordinary.
- **RM 500 for Coffee / Fast Food**: Highly suspicious or a major data entry error.

Therefore, we apply Tukey's non-parametric Interquartile Range per spending category:
$$\\text{Upper Fence} = Q_3 + (k \\times \\text{IQR})$$
Where $\\text{IQR} = Q_3 - Q_1$ and $k=2.0$ (Tukey's extreme boundary).
"""
        )
    )

    cells.append(
        nbf.v4.new_code_cell(
            """# Execute Category-Level Statistical Outlier Detection
iqr_anomalies = detect_statistical_anomalies(clean_df, iqr_multiplier=2.0)
print(f"Total statistical anomalies detected: {len(iqr_anomalies)}")

iqr_anomalies[['transaction_date', 'description', 'category', 'amount', 'category_median', 'deviation_ratio', 'human_review_status']]
"""
        )
    )

    cells.append(
        nbf.v4.new_code_cell(
            """# Visualizing Category Boxplots with Flagged Outliers
fig, ax = plt.subplots(figsize=(11, 5))

# Top 6 categories by transaction count for clear visualization
top_cats = expense_df['category'].value_counts().head(6).index
filtered_df = expense_df[expense_df['category'].isin(top_cats)]

sns.boxplot(data=filtered_df, x='category', y='amount', ax=ax, palette='Blues', flierprops=dict(marker='o', markersize=4, alpha=0.5))
ax.set_title("Expense Distribution by Category (Tukey Boxplots)")
ax.set_ylabel("Amount (MYR)")
ax.set_xlabel("Spending Category")
plt.tight_layout()
plt.show()
"""
        )
    )

    # Step 3: Isolation Forest
    cells.append(
        nbf.v4.new_markdown_cell(
            """---
## Step 3: Engine 2 — Multidimensional Isolation Forest (Unsupervised ML)

### How Isolation Forest Works:
Standard clustering or density algorithms (like DBSCAN) calculate pairwise distances, which scales poorly.
**Isolation Forest** isolates anomalies instead of profiling normal points:
1. It builds an ensemble of completely randomized decision trees.
2. Anomalies are few and topologically isolated in feature space, so they require **fewer random splits** to isolate near the root of the tree.
3. The average path length across all trees determines the **Anomaly Score**: shorter path = more anomalous.

### Features Used:
- `log_amount`: Magnitude of expense.
- `day_of_week`: Temporal cadence (e.g., weekend spending vs. weekday business expenses).
- `category_encoded`: Categorical spending domain.
"""
        )
    )

    cells.append(
        nbf.v4.new_code_cell(
            """# Train Multidimensional Isolation Forest with 2% Contamination
iso_engine = IsolationForestAnomalyDetector(contamination=0.02, random_state=42)
iso_anomalies = iso_engine.fit_predict(clean_df)

print(f"Total Isolation Forest anomalies flagged: {len(iso_anomalies)}")
iso_anomalies[['transaction_date', 'description', 'category', 'amount', 'anomaly_score', 'flag_reason']].head(8)
"""
        )
    )

    cells.append(
        nbf.v4.new_code_cell(
            """# Visualizing Multi-Dimensional Isolation Forest Space
dates = pd.to_datetime(expense_df['transaction_date'])
expense_df['day_of_week'] = dates.dt.dayofweek
expense_df['day_name'] = dates.dt.day_name()

# Map flagged transaction IDs
flagged_ids = set(iso_anomalies['transaction_id'])
expense_df['is_anomaly'] = expense_df['transaction_id'].isin(flagged_ids)

fig, ax = plt.subplots(figsize=(10, 5))
inliers = expense_df[~expense_df['is_anomaly']]
anomalies = expense_df[expense_df['is_anomaly']]

ax.scatter(inliers['day_of_week'], inliers['amount'], alpha=0.35, color='#94a3b8', label="Normal Transactions (Inliers)", s=30)
ax.scatter(anomalies['day_of_week'], anomalies['amount'], color='#e11d48', s=90, edgecolors='black', linewidth=1, label="Flagged Anomalies", zorder=5)

ax.set_xticks(range(7))
ax.set_xticklabels(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'])
ax.set_title("Isolation Forest Anomaly Map: Amount vs. Day of Week")
ax.set_ylabel("Transaction Amount (MYR)")
ax.set_xlabel("Day of Week")
ax.legend(frameon=True, facecolor="white", edgecolor="#e2e8f0")
plt.tight_layout()
plt.show()
"""
        )
    )

    # Step 4: Comparison Analysis
    cells.append(
        nbf.v4.new_markdown_cell(
            """---
## Step 4: Comparative Analysis — Statistical IQR vs. Isolation Forest

| Dimension | Tukey's IQR (Parametric / Fence) | Isolation Forest (ML Ensemble) |
| :--- | :--- | :--- |
| **Dimensionality** | 1D (per category amount) | Multivariate (Amount, Day, Category) |
| **Interpretability** | 100% transparent ($k \\times \\text{IQR}$) | Score-based (tree path length) |
| **Catch Potential** | Catches extreme volume/amount spikes | Catches rare combinations (e.g. unusual day + rare category) |
| **Production Role** | Immediate deterministic sanity check | Exploratory high-dimensional monitoring |
"""
        )
    )

    cells.append(
        nbf.v4.new_code_cell(
            """# Check Overlap Between Engines
iqr_ids = set(iqr_anomalies['transaction_id'])
iso_ids = set(iso_anomalies['transaction_id'])

overlap = iqr_ids.intersection(iso_ids)
print(f"Transactions flagged by BOTH IQR and Isolation Forest: {len(overlap)}")
for tid in overlap:
    row = clean_df[clean_df['transaction_id'] == tid].iloc[0]
    print(f" -> [{tid}] {row['transaction_date']} | {row['description']} | MYR {row['amount']:,.2f}")
"""
        )
    )

    # Step 5: Recurring Subscriptions
    cells.append(
        nbf.v4.new_markdown_cell(
            """---
## Step 5: Recurring Expense & Subscription Intelligence

Subscription fatigue is one of the most common personal finance challenges. Users often lose track of recurring gym memberships, cloud storage, streaming services, and utility bills.

### Mathematical Detection Rules:
1. **Temporal Regularity**: Day intervals between consecutive charges must have a low standard deviation:
   $$\\sigma_{\\Delta t} \\le 6.0 \\text{ days}$$
2. **Amount Consistency**:
   $$\\text{Coefficient of Variation (CV)} = \\frac{\\sigma_{\\text{amount}}}{\\mu_{\\text{amount}}}$$
   - **Fixed Recurring**: $CV \\le 0.05$ (Exact flat rate, e.g. Rent, Netflix).
   - **Variable Recurring**: $0.05 < CV \\le 0.25$ (Metered usage, e.g. Water/Electricity).
"""
        )
    )

    cells.append(
        nbf.v4.new_code_cell(
            """# Execute Recurring Subscription Detection
recurring_df = detect_recurring_expenses(clean_df)
print(f"Total recurring obligations discovered: {len(recurring_df)}")

recurring_df[['merchant', 'category', 'cadence', 'cost_type', 'monthly_equivalent', 'last_billed_date', 'next_expected_date']]
"""
        )
    )

    cells.append(
        nbf.v4.new_code_cell(
            """# Visualizing Recurring Monthly Budget Commitments
fig, ax = plt.subplots(figsize=(8, 4))

bars = ax.barh(recurring_df['merchant'], recurring_df['monthly_equivalent'], color='#6366f1', height=0.5)
ax.set_title("Discovered Recurring Monthly Obligations (MYR / Month)")
ax.set_xlabel("Monthly Equivalent Cost (MYR)")

# Add cost labels to each bar
for bar in bars:
    width = bar.get_width()
    ax.text(width + 20, bar.get_y() + bar.get_height()/2, f"MYR {width:,.2f}", 
            va='center', ha='left', fontweight='bold', fontsize=10, color='#1e293b')

plt.tight_layout()
plt.show()
"""
        )
    )

    # Step 6: Summary
    cells.append(
        nbf.v4.new_markdown_cell(
            """---
## Key Takeaways & Interview Discussion Points:

1. **Why Financial Outliers Require Human Verification**:
   In fraud detection, anomalies may trigger automated transaction blocking. In personal finance management, however, flagging a vacation flight as "fraud" causes user frustration. We mark anomalies with `Requires Review` to provide contextual transparency.

2. **Multidimensional vs. Univariate Trade-offs**:
   Univariate IQR provides explainable fences that non-technical users can understand instantly. Isolation Forest complements this by uncovering complex multivariate patterns that slip past 1D thresholds.

3. **Production Utility of Subscription Intelligence**:
   Detecting cadence and next expected billing dates allows the forecasting model to pre-populate fixed baseline expenses for future months, significantly reducing forecast variance.
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
    out = build_anomaly_detection_notebook()
    print(f"Successfully generated Anomaly Detection Notebook at: {out.resolve()}")
