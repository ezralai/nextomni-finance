"""
Hierarchical Category-Level Spending Forecasting Engine.

Decomposes personal expenditure across individual spending categories to project
month t+1 category-level allocations and budget utilization risks.

Guiding Principle:
Spending behavior varies drastically by category:
- Fixed Inelastic Outgoings (Rent, Utilities, Subscriptions): Highly predictable baseline.
- Variable Discretionary Outgoings (Groceries, Dining, Shopping): Fluctuate with recent trends.

Forecasting Methodology:
- Computes monthly category matrices strictly over past observed periods.
- Projects each category via rolling 3-month moving averages (or historical mean fallback).
- Evaluates projected spending against user-defined or default target budgets to flag overrun risks.
"""

from __future__ import annotations

from typing import Any
import numpy as np
import pandas as pd

from src.analytics.recurring import detect_recurring_expenses


def build_monthly_category_matrix(df: pd.DataFrame) -> pd.DataFrame:
    """
    Construct a chronological pivot matrix of monthly expenses per category.

    Parameters
    ----------
    df : pd.DataFrame
        Cleaned transaction DataFrame.

    Returns
    -------
    pd.DataFrame
        Index: 'period' (YYYY-MM), Columns: category slugs, Values: monthly spend.
    """
    if df.empty:
        return pd.DataFrame()

    expense_df = df[df["type"] == "expense"].copy()
    if expense_df.empty:
        return pd.DataFrame()

    expense_df["period"] = pd.to_datetime(expense_df["transaction_date"]).dt.to_period("M").astype(str)

    pivot = (
        expense_df.groupby(["period", "category"])["amount"]
        .sum()
        .unstack(fill_value=0.0)
        .sort_index()
    )

    return pivot.round(2)


def forecast_category_expenses(
    df: pd.DataFrame,
    target_budgets: dict[str, float] | None = None,
    rolling_window: int = 3,
) -> tuple[pd.DataFrame, dict[str, Any]]:
    """
    Forecast next month's spending across each category and analyze budget utilization.

    Parameters
    ----------
    df : pd.DataFrame
        Cleaned transaction DataFrame.
    target_budgets : dict[str, float], optional
        Optional dictionary mapping category name to target monthly budget (e.g. {'groceries': 1200.0}).
    rolling_window : int, default 3
        Number of recent months to average for discretionary projection.

    Returns
    -------
    tuple of (pd.DataFrame, dict[str, Any])
        (category_forecast_table, summary_kpis)
    """
    if df.empty:
        return pd.DataFrame(), {
            "total_projected_expense": 0.0,
            "total_budget": 0.0,
            "overall_budget_variance": 0.0,
            "categories_at_risk_count": 0,
            "forecast_period": None,
        }

    matrix = build_monthly_category_matrix(df)
    if matrix.empty:
        return pd.DataFrame(), {
            "total_projected_expense": 0.0,
            "total_budget": 0.0,
            "overall_budget_variance": 0.0,
            "categories_at_risk_count": 0,
            "forecast_period": None,
        }

    budgets = target_budgets or {}
    total_months = len(matrix)
    last_period_str = str(matrix.index[-1])
    last_period_dt = pd.to_datetime(last_period_str)
    next_period_dt = last_period_dt + pd.DateOffset(months=1)
    next_period_str = next_period_dt.strftime("%Y-%m")

    # Detect known recurring commitments to anchor fixed categories
    recurring_df = detect_recurring_expenses(df)
    recurring_by_cat: dict[str, float] = {}
    if not recurring_df.empty:
        recurring_by_cat = (
            recurring_df.groupby("category")["monthly_equivalent"]
            .sum()
            .to_dict()
        )

    records: list[dict[str, Any]] = []

    for category in matrix.columns:
        series = matrix[category].to_numpy(dtype=float)
        last_month_actual = float(series[-1])
        hist_mean = float(np.mean(series))
        hist_std = float(np.std(series)) if len(series) > 1 else 0.0

        # Calculate forecast:
        # Use recurring floor if known fixed category, otherwise rolling window average
        recurring_floor = recurring_by_cat.get(category, 0.0)

        if total_months >= rolling_window:
            rolling_val = float(np.mean(series[-rolling_window:]))
        else:
            rolling_val = hist_mean

        # Forecast is at least the known recurring obligations
        forecast_amt = max(rolling_val, recurring_floor)
        forecast_amt = round(max(forecast_amt, 0.0), 2)

        # Spending Trend direction (comparing last month to 3-month projection)
        if last_month_actual > (rolling_val * 1.05):
            trend = "Surging (+5%)"
        elif last_month_actual < (rolling_val * 0.95):
            trend = "Declining (-5%)"
        else:
            trend = "Stable"

        # Budget Risk Analysis
        cat_budget = budgets.get(category)
        if cat_budget is not None and cat_budget > 0:
            variance = round(forecast_amt - cat_budget, 2)
            utilization = round((forecast_amt / cat_budget) * 100, 1)
            if utilization > 100.0:
                budget_status = "Projected Overrun"
            elif utilization >= 90.0:
                budget_status = "At Risk (90-100%)"
            else:
                budget_status = "Within Budget"
        else:
            cat_budget = None
            variance = None
            utilization = None
            budget_status = "No Budget Set"

        records.append({
            "category": str(category),
            "forecast_amount": forecast_amt,
            "last_month_actual": round(last_month_actual, 2),
            "historical_mean": round(hist_mean, 2),
            "historical_std": round(hist_std, 2),
            "recurring_floor": round(recurring_floor, 2),
            "trend": trend,
            "target_budget": cat_budget,
            "projected_variance": variance,
            "budget_utilization_pct": utilization,
            "budget_status": budget_status,
        })

    forecast_df = pd.DataFrame(records)

    # Compute percentage share of total projected spend
    total_projected = float(forecast_df["forecast_amount"].sum())
    if total_projected > 0:
        forecast_df["share_of_total_pct"] = (
            (forecast_df["forecast_amount"] / total_projected) * 100
        ).round(2)
    else:
        forecast_df["share_of_total_pct"] = 0.0

    # Sort descending by forecast amount
    forecast_df = forecast_df.sort_values(by="forecast_amount", ascending=False).reset_index(drop=True)

    # Compile Summary KPIs
    total_budget_sum = sum(b for b in budgets.values() if b is not None)
    total_var = round(total_projected - total_budget_sum, 2) if total_budget_sum > 0 else 0.0
    at_risk_count = int((forecast_df["budget_status"].isin(["Projected Overrun", "At Risk (90-100%)"])).sum())

    summary_kpis = {
        "total_projected_expense": round(total_projected, 2),
        "total_budget": round(total_budget_sum, 2) if total_budget_sum > 0 else None,
        "overall_budget_variance": total_var if total_budget_sum > 0 else None,
        "categories_at_risk_count": at_risk_count,
        "forecast_period": next_period_str,
        "total_categories": len(forecast_df),
    }

    return forecast_df, summary_kpis
