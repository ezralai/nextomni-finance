"""
Heuristic Baseline Forecasters for Personal Finance.

Provides simple, interpretable benchmarks that all complex ML models must outperform:
1. NaivePriorMonthBaseline: Predicts that spending this month equals last month's spending.
2. RollingWindowMeanBaseline: Predicts spending as the moving average of the past K months.
3. ExpandingMeanBaseline: Predicts spending as the overall average of all historical months observed so far.
"""

from __future__ import annotations

import numpy as np
import pandas as pd


class NaivePriorMonthBaseline:
    """
    Naive Persistence Forecaster:
    Forecast for period t is simply the observed expense at period t-1.
    Mathematical formula: y_hat_t = y_{t-1}
    """

    def __init__(self):
        self.last_observed_value: float | None = None

    def fit(self, y: pd.Series | np.ndarray) -> NaivePriorMonthBaseline:
        """Fit by recording the most recent observed point."""
        arr = np.asarray(y, dtype=float)
        if len(arr) == 0:
            raise ValueError("Cannot fit NaivePriorMonthBaseline on empty series.")
        self.last_observed_value = float(arr[-1])
        return self

    def predict(self, lag_1_series: pd.Series | np.ndarray) -> np.ndarray:
        """
        Generate predictions using the lagged values (t-1).
        If provided an array of lag_1 values, returns lag_1 directly.
        """
        return np.asarray(lag_1_series, dtype=float)


class RollingWindowMeanBaseline:
    """
    Moving Average Forecaster:
    Forecast for period t is the arithmetic average of the last K observed periods.
    Mathematical formula: y_hat_t = (1 / K) * sum(y_{t-i}) for i=1..K
    """

    def __init__(self, window: int = 3):
        if window < 1:
            raise ValueError("Window size must be at least 1.")
        self.window = window

    def fit(self, y: pd.Series | np.ndarray) -> RollingWindowMeanBaseline:
        """Fit stores the rolling window history."""
        return self

    def predict(self, rolling_mean_series: pd.Series | np.ndarray) -> np.ndarray:
        """
        Returns the precomputed rolling average series.
        """
        return np.asarray(rolling_mean_series, dtype=float)


class ExpandingMeanBaseline:
    """
    Expanding Mean Forecaster:
    Predicts the cumulative mean of all historical observations seen up to period t-1.
    """

    def __init__(self):
        pass

    def fit(self, y: pd.Series | np.ndarray) -> ExpandingMeanBaseline:
        return self

    def predict_series(self, y_history: pd.Series | np.ndarray) -> np.ndarray:
        """
        Computes the expanding mean shifted by 1 so period t uses mean of [0..t-1].
        """
        s = pd.Series(y_history, dtype=float)
        return s.shift(1).expanding().mean().to_numpy()
