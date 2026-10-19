"""
Automated Recurring Expense & Subscription Detection Engine.

Identifies recurring financial commitments (e.g., rent, utilities, streaming subscriptions,
telecommunications, gym memberships) using temporal cadence analysis and amount variance.

Cadence Classification:
- Monthly: 25 - 35 day intervals (e.g. Rent, Netflix, Internet)
- Bi-Weekly: 12 - 16 day intervals
- Weekly: 6 - 8 day intervals

Cost Classification:
- Fixed Recurring: Coefficient of Variation (CV) <= 0.05 (exact same amount every cycle)
- Variable Recurring: 0.05 < CV <= 0.25 (e.g. metered electricity, water bills)
"""

from __future__ import annotations

from typing import Any
from datetime import timedelta
import numpy as np
import pandas as pd


def detect_recurring_expenses(
    df: pd.DataFrame,
    min_occurrences: int = 3,
    max_interval_std_days: float = 6.0,
) -> pd.DataFrame:
    """
    Scan transactions to isolate recurring subscriptions and scheduled bills.

    Parameters
    ----------
    df : pd.DataFrame
        Cleaned transaction DataFrame.
    min_occurrences : int, default 3
        Minimum transactions required before evaluating recurrence.
    max_interval_std_days : float, default 6.0
        Maximum standard deviation of day intervals between postings.

    Returns
    -------
    pd.DataFrame
        Detected recurring obligations sorted descending by monthly cost.
    """
    if df.empty:
        return pd.DataFrame()

    expense_df = df[df["type"] == "expense"].copy()
    if expense_df.empty:
        return pd.DataFrame()

    expense_df["date_dt"] = pd.to_datetime(expense_df["transaction_date"])

    # Determine grouping column: merchant preferred, fallback to description
    group_col = "merchant" if "merchant" in expense_df.columns and expense_df["merchant"].notna().any() else "description"

    recurring_records: list[dict[str, Any]] = []

    for name, group in expense_df.groupby(group_col):
        if len(group) < min_occurrences:
            continue

        sorted_group = group.sort_values(by="date_dt").reset_index(drop=True)
        dates = sorted_group["date_dt"]

        # 1. Compute day differences between consecutive postings
        diffs = dates.diff().dropna().dt.days.to_numpy(dtype=float)
        if len(diffs) == 0:
            continue

        median_interval = float(np.median(diffs))
        std_interval = float(np.std(diffs)) if len(diffs) > 1 else 0.0

        # Enforce temporal regularity
        if std_interval > max_interval_std_days:
            continue

        # 2. Determine Cadence
        cadence = None
        multiplier_to_monthly = 1.0

        if 25 <= median_interval <= 35:
            cadence = "Monthly"
            multiplier_to_monthly = 1.0
        elif 12 <= median_interval <= 16:
            cadence = "Bi-Weekly"
            multiplier_to_monthly = 26.0 / 12.0
        elif 6 <= median_interval <= 8:
            cadence = "Weekly"
            multiplier_to_monthly = 52.0 / 12.0
        elif 80 <= median_interval <= 100:
            cadence = "Quarterly"
            multiplier_to_monthly = 1.0 / 3.0
        elif 340 <= median_interval <= 380:
            cadence = "Annual"
            multiplier_to_monthly = 1.0 / 12.0
        else:
            # Irregular interval cadence
            continue

        # 3. Amount Stability & Variance Analysis
        amounts = sorted_group["amount"].to_numpy(dtype=float)
        mean_amount = float(np.mean(amounts))
        std_amount = float(np.std(amounts)) if len(amounts) > 1 else 0.0
        cv_amount = (std_amount / mean_amount) if mean_amount > 0 else 0.0

        if cv_amount <= 0.05:
            cost_type = "Fixed Amount"
        elif cv_amount <= 0.25:
            cost_type = "Variable Metered"
        else:
            # High amount fluctuation: skip unless strictly regular
            continue

        # 4. Predict Next Expected Billing Date
        last_date = dates.iloc[-1]
        next_date = last_date + timedelta(days=int(round(median_interval)))

        category = sorted_group["category"].mode().iloc[0] if not sorted_group["category"].empty else "general"
        monthly_cost = round(mean_amount * multiplier_to_monthly, 2)
        annual_cost = round(monthly_cost * 12.0, 2)

        recurring_records.append({
            "merchant": str(name),
            "category": str(category),
            "cadence": cadence,
            "cost_type": cost_type,
            "recurrence_count": len(sorted_group),
            "average_amount": round(mean_amount, 2),
            "monthly_equivalent": monthly_cost,
            "annual_projected": annual_cost,
            "interval_median_days": round(median_interval, 1),
            "interval_std_days": round(std_interval, 1),
            "amount_cv": round(cv_amount, 3),
            "last_billed_date": last_date.strftime("%Y-%m-%d"),
            "next_expected_date": next_date.strftime("%Y-%m-%d"),
        })

    if not recurring_records:
        return pd.DataFrame(
            columns=[
                "merchant",
                "category",
                "cadence",
                "cost_type",
                "recurrence_count",
                "average_amount",
                "monthly_equivalent",
                "annual_projected",
                "interval_median_days",
                "interval_std_days",
                "amount_cv",
                "last_billed_date",
                "next_expected_date",
            ]
        )

    res_df = pd.DataFrame(recurring_records)
    return res_df.sort_values(by="monthly_equivalent", ascending=False).reset_index(drop=True)


def compute_recurring_summary(recurring_df: pd.DataFrame) -> dict[str, Any]:
    """
    Calculate portfolio-level recurring obligation statistics.

    Parameters
    ----------
    recurring_df : pd.DataFrame
        Output from detect_recurring_expenses.

    Returns
    -------
    dict[str, Any]
        Summary KPIs of total recurring burden and fixed vs variable breakdown.
    """
    if recurring_df.empty:
        return {
            "total_commitments": 0,
            "total_monthly_recurring": 0.0,
            "total_annual_recurring": 0.0,
            "fixed_commitments_count": 0,
            "variable_commitments_count": 0,
            "fixed_share_percent": 0.0,
        }

    total_monthly = round(float(recurring_df["monthly_equivalent"].sum()), 2)
    total_annual = round(float(recurring_df["annual_projected"].sum()), 2)

    fixed_mask = recurring_df["cost_type"] == "Fixed Amount"
    fixed_count = int(fixed_mask.sum())
    variable_count = len(recurring_df) - fixed_count

    fixed_monthly_sum = float(recurring_df.loc[fixed_mask, "monthly_equivalent"].sum())
    fixed_share = round((fixed_monthly_sum / total_monthly) * 100, 2) if total_monthly > 0 else 0.0

    return {
        "total_commitments": len(recurring_df),
        "total_monthly_recurring": total_monthly,
        "total_annual_recurring": total_annual,
        "fixed_commitments_count": fixed_count,
        "variable_commitments_count": variable_count,
        "fixed_share_percent": fixed_share,
    }
