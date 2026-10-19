"""
Unit Tests for Recurring Expense & Subscription Detection Engine.
"""

from __future__ import annotations

import pandas as pd
import pytest

from src.analytics.recurring import detect_recurring_expenses, compute_recurring_summary


@pytest.fixture
def recurring_mock_ledger() -> pd.DataFrame:
    """Fixture providing known monthly fixed subscriptions, variable bills, and random expenses."""
    rows = []

    # 1. Fixed Monthly Subscription: Netflix (MYR 55.00 every ~30 days on the 10th)
    for m in range(1, 7):
        month_str = f"2025-0{m}"
        rows.append({
            "transaction_date": f"{month_str}-10",
            "merchant": "Netflix Standard",
            "description": "Netflix 4K Streaming",
            "amount": 55.00,
            "type": "expense",
            "category": "entertainment",
        })

    # 2. Variable Monthly Bill: TNB Electricity (Every ~30 days on the 5th, varying between 110 and 155)
    elec_amounts = [110.0, 145.0, 125.0, 155.0, 118.0, 140.0]
    for m, amt in enumerate(elec_amounts, start=1):
        month_str = f"2025-0{m}"
        rows.append({
            "transaction_date": f"{month_str}-05",
            "merchant": "TNB Electricity",
            "description": "Electricity Utility",
            "amount": amt,
            "type": "expense",
            "category": "utilities",
        })

    # 3. Weekly Fixed Subscription: Gym Class (Every 7 days)
    for w in range(1, 9):
        day = (w - 1) * 7 + 1
        day_str = f"{day:02d}" if day < 28 else "28"
        rows.append({
            "transaction_date": f"2025-01-{day_str}",
            "merchant": "FitZone Gym",
            "description": "Weekly Personal Training",
            "amount": 80.00,
            "type": "expense",
            "category": "health",
        })

    # 4. Random Non-Recurring Purchases: Dining out at random irregular days and amounts
    random_purchases = [
        ("2025-01-02", 15.50),
        ("2025-01-03", 45.00),
        ("2025-01-18", 120.00),
        ("2025-02-14", 85.00),
    ]
    for d, amt in random_purchases:
        rows.append({
            "transaction_date": d,
            "merchant": "Local Bistro",
            "description": "Dinner at Bistro",
            "amount": amt,
            "type": "expense",
            "category": "dining",
        })

    return pd.DataFrame(rows)


def test_detect_recurring_expenses_identifies_fixed_and_variable(recurring_mock_ledger: pd.DataFrame):
    """Verify that Netflix (Fixed) and TNB Electricity (Variable) are correctly detected and classified."""
    recurring = detect_recurring_expenses(recurring_mock_ledger)

    assert not recurring.empty
    merchants = recurring["merchant"].tolist()

    # Netflix must be identified as Monthly Fixed Amount
    assert "Netflix Standard" in merchants
    netflix_row = recurring[recurring["merchant"] == "Netflix Standard"].iloc[0]
    assert netflix_row["cadence"] == "Monthly"
    assert netflix_row["cost_type"] == "Fixed Amount"
    assert netflix_row["average_amount"] == 55.00
    assert netflix_row["monthly_equivalent"] == 55.00
    assert netflix_row["annual_projected"] == 660.00
    assert netflix_row["next_expected_date"] > netflix_row["last_billed_date"]

    # TNB must be identified as Monthly Variable Metered
    assert "TNB Electricity" in merchants
    tnb_row = recurring[recurring["merchant"] == "TNB Electricity"].iloc[0]
    assert tnb_row["cadence"] == "Monthly"
    assert tnb_row["cost_type"] == "Variable Metered"

    # Local Bistro (random irregular intervals and amounts) must NOT be detected
    assert "Local Bistro" not in merchants


def test_compute_recurring_summary_math(recurring_mock_ledger: pd.DataFrame):
    """Verify portfolio summary calculations for recurring obligations."""
    recurring = detect_recurring_expenses(recurring_mock_ledger)
    summary = compute_recurring_summary(recurring)

    assert summary["total_commitments"] >= 2
    assert summary["total_monthly_recurring"] > 0
    assert summary["total_annual_recurring"] == round(summary["total_monthly_recurring"] * 12.0, 2)
    assert summary["fixed_commitments_count"] >= 1
    assert summary["fixed_share_percent"] > 0


def test_detect_recurring_empty_ledger():
    """Verify empty DataFrame returns empty output cleanly."""
    empty_df = pd.DataFrame()
    assert detect_recurring_expenses(empty_df).empty

    summary = compute_recurring_summary(pd.DataFrame())
    assert summary["total_commitments"] == 0
    assert summary["total_monthly_recurring"] == 0.0
