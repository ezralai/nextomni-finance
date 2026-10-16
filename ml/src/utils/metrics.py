"""
Time-Series Forecasting Evaluation Metrics.

Computes interpretable financial error metrics:
- Mean Absolute Error (MAE): Average error magnitude in currency units (MYR)
- Root Mean Squared Error (RMSE): Quadratic penalty penalizing large outlier misses
- Mean Absolute Percentage Error (MAPE): Relative error percentage
"""

from __future__ import annotations

from typing import Any
import numpy as np
import pandas as pd


def compute_mae(y_true: np.ndarray | list[float] | pd.Series, y_pred: np.ndarray | list[float] | pd.Series) -> float:
    """
    Mean Absolute Error (MAE): Average absolute difference between actual and forecast.
    Interpretable directly in the target currency (e.g. 'on average, forecast misses by MYR 150').
    """
    y_t = np.asarray(y_true, dtype=float)
    y_p = np.asarray(y_pred, dtype=float)
    return round(float(np.mean(np.abs(y_t - y_p))), 2)


def compute_rmse(y_true: np.ndarray | list[float] | pd.Series, y_pred: np.ndarray | list[float] | pd.Series) -> float:
    """
    Root Mean Squared Error (RMSE): Square root of mean squared errors.
    Penalizes large errors disproportionately more than small errors.
    """
    y_t = np.asarray(y_true, dtype=float)
    y_p = np.asarray(y_pred, dtype=float)
    return round(float(np.sqrt(np.mean((y_t - y_p) ** 2))), 2)


def compute_mape(y_true: np.ndarray | list[float] | pd.Series, y_pred: np.ndarray | list[float] | pd.Series) -> float:
    """
    Mean Absolute Percentage Error (MAPE): Percentage relative error.
    Avoids division by zero by filtering non-positive actual values.
    """
    y_t = np.asarray(y_true, dtype=float)
    y_p = np.asarray(y_pred, dtype=float)

    mask = y_t > 0
    if not np.any(mask):
        return 0.0

    return round(float(np.mean(np.abs((y_t[mask] - y_p[mask]) / y_t[mask])) * 100), 2)


def evaluate_forecast(
    y_true: np.ndarray | list[float] | pd.Series,
    y_pred: np.ndarray | list[float] | pd.Series,
    model_name: str = "Model",
) -> dict[str, Any]:
    """
    Compile a standardized evaluation report for a given forecaster.
    """
    mae = compute_mae(y_true, y_pred)
    rmse = compute_rmse(y_true, y_pred)
    mape = compute_mape(y_true, y_pred)

    return {
        "model_name": model_name,
        "sample_size": len(y_true),
        "mae": mae,
        "rmse": rmse,
        "mape_pct": mape,
    }
