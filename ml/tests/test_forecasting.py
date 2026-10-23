"""
Unit Tests for Machine Learning Regression Forecasters and Evaluation Harness.
"""

from __future__ import annotations

import numpy as np
import pandas as pd
import pytest

from src.features.engineering import build_monthly_spending_series
from src.models.forecasting import ExpenseRegressionForecaster
from src.models.evaluation import (
    prepare_modeling_dataset,
    train_test_split_chronological,
    benchmark_all_models,
    run_walk_forward_validation,
)


@pytest.fixture
def multi_month_dataset() -> pd.DataFrame:
    """
    Generate 15 months of consistent mock transactions with known baseline levels.
    """
    rows = []
    base_expenses = [
        3000.0, 3100.0, 3050.0, 3200.0, 3150.0,
        3300.0, 3250.0, 3400.0, 3350.0, 3500.0,
        3450.0, 3600.0, 3550.0, 3700.0, 3650.0,
    ]
    for i, exp in enumerate(base_expenses, start=1):
        year = 2025 if i <= 12 else 2026
        month_num = i if i <= 12 else i - 12
        month_str = f"{year}-{month_num:02d}"
        rows.append({
            "transaction_date": f"{month_str}-05",
            "description": f"Living expenses month {i}",
            "amount": exp,
            "type": "expense",
            "category": "living",
        })
        rows.append({
            "transaction_date": f"{month_str}-25",
            "description": f"Salary month {i}",
            "amount": 6000.0,
            "type": "income",
            "category": "salary",
        })
    df = pd.DataFrame(rows)
    monthly_series = build_monthly_spending_series(df)
    return prepare_modeling_dataset(monthly_series)


def test_prepare_modeling_dataset_drops_warmup_nans():
    """Verify that rows without full 3-month lag features are purged."""
    # Create artificial 5-row monthly df with initial NaNs
    raw = pd.DataFrame({
        "period": ["2025-01", "2025-02", "2025-03", "2025-04", "2025-05"],
        "actual_expense": [100.0, 200.0, 300.0, 400.0, 500.0],
        "lag_1": [np.nan, 100.0, 200.0, 300.0, 400.0],
        "lag_2": [np.nan, np.nan, 100.0, 200.0, 300.0],
        "lag_3": [np.nan, np.nan, np.nan, 100.0, 200.0],
        "rolling_3m_mean": [np.nan, np.nan, np.nan, 200.0, 300.0],
    })
    clean = prepare_modeling_dataset(raw)
    assert len(clean) == 2
    assert clean.iloc[0]["period"] == "2025-04"
    assert clean.iloc[1]["period"] == "2025-05"


def test_chronological_split_strict_boundaries(multi_month_dataset: pd.DataFrame):
    """Verify train/test split maintains strict chronological sequence without leakage."""
    total_len = len(multi_month_dataset)
    test_months = 4

    train_df, test_df = train_test_split_chronological(multi_month_dataset, test_months=test_months)

    assert len(train_df) + len(test_df) == total_len
    assert len(test_df) == test_months

    # Test periods must strictly follow train periods
    train_last_period = train_df.iloc[-1]["period"]
    test_first_period = test_df.iloc[0]["period"]
    assert train_last_period < test_first_period


def test_forecaster_fit_and_predict(multi_month_dataset: pd.DataFrame):
    """Test ExpenseRegressionForecaster for both OLS and Ridge."""
    train_df, test_df = train_test_split_chronological(multi_month_dataset, test_months=4)

    # 1. Test Ridge
    ridge = ExpenseRegressionForecaster(model_type="ridge", alpha=2.0, scale_features=True)
    ridge.fit(train_df, train_df["actual_expense"])

    assert ridge.is_fitted
    assert len(ridge.feature_importances_) == len(ridge.feature_cols)

    preds = ridge.predict(test_df)
    assert len(preds) == len(test_df)
    assert np.all(preds > 0)  # All expense forecasts should be positive

    explanation = ridge.explain()
    assert explanation["model_type"] == "ridge"
    assert explanation["alpha"] == 2.0
    assert "lag_1" in explanation["coefficients"]

    # 2. Test Linear Regression
    ols = ExpenseRegressionForecaster(model_type="linear", scale_features=False)
    ols.fit(train_df, train_df["actual_expense"])
    preds_ols = ols.predict(test_df)
    assert len(preds_ols) == len(test_df)


def test_forecaster_predict_with_intervals(multi_month_dataset: pd.DataFrame):
    """Test confidence intervals computation (80% and 95%) from residual variance."""
    train_df, test_df = train_test_split_chronological(multi_month_dataset, test_months=4)
    ridge = ExpenseRegressionForecaster(model_type="ridge", alpha=1.0)
    ridge.fit(train_df, train_df["actual_expense"])

    single_row = test_df.iloc[[0]]
    result = ridge.predict_with_intervals(single_row, horizon_step=1, recurring_floor=500.0)

    assert "predicted_expense" in result
    assert "lower_bound_80" in result
    assert "upper_bound_80" in result
    assert "lower_bound_95" in result
    assert "upper_bound_95" in result
    assert "margin_of_error_80" in result
    assert "margin_of_error_95" in result

    pred = result["predicted_expense"]
    assert pred >= 500.0
    # 80% CI bounds check
    assert result["lower_bound_80"] <= pred <= result["upper_bound_80"]
    # 95% CI bounds check
    assert result["lower_bound_95"] <= pred <= result["upper_bound_95"]
    # 95% interval must be wider than 80% interval
    assert result["lower_bound_95"] <= result["lower_bound_80"]
    assert result["upper_bound_95"] >= result["upper_bound_80"]
    assert result["margin_of_error_95"] >= result["margin_of_error_80"]


def test_forecaster_error_handling():
    """Verify appropriate exceptions for invalid states or inputs."""
    forecaster = ExpenseRegressionForecaster(model_type="ridge")
    with pytest.raises(RuntimeError, match="must be fitted"):
        forecaster.predict(pd.DataFrame({"lag_1": [100.0]}))

    with pytest.raises(ValueError, match="Unsupported model_type"):
        ExpenseRegressionForecaster(model_type="neural_network")


def test_benchmark_all_models_executes_cleanly(multi_month_dataset: pd.DataFrame):
    """Test that benchmark_all_models runs all baselines and ML models successfully."""
    train_df, test_df = train_test_split_chronological(multi_month_dataset, test_months=4)
    summary_df, details_df = benchmark_all_models(train_df, test_df)

    assert not summary_df.empty
    assert "model" in summary_df.columns
    assert "mae" in summary_df.columns
    assert "rmse" in summary_df.columns
    assert "mape_pct" in summary_df.columns

    # Verify all expected models are present
    models = summary_df["model"].tolist()
    assert "Baseline: Naive Prior Month" in models
    assert "Baseline: 3M Moving Average" in models
    assert "ML: Linear Regression (OLS)" in models
    assert "ML: Ridge Regression (α=1.0)" in models

    # Detailed table sanity check
    assert "period" in details_df.columns
    assert "actual_expense" in details_df.columns
    assert len(details_df) == 4


def test_walk_forward_validation(multi_month_dataset: pd.DataFrame):
    """Verify walk-forward step-by-step expanding window simulation."""
    wf_results = run_walk_forward_validation(multi_month_dataset, min_train_months=8)

    assert not wf_results.empty
    assert "period" in wf_results.columns
    assert "actual" in wf_results.columns
    assert "pred_ridge" in wf_results.columns
    assert "error_ridge" in wf_results.columns
    assert "abs_error_ridge" in wf_results.columns
    # Check that error is correctly defined as actual - predicted
    for _, row in wf_results.iterrows():
        expected_error = round(row["actual"] - row["pred_ridge"], 2)
        assert abs(row["error_ridge"] - expected_error) < 0.05
