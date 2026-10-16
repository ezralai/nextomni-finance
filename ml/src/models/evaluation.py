"""
Chronological Time-Series Model Evaluation Harness.

Provides leakage-free evaluation protocols for personal expense forecasting:
1. Fixed Chronological Out-of-Sample Holdout (e.g. 12m train vs 6m test)
2. Walk-Forward Expanding Window Validation (simulating live monthly production updates)
3. Standardized Multi-Model Benchmarking (Baselines vs Regressors)
"""

from __future__ import annotations

from typing import Any
import numpy as np
import pandas as pd

from src.models.baselines import (
    NaivePriorMonthBaseline,
    RollingWindowMeanBaseline,
    ExpandingMeanBaseline,
)
from src.models.forecasting import ExpenseRegressionForecaster
from src.utils.metrics import compute_mae, compute_rmse, compute_mape


def prepare_modeling_dataset(monthly_df: pd.DataFrame) -> pd.DataFrame:
    """
    Filter out warmup rows containing NaNs due to lag and rolling calculations.

    Parameters
    ----------
    monthly_df : pd.DataFrame
        Output from build_monthly_spending_series.

    Returns
    -------
    pd.DataFrame
        Clean, ready-to-model chronological dataframe.
    """
    if monthly_df.empty:
        return pd.DataFrame()

    # Drop rows that don't have enough history for lag_3 and rolling_3m_mean
    clean_df = monthly_df.dropna(subset=["lag_1", "lag_2", "lag_3", "rolling_3m_mean"]).copy()
    return clean_df.reset_index(drop=True)


def train_test_split_chronological(
    clean_df: pd.DataFrame,
    test_months: int = 6,
) -> tuple[pd.DataFrame, pd.DataFrame]:
    """
    Split time-series data chronologically into training and out-of-sample test sets.
    NEVER shuffles order to prevent future information leakage into the past.

    Parameters
    ----------
    clean_df : pd.DataFrame
        Prepared monthly dataset.
    test_months : int
        Number of final months reserved for test holdout.

    Returns
    -------
    tuple[pd.DataFrame, pd.DataFrame]
        (train_df, test_df)
    """
    if len(clean_df) <= test_months:
        raise ValueError(
            f"Dataset length ({len(clean_df)}) must be greater than test_months ({test_months})."
        )

    split_idx = len(clean_df) - test_months
    train_df = clean_df.iloc[:split_idx].copy().reset_index(drop=True)
    test_df = clean_df.iloc[split_idx:].copy().reset_index(drop=True)

    return train_df, test_df


def benchmark_all_models(
    train_df: pd.DataFrame,
    test_df: pd.DataFrame,
    target_col: str = "actual_expense",
) -> tuple[pd.DataFrame, pd.DataFrame]:
    """
    Evaluate all baselines and machine learning regressors on the EXACT same test periods.

    Returns
    -------
    tuple[pd.DataFrame, pd.DataFrame]
        (summary_metrics_table, detailed_predictions_table)
    """
    y_test = test_df[target_col].to_numpy(dtype=float)
    periods = test_df["period"].tolist()

    preds_dict: dict[str, np.ndarray] = {}

    # 1. Baseline: Naive Prior Month (y_hat_t = y_{t-1})
    naive = NaivePriorMonthBaseline()
    preds_dict["Baseline: Naive Prior Month"] = naive.predict(test_df["lag_1"])

    # 2. Baseline: 3-Month Moving Average
    rolling = RollingWindowMeanBaseline(window=3)
    preds_dict["Baseline: 3M Moving Average"] = rolling.predict(test_df["rolling_3m_mean"])

    # 3. Baseline: Expanding Historical Mean
    expanding = ExpandingMeanBaseline()
    # Compute expanding mean across the combined timeline up to t-1
    combined_history = pd.concat([train_df[target_col], test_df[target_col]], ignore_index=True)
    all_exp_preds = expanding.predict_series(combined_history)
    # Extract only the test portion
    preds_dict["Baseline: Expanding Historical Mean"] = all_exp_preds[len(train_df) :]

    # 4. ML Model: Ordinary Least Squares (Linear Regression)
    ols = ExpenseRegressionForecaster(model_type="linear", scale_features=True)
    ols.fit(train_df, train_df[target_col])
    preds_dict["ML: Linear Regression (OLS)"] = ols.predict(test_df)

    # 5. ML Model: Ridge Regression (L2 Regularized, alpha=1.0)
    ridge_1 = ExpenseRegressionForecaster(model_type="ridge", alpha=1.0, scale_features=True)
    ridge_1.fit(train_df, train_df[target_col])
    preds_dict["ML: Ridge Regression (α=1.0)"] = ridge_1.predict(test_df)

    # 6. ML Model: Ridge Regression (Higher Regularization, alpha=5.0)
    ridge_5 = ExpenseRegressionForecaster(model_type="ridge", alpha=5.0, scale_features=True)
    ridge_5.fit(train_df, train_df[target_col])
    preds_dict["ML: Ridge Regression (α=5.0)"] = ridge_5.predict(test_df)

    # Compile Summary Metrics
    records = []
    for model_name, y_pred in preds_dict.items():
        mae = compute_mae(y_test, y_pred)
        rmse = compute_rmse(y_test, y_pred)
        mape = compute_mape(y_test, y_pred)
        max_err = round(float(np.max(np.abs(y_test - y_pred))), 2)

        records.append({
            "model": model_name,
            "test_sample_size": len(y_test),
            "mae": mae,
            "rmse": rmse,
            "mape_pct": mape,
            "max_error": max_err,
        })

    summary_df = pd.DataFrame(records).sort_values(by="mae").reset_index(drop=True)

    # Compile Detailed Predictions Table for inspection
    details_df = pd.DataFrame({
        "period": periods,
        "actual_expense": y_test,
    })
    for model_name, y_pred in preds_dict.items():
        details_df[f"pred_{model_name}"] = y_pred

    return summary_df, details_df


def run_walk_forward_validation(
    clean_df: pd.DataFrame,
    min_train_months: int = 8,
    target_col: str = "actual_expense",
) -> pd.DataFrame:
    """
    Walk-forward expanding window validation.
    At each step k, fit on [0..k-1] and predict step k.
    Expands until the final month is evaluated.
    """
    total_months = len(clean_df)
    if total_months <= min_train_months:
        raise ValueError(
            f"Dataset length ({total_months}) must exceed min_train_months ({min_train_months})."
        )

    results = []
    for step in range(min_train_months, total_months):
        train_slice = clean_df.iloc[:step]
        test_row = clean_df.iloc[step : step + 1]
        period = test_row["period"].values[0]
        actual = float(test_row[target_col].values[0])

        # Evaluate Ridge (alpha=1.0)
        ridge = ExpenseRegressionForecaster(model_type="ridge", alpha=1.0, scale_features=True)
        ridge.fit(train_slice, train_slice[target_col])
        pred_ridge = float(ridge.predict(test_row)[0])

        # Evaluate Naive Prior Month
        pred_naive = float(test_row["lag_1"].values[0])

        # Evaluate 3M Moving Average
        pred_rolling = float(test_row["rolling_3m_mean"].values[0])

        results.append({
            "period": period,
            "actual": actual,
            "pred_ridge": pred_ridge,
            "pred_naive": pred_naive,
            "pred_rolling": pred_rolling,
            "error_ridge": round(actual - pred_ridge, 2),
            "error_naive": round(actual - pred_naive, 2),
            "abs_error_ridge": round(abs(actual - pred_ridge), 2),
            "abs_error_naive": round(abs(actual - pred_naive), 2),
        })

    return pd.DataFrame(results)
