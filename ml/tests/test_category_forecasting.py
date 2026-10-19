"""
Unit Tests for Hierarchical Category-Level Spending Forecaster.
"""

from __future__ import annotations

import pandas as pd
import pytest

from src.models.category_forecasting import (
    build_monthly_category_matrix,
    forecast_category_expenses,
)


@pytest.fixture
def multi_month_category_ledger() -> pd.DataFrame:
    """Fixture providing 4 consecutive months with distinct category spending behaviors."""
    rows = []
    # 4 consecutive months: 2025-01, 2025-02, 2025-03, 2025-04
    for i in range(1, 5):
        period_str = f"2025-0{i}"
        # Rent: Fixed MYR 1,000 every month
        rows.append({
            "transaction_date": f"{period_str}-01",
            "merchant": "Apartment Landlord",
            "description": "Monthly Rent",
            "amount": 1000.0,
            "type": "expense",
            "category": "rent",
        })
        # Groceries: 500 in m1, 550 in m2, 600 in m3, 650 in m4 (Increasing)
        rows.append({
            "transaction_date": f"{period_str}-15",
            "merchant": "Jaya Supermarket",
            "description": "Groceries",
            "amount": 500.0 + (i * 50.0),
            "type": "expense",
            "category": "groceries",
        })
        # Dining: 300, 310, 290, 300 (Stable)
        rows.append({
            "transaction_date": f"{period_str}-20",
            "merchant": "Cafe Delights",
            "description": "Dining Out",
            "amount": 300.0 if i % 2 == 0 else 310.0,
            "type": "expense",
            "category": "dining",
        })

    # One-off expense in m2 only for shopping
    rows.append({
        "transaction_date": "2025-02-18",
        "merchant": "Tech Store",
        "description": "Headphones",
        "amount": 200.0,
        "type": "expense",
        "category": "shopping",
    })

    return pd.DataFrame(rows)


def test_build_monthly_category_matrix(multi_month_category_ledger: pd.DataFrame):
    """Verify category monthly matrix construction and zero-fill for inactive months."""
    matrix = build_monthly_category_matrix(multi_month_category_ledger)

    assert len(matrix) == 4  # 4 periods: 2025-01 to 2025-04
    assert "rent" in matrix.columns
    assert "groceries" in matrix.columns
    assert "shopping" in matrix.columns

    # Rent must be 1000 in all 4 periods
    assert list(matrix["rent"]) == [1000.0, 1000.0, 1000.0, 1000.0]

    # Shopping was only present in 2025-02
    assert matrix.loc["2025-01", "shopping"] == 0.0
    assert matrix.loc["2025-02", "shopping"] == 200.0
    assert matrix.loc["2025-03", "shopping"] == 0.0


def test_forecast_category_expenses_math_and_trends(multi_month_category_ledger: pd.DataFrame):
    """
    Test 3-month moving average forecast, trend direction, and budget utilization.
    For Groceries across m2, m3, m4:
    m2 = 600, m3 = 650, m4 = 700.
    Rolling 3m average = (600 + 650 + 700) / 3 = 650.00.
    Since last month actual (700) > 650 * 1.05 (682.5), trend is 'Surging (+5%)'.
    """
    budgets = {
        "rent": 1000.0,
        "groceries": 600.0,  # Forecast is 650, so this should trigger 'Projected Overrun'
        "dining": 350.0,     # Forecast is ~305, within budget
    }

    forecast_df, summary = forecast_category_expenses(multi_month_category_ledger, target_budgets=budgets)

    assert not forecast_df.empty
    assert summary["forecast_period"] == "2025-05"
    assert summary["categories_at_risk_count"] >= 1

    # Check Rent
    rent_row = forecast_df[forecast_df["category"] == "rent"].iloc[0]
    assert rent_row["forecast_amount"] == 1000.0
    assert rent_row["budget_status"] in ["Within Budget", "At Risk (90-100%)"]

    # Check Groceries
    groc_row = forecast_df[forecast_df["category"] == "groceries"].iloc[0]
    assert groc_row["forecast_amount"] == 650.00
    assert groc_row["trend"] == "Surging (+5%)"
    assert groc_row["budget_status"] == "Projected Overrun"
    assert groc_row["projected_variance"] == 50.00

    # Total projected expense must equal sum of individual category forecasts
    expected_total = round(float(forecast_df["forecast_amount"].sum()), 2)
    assert summary["total_projected_expense"] == expected_total


def test_forecast_category_empty_handling():
    """Verify empty DataFrame returns empty outputs cleanly."""
    forecast_df, summary = forecast_category_expenses(pd.DataFrame())

    assert forecast_df.empty
    assert summary["total_projected_expense"] == 0.0
    assert summary["forecast_period"] is None
