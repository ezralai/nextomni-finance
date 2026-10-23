"""
Expense Forecasting Models with Scikit-Learn.

Implements interpretable linear regression and L2 regularized Ridge models
for monthly personal expense forecasting.

Enforces:
1. Strict temporal ordering (no future leakage).
2. Train-only feature scaling via StandardScaler.
3. Coefficient inspection for beginner data science interpretability.
"""

from __future__ import annotations

from typing import Any
import numpy as np
import pandas as pd
from sklearn.linear_model import LinearRegression, Ridge
from sklearn.preprocessing import StandardScaler


DEFAULT_FEATURE_COLS = [
    "lag_1",
    "lag_2",
    "lag_3",
    "rolling_3m_mean",
    "days_in_month",
]


class ExpenseRegressionForecaster:
    """
    Scikit-learn based regression forecaster for monthly expenses.

    Supports:
    - Ordinary Least Squares (LinearRegression)
    - L2 Regularization (Ridge) to prevent overfitting on small monthly datasets.
    """

    def __init__(
        self,
        model_type: str = "ridge",
        alpha: float = 1.0,
        feature_cols: list[str] | None = None,
        scale_features: bool = True,
    ):
        """
        Parameters
        ----------
        model_type : str
            Either 'linear' or 'ridge'.
        alpha : float
            L2 regularization strength for Ridge (higher = simpler, more constrained model).
        feature_cols : list[str], optional
            Columns to use as predictors. Defaults to DEFAULT_FEATURE_COLS.
        scale_features : bool
            Whether to standardize features with zero mean and unit variance.
        """
        self.model_type = model_type.lower()
        self.alpha = float(alpha)
        self.feature_cols = feature_cols or list(DEFAULT_FEATURE_COLS)
        self.scale_features = scale_features

        if self.model_type == "linear":
            self.model = LinearRegression()
        elif self.model_type == "ridge":
            self.model = Ridge(alpha=self.alpha)
        else:
            raise ValueError(f"Unsupported model_type '{model_type}'. Choose 'linear' or 'ridge'.")

        self.scaler: StandardScaler | None = StandardScaler() if scale_features else None
        self.is_fitted = False
        self.feature_importances_: dict[str, float] = {}
        self.intercept_: float = 0.0

    def fit(self, X: pd.DataFrame, y: pd.Series | np.ndarray) -> ExpenseRegressionForecaster:
        """
        Fit the regressor on training features and target.

        Parameters
        ----------
        X : pd.DataFrame
            Training feature matrix.
        y : pd.Series or np.ndarray
            Actual expense target.
        """
        if X.empty or len(y) == 0:
            raise ValueError("Cannot fit model on empty training data.")

        # Ensure all required feature columns exist
        missing = [col for col in self.feature_cols if col not in X.columns]
        if missing:
            raise KeyError(f"Missing required feature columns: {missing}")

        X_mat = X[self.feature_cols].to_numpy(dtype=float)
        y_vec = np.asarray(y, dtype=float)

        # Fit scaler ONLY on training data
        if self.scaler is not None:
            X_mat = self.scaler.fit_transform(X_mat)

        self.model.fit(X_mat, y_vec)
        self.is_fitted = True
        self.intercept_ = round(float(self.model.intercept_), 2)

        # Calculate residual standard error for confidence interval estimation
        in_sample_preds = self.model.predict(X_mat)
        residuals = y_vec - in_sample_preds
        dof = max(1, len(y_vec) - len(self.feature_cols) - 1)
        self.residual_std_ = round(float(np.sqrt(np.sum(residuals ** 2) / dof)), 2)

        # Record feature coefficients
        coefs = self.model.coef_
        self.feature_importances_ = {
            col: round(float(c), 4) for col, c in zip(self.feature_cols, coefs)
        }

        return self

    def predict(self, X: pd.DataFrame) -> np.ndarray:
        """
        Generate out-of-sample expense predictions.
        """
        if not self.is_fitted:
            raise RuntimeError("Model must be fitted before calling predict().")

        if X.empty:
            return np.array([])

        missing = [col for col in self.feature_cols if col not in X.columns]
        if missing:
            raise KeyError(f"Missing required feature columns: {missing}")

        X_mat = X[self.feature_cols].to_numpy(dtype=float)

        if self.scaler is not None:
            X_mat = self.scaler.transform(X_mat)

        preds = self.model.predict(X_mat)
        # Ensure predicted expenses cannot be negative
        preds = np.clip(preds, a_min=0.0, a_max=None)
        return np.round(preds, 2)

    def predict_with_intervals(
        self,
        X: pd.DataFrame,
        horizon_step: int = 1,
        recurring_floor: float = 0.0,
    ) -> dict[str, float]:
        """
        Generate out-of-sample expense prediction with 80% and 95% confidence intervals.

        Parameters
        ----------
        X : pd.DataFrame
            Single-row feature DataFrame.
        horizon_step : int, default 1
            Forecast month step (1, 2, or 3) to expand uncertainty over time.
        recurring_floor : float, default 0.0
            Fixed expense floor to prevent under-predicting committed outlays.

        Returns
        -------
        dict[str, float]
            Dictionary with predicted amount, 80% and 95% bounds, and margin of error.
        """
        preds = self.predict(X)
        pred = float(preds[0]) if len(preds) > 0 else 0.0
        pred = max(pred, recurring_floor, 0.0)

        # Scale prediction standard error by forecast step (uncertainty expands into future)
        step_factor = float(np.sqrt(1.0 + (horizon_step / 3.0)))
        sigma = max(self.residual_std_ * step_factor, pred * 0.05, 10.0)

        # Z-scores: 80% CI -> 1.282, 95% CI -> 1.960
        margin_80 = round(float(1.282 * sigma), 2)
        margin_95 = round(float(1.960 * sigma), 2)

        lower_80 = round(max(pred - margin_80, recurring_floor, 0.0), 2)
        upper_80 = round(pred + margin_80, 2)
        lower_95 = round(max(pred - margin_95, recurring_floor, 0.0), 2)
        upper_95 = round(pred + margin_95, 2)

        return {
            "predicted_expense": round(pred, 2),
            "lower_bound_80": lower_80,
            "upper_bound_80": upper_80,
            "lower_bound_95": lower_95,
            "upper_bound_95": upper_95,
            "margin_of_error_80": margin_80,
            "margin_of_error_95": margin_95,
        }

    def explain(self) -> dict[str, Any]:
        """
        Return human-readable model parameters and feature influence.
        """
        if not self.is_fitted:
            return {"status": "unfitted"}

        return {
            "model_type": self.model_type,
            "alpha": self.alpha if self.model_type == "ridge" else None,
            "scaled": self.scale_features,
            "intercept": self.intercept_,
            "coefficients": self.feature_importances_,
        }
