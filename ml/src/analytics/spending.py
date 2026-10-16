"""
Deterministic Financial Analytics Engine.

Calculates exact financial aggregations, category breakdowns, monthly cash flows,
and behavioral spending distributions without relying on heuristic approximations.
"""

from __future__ import annotations

from typing import Any
import pandas as pd


def compute_overall_summary(df: pd.DataFrame) -> dict[str, Any]:
    """
    Compute comprehensive high-level financial summary metrics.

    Parameters
    ----------
    df : pd.DataFrame
        Cleaned transaction DataFrame conforming to canonical schema.

    Returns
    -------
    dict[str, Any]
        Dictionary of financial totals, ratios, and descriptive statistics.
    """
    if df.empty:
        return {
            "total_income": 0.0,
            "total_expense": 0.0,
            "net_savings": 0.0,
            "savings_rate_pct": 0.0,
            "total_transactions": 0,
            "expense_count": 0,
            "income_count": 0,
            "expense_mean": 0.0,
            "expense_median": 0.0,
            "expense_std": 0.0,
            "expense_min": 0.0,
            "expense_max": 0.0,
            "start_date": None,
            "end_date": None,
        }

    income_mask = df["type"] == "income"
    expense_mask = df["type"] == "expense"

    total_income = round(float(df.loc[income_mask, "amount"].sum()), 2)
    total_expense = round(float(df.loc[expense_mask, "amount"].sum()), 2)
    net_savings = round(total_income - total_expense, 2)
    savings_rate = (
        round((net_savings / total_income) * 100, 2) if total_income > 0 else 0.0
    )

    expense_amounts = df.loc[expense_mask, "amount"]
    dates = pd.to_datetime(df["transaction_date"])

    return {
        "total_income": total_income,
        "total_expense": total_expense,
        "net_savings": net_savings,
        "savings_rate_pct": savings_rate,
        "total_transactions": len(df),
        "expense_count": int(expense_mask.sum()),
        "income_count": int(income_mask.sum()),
        "expense_mean": round(float(expense_amounts.mean()), 2) if not expense_amounts.empty else 0.0,
        "expense_median": round(float(expense_amounts.median()), 2) if not expense_amounts.empty else 0.0,
        "expense_std": round(float(expense_amounts.std()), 2) if len(expense_amounts) > 1 else 0.0,
        "expense_min": round(float(expense_amounts.min()), 2) if not expense_amounts.empty else 0.0,
        "expense_max": round(float(expense_amounts.max()), 2) if not expense_amounts.empty else 0.0,
        "start_date": dates.min().strftime("%Y-%m-%d"),
        "end_date": dates.max().strftime("%Y-%m-%d"),
    }


def compute_monthly_cashflow(df: pd.DataFrame) -> pd.DataFrame:
    """
    Aggregate transactions by calendar month (YYYY-MM) for cash-flow tracking.

    Returns a DataFrame with columns:
    ['period', 'income', 'expense', 'net_savings', 'savings_rate_pct', 'expense_count']
    """
    if df.empty:
        return pd.DataFrame(
            columns=["period", "income", "expense", "net_savings", "savings_rate_pct", "expense_count"]
        )

    work_df = df.copy()
    work_df["period"] = pd.to_datetime(work_df["transaction_date"]).dt.to_period("M").astype(str)

    periods = sorted(work_df["period"].unique())
    rows: list[dict[str, Any]] = []

    for period in periods:
        p_df = work_df[work_df["period"] == period]
        inc = round(float(p_df.loc[p_df["type"] == "income", "amount"].sum()), 2)
        exp = round(float(p_df.loc[p_df["type"] == "expense", "amount"].sum()), 2)
        net = round(inc - exp, 2)
        rate = round((net / inc) * 100, 2) if inc > 0 else 0.0
        exp_count = int((p_df["type"] == "expense").sum())

        rows.append({
            "period": period,
            "income": inc,
            "expense": exp,
            "net_savings": net,
            "savings_rate_pct": rate,
            "expense_count": exp_count,
        })

    return pd.DataFrame(rows)


def compute_category_distribution(df: pd.DataFrame) -> pd.DataFrame:
    """
    Calculate spending concentration across categories, sorted descending by volume.

    Returns a DataFrame with columns:
    ['category', 'total_amount', 'percentage', 'count', 'mean_amount', 'median_amount']
    """
    expense_df = df[df["type"] == "expense"].copy()
    if expense_df.empty:
        return pd.DataFrame(
            columns=["category", "total_amount", "percentage", "count", "mean_amount", "median_amount"]
        )

    total_expense = expense_df["amount"].sum()

    grouped = (
        expense_df.groupby("category")
        .agg(
            total_amount=("amount", "sum"),
            count=("amount", "count"),
            mean_amount=("amount", "mean"),
            median_amount=("amount", "median"),
        )
        .reset_index()
    )

    grouped["total_amount"] = grouped["total_amount"].round(2)
    grouped["mean_amount"] = grouped["mean_amount"].round(2)
    grouped["median_amount"] = grouped["median_amount"].round(2)
    grouped["percentage"] = (
        (grouped["total_amount"] / total_expense) * 100
    ).round(2) if total_expense > 0 else 0.0

    return grouped.sort_values(by="total_amount", ascending=False).reset_index(drop=True)


def compute_day_of_week_metrics(df: pd.DataFrame) -> pd.DataFrame:
    """
    Analyze spending volume and frequency grouped by day of week (Monday through Sunday).

    Returns a DataFrame with columns:
    ['day_name', 'day_index', 'total_amount', 'percentage', 'count', 'average_per_transaction']
    """
    expense_df = df[df["type"] == "expense"].copy()
    if expense_df.empty:
        return pd.DataFrame(
            columns=["day_name", "day_index", "total_amount", "percentage", "count", "average_per_transaction"]
        )

    dates = pd.to_datetime(expense_df["transaction_date"])
    expense_df["day_index"] = dates.dt.dayofweek
    expense_df["day_name"] = dates.dt.day_name()

    day_order = [
        "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"
    ]
    total_expense = expense_df["amount"].sum()

    grouped = (
        expense_df.groupby(["day_index", "day_name"])
        .agg(
            total_amount=("amount", "sum"),
            count=("amount", "count"),
            average_per_transaction=("amount", "mean"),
        )
        .reset_index()
    )

    grouped["total_amount"] = grouped["total_amount"].round(2)
    grouped["average_per_transaction"] = grouped["average_per_transaction"].round(2)
    grouped["percentage"] = (
        (grouped["total_amount"] / total_expense) * 100
    ).round(2) if total_expense > 0 else 0.0

    # Ensure all 7 days exist even if no spend
    indexed = grouped.set_index("day_name").reindex(day_order).reset_index()
    indexed["day_index"] = range(7)
    indexed["total_amount"] = indexed["total_amount"].fillna(0.0)
    indexed["percentage"] = indexed["percentage"].fillna(0.0)
    indexed["count"] = indexed["count"].fillna(0).astype(int)
    indexed["average_per_transaction"] = indexed["average_per_transaction"].fillna(0.0)

    return indexed


def compute_top_merchants(df: pd.DataFrame, top_n: int = 10) -> pd.DataFrame:
    """
    Identify highest volume merchants and their transaction counts.
    """
    expense_df = df[df["type"] == "expense"].copy()
    if expense_df.empty:
        return pd.DataFrame(columns=["merchant", "total_amount", "count", "average_spend"])

    total_expense = expense_df["amount"].sum()

    grouped = (
        expense_df.groupby("merchant")
        .agg(
            total_amount=("amount", "sum"),
            count=("amount", "count"),
            average_spend=("amount", "mean"),
        )
        .reset_index()
    )

    grouped["total_amount"] = grouped["total_amount"].round(2)
    grouped["average_spend"] = grouped["average_spend"].round(2)
    grouped["percentage"] = (
        (grouped["total_amount"] / total_expense) * 100
    ).round(2) if total_expense > 0 else 0.0

    return grouped.sort_values(by="total_amount", ascending=False).head(top_n).reset_index(drop=True)
