"""
Unit Tests for Deterministic Financial Analytics Module.
"""

from __future__ import annotations

import pandas as pd
import pytest

from src.analytics.spending import (
    compute_overall_summary,
    compute_monthly_cashflow,
    compute_category_distribution,
    compute_day_of_week_metrics,
    compute_top_merchants,
)


@pytest.fixture
def mock_transactions_df() -> pd.DataFrame:
    """Fixture providing a deterministic small dataset for verifying mathematical aggregates."""
    return pd.DataFrame([
        # Month 1: Jan 2025
        {"transaction_date": "2025-01-01", "description": "Rent", "merchant": "Landlord", "amount": 1000.00, "type": "expense", "category": "rent"},
        {"transaction_date": "2025-01-05", "description": "Groceries", "merchant": "Tesco", "amount": 200.00, "type": "expense", "category": "groceries"},
        {"transaction_date": "2025-01-10", "description": "Coffee", "merchant": "Starbucks", "amount": 20.00, "type": "expense", "category": "dining"},
        {"transaction_date": "2025-01-25", "description": "Salary", "merchant": "Employer", "amount": 4000.00, "type": "income", "category": "salary"},

        # Month 2: Feb 2025
        {"transaction_date": "2025-02-01", "description": "Rent", "merchant": "Landlord", "amount": 1000.00, "type": "expense", "category": "rent"},
        {"transaction_date": "2025-02-08", "description": "Groceries", "merchant": "Tesco", "amount": 300.00, "type": "expense", "category": "groceries"},
        {"transaction_date": "2025-02-25", "description": "Salary", "merchant": "Employer", "amount": 4000.00, "type": "income", "category": "salary"},
    ])


def test_compute_overall_summary_math(mock_transactions_df: pd.DataFrame):
    """Verify total income, expense, net savings, and descriptive statistics."""
    summary = compute_overall_summary(mock_transactions_df)

    assert summary["total_income"] == 8000.00
    assert summary["total_expense"] == 2520.00
    assert summary["net_savings"] == 5480.00
    # Savings rate: (5480 / 8000) * 100 = 68.5%
    assert summary["savings_rate_pct"] == 68.5
    assert summary["total_transactions"] == 7
    assert summary["income_count"] == 2
    assert summary["expense_count"] == 5

    # Expense stats: [1000, 200, 20, 1000, 300]
    # Mean = 2520 / 5 = 504.0
    # Median = 300.0
    assert summary["expense_mean"] == 504.00
    assert summary["expense_median"] == 300.00
    assert summary["expense_min"] == 20.00
    assert summary["expense_max"] == 1000.00


def test_compute_monthly_cashflow_grouping(mock_transactions_df: pd.DataFrame):
    """Verify monthly cash flow produces exact rows for Jan and Feb 2025."""
    monthly = compute_monthly_cashflow(mock_transactions_df)

    assert len(monthly) == 2
    jan = monthly[monthly["period"] == "2025-01"].iloc[0]
    feb = monthly[monthly["period"] == "2025-02"].iloc[0]

    assert jan["income"] == 4000.00
    assert jan["expense"] == 1220.00
    assert jan["net_savings"] == 2780.00
    assert jan["expense_count"] == 3

    assert feb["income"] == 4000.00
    assert feb["expense"] == 1300.00
    assert feb["net_savings"] == 2700.00
    assert feb["expense_count"] == 2


def test_compute_category_distribution_shares(mock_transactions_df: pd.DataFrame):
    """Verify category totals, ordering, and that percentages sum to 100%."""
    cat_df = compute_category_distribution(mock_transactions_df)

    # Categories: rent (2000), groceries (500), dining (20)
    assert len(cat_df) == 3
    assert cat_df.iloc[0]["category"] == "rent"
    assert cat_df.iloc[0]["total_amount"] == 2000.00

    assert cat_df.iloc[1]["category"] == "groceries"
    assert cat_df.iloc[1]["total_amount"] == 500.00

    assert cat_df.iloc[2]["category"] == "dining"
    assert cat_df.iloc[2]["total_amount"] == 20.00

    # Total percentage must equal 100% (+/- rounding)
    assert abs(cat_df["percentage"].sum() - 100.0) < 0.1


def test_compute_day_of_week_structure(mock_transactions_df: pd.DataFrame):
    """Verify 7-day structure and ordering."""
    dow_df = compute_day_of_week_metrics(mock_transactions_df)

    assert len(dow_df) == 7
    assert dow_df["day_name"].tolist() == [
        "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"
    ]
    # Total percentage must equal 100% (+/- rounding)
    assert abs(dow_df["percentage"].sum() - 100.0) < 0.1


def test_compute_top_merchants_ranking(mock_transactions_df: pd.DataFrame):
    """Verify merchant ranking by total volume."""
    merchants = compute_top_merchants(mock_transactions_df, top_n=2)

    assert len(merchants) == 2
    assert merchants.iloc[0]["merchant"] == "Landlord"
    assert merchants.iloc[0]["total_amount"] == 2000.00
    assert merchants.iloc[1]["merchant"] == "Tesco"
    assert merchants.iloc[1]["total_amount"] == 500.00
