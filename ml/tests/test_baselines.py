"""
Unit Tests for Time-Series Feature Engineering, Baselines, and Metrics.
"""

from __future__ import annotations

import numpy as np
import pandas as pd
import pytest

from src.features.engineering import build_monthly_spending_series
from src.models.baselines import (
    NaivePriorMonthBaseline,
    RollingWindowMeanBaseline,
    ExpandingMeanBaseline,
)
from src.utils.metrics import compute_mae, compute_rmse, compute_mape, evaluate_forecast


@pytest.fixture
def sample_monthly_transactions() -> pd.DataFrame:
    """Fixture providing 6 months of distinct transactions."""
    rows = []
    # 6 consecutive months
    expenses = [3000.0, 3200.0, 3100.0, 3500.0, 3300.0, 3600.0]
    for i, exp in enumerate(expenses, start=1):
        month_str = f"2025-0{i}" if i < 10 else f"2025-{i}"
        rows.append({
            "transaction_date": f"{month_str}-05",
            "description": f"General expenses month {i}",
            "amount": exp,
            "type": "expense",
            "category": "living",
        })
        rows.append({
            "transaction_date": f"{month_str}-25",
            "description": f"Salary month {i}",
            "amount": 5000.0,
            "type": "income",
            "category": "salary",
        })
    return pd.DataFrame(rows)


def test_feature_engineering_zero_leakage_guarantee(sample_monthly_transactions: pd.DataFrame):
    """
    Verify feature engineering structure and strictly prove zero future data leakage.
    lag_1 at row t MUST equal actual_expense at row t-1.
    rolling_3m_mean at row t MUST strictly average rows t-1, t-2, and t-3.
    """
    monthly = build_monthly_spending_series(sample_monthly_transactions)

    assert len(monthly) == 6
    assert "period" in monthly.columns
    assert "actual_expense" in monthly.columns
    assert "lag_1" in monthly.columns
    assert "rolling_3m_mean" in monthly.columns

    # Month 0: lags must be NaN (no prior history)
    assert pd.isna(monthly.loc[0, "lag_1"])
    assert pd.isna(monthly.loc[0, "rolling_3m_mean"])

    # Month 1: lag_1 must equal Month 0's actual expense
    assert monthly.loc[1, "lag_1"] == monthly.loc[0, "actual_expense"]

    # Month 3 (4th row): lag_1 = Month 2, lag_2 = Month 1, lag_3 = Month 0
    assert monthly.loc[3, "lag_1"] == 3100.0  # Month 2
    assert monthly.loc[3, "lag_2"] == 3200.0  # Month 1
    assert monthly.loc[3, "lag_3"] == 3000.0  # Month 0

    # rolling_3m_mean at Month 3 must equal mean of (3000 + 3200 + 3100) / 3 = 3100.0
    expected_3m = round((3000.0 + 3200.0 + 3100.0) / 3, 2)
    assert monthly.loc[3, "rolling_3m_mean"] == expected_3m


def test_naive_prior_month_baseline():
    """Verify NaivePriorMonthBaseline predictions."""
    baseline = NaivePriorMonthBaseline()
    lags = pd.Series([np.nan, 3000.0, 3200.0, 3100.0])
    preds = baseline.predict(lags)

    assert np.isnan(preds[0])
    assert preds[1] == 3000.0
    assert preds[2] == 3200.0
    assert preds[3] == 3100.0


def test_rolling_window_mean_baseline():
    """Verify RollingWindowMeanBaseline predictions."""
    baseline = RollingWindowMeanBaseline(window=3)
    rolling_vals = pd.Series([np.nan, np.nan, np.nan, 3100.0, 3266.67])
    preds = baseline.predict(rolling_vals)

    assert preds[3] == 3100.0
    assert preds[4] == 3266.67


def test_expanding_mean_baseline():
    """Verify ExpandingMeanBaseline predictions."""
    baseline = ExpandingMeanBaseline()
    history = [100.0, 200.0, 300.0, 400.0]
    preds = baseline.predict_series(history)

    # At t=0: NaN
    assert np.isnan(preds[0])
    # At t=1: mean([100]) = 100.0
    assert preds[1] == 100.0
    # At t=2: mean([100, 200]) = 150.0
    assert preds[2] == 150.0
    # At t=3: mean([100, 200, 300]) = 200.0
    assert preds[3] == 200.0


def test_evaluation_metrics_deterministic_math():
    """
    Test MAE, RMSE, and MAPE against hand-calculated expectations:
    y_true = [100.0, 200.0, 300.0]
    y_pred = [110.0, 190.0, 330.0]
    Errors: [+10, -10, +30]
    Abs Errors: [10, 10, 30] -> Sum = 50 -> Mean = 16.67
    Sq Errors:  [100, 100, 900] -> Sum = 1100 -> Mean = 366.67 -> Sqrt = 19.15
    Pct Errors: [10/100, 10/200, 30/300] = [0.10, 0.05, 0.10] -> Mean = 0.0833 -> 8.33%
    """
    y_true = [100.0, 200.0, 300.0]
    y_pred = [110.0, 190.0, 330.0]

    mae = compute_mae(y_true, y_pred)
    rmse = compute_rmse(y_true, y_pred)
    mape = compute_mape(y_true, y_pred)

    assert mae == 16.67
    assert rmse == 19.15
    assert mape == 8.33

    report = evaluate_forecast(y_true, y_pred, model_name="Hand-Calculated Test")
    assert report["mae"] == 16.67
    assert report["rmse"] == 19.15
    assert report["mape_pct"] == 8.33
    assert report["sample_size"] == 3
