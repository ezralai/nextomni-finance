"""
Dual-Engine Anomaly Detection for Personal Financial Transactions.

Provides two complementary anomaly detection approaches:
1. Category-Level Non-Parametric IQR (Statistical Thresholding)
2. Multidimensional Isolation Forest (Machine Learning Ensemble)

Guiding Principle:
An anomaly is NOT necessarily fraud, a mistake, or unauthorized spending.
Flags represent transactions that deviate substantially from typical historical
behavior and are presented for human verification.
"""

from __future__ import annotations

import numpy as np
import pandas as pd
from sklearn.ensemble import IsolationForest
from sklearn.preprocessing import OrdinalEncoder


def detect_statistical_anomalies(
    df: pd.DataFrame,
    iqr_multiplier: float = 2.0,
    min_category_samples: int = 5,
) -> pd.DataFrame:
    """
    Detect statistical outliers in expenses on a per-category basis.

    Parameters
    ----------
    df : pd.DataFrame
        Cleaned transaction DataFrame.
    iqr_multiplier : float, default 2.0
        Tukey's IQR multiplier for extreme values.
    min_category_samples : int, default 5
        Minimum transactions in a category before applying statistical thresholds.

    Returns
    -------
    pd.DataFrame
        Flagged transactions with statistical context and human review guidance.
    """
    if df.empty:
        return pd.DataFrame()

    expense_df = df[df["type"] == "expense"].copy()
    if expense_df.empty:
        return pd.DataFrame()

    flagged_rows = []

    for category, cat_df in expense_df.groupby("category"):
        if len(cat_df) < min_category_samples:
            continue

        q1 = cat_df["amount"].quantile(0.25)
        q3 = cat_df["amount"].quantile(0.75)
        iqr = q3 - q1
        median = cat_df["amount"].median()

        upper_bound = round(float(q3 + (iqr_multiplier * iqr)), 2)

        # Find transactions exceeding upper bound
        outliers = cat_df[cat_df["amount"] > upper_bound]

        for _, row in outliers.iterrows():
            amt = float(row["amount"])
            multiple = round(amt / median, 1) if median > 0 else 1.0
            flagged_rows.append({
                "transaction_id": row.get("transaction_id", "N/A"),
                "transaction_date": str(row["transaction_date"]),
                "description": str(row["description"]),
                "category": str(category),
                "amount": amt,
                "category_median": round(float(median), 2),
                "upper_threshold": upper_bound,
                "deviation_ratio": f"{multiple}x median",
                "flag_reason": f"Amount (MYR {amt:,.2f}) exceeds category IQR threshold (MYR {upper_bound:,.2f})",
                "human_review_status": "Requires Review",
            })

    if not flagged_rows:
        return pd.DataFrame(
            columns=[
                "transaction_id",
                "transaction_date",
                "description",
                "category",
                "amount",
                "category_median",
                "upper_threshold",
                "deviation_ratio",
                "flag_reason",
                "human_review_status",
            ]
        )

    res_df = pd.DataFrame(flagged_rows)
    return res_df.sort_values(by="amount", ascending=False).reset_index(drop=True)


class IsolationForestAnomalyDetector:
    """
    Multidimensional Anomaly Detection Engine using Scikit-Learn Isolation Forest.

    Isolates unusual transactions by building randomized decision trees across multiple dimensions:
    - Log-transformed transaction amount
    - Day of week (0=Mon, 6=Sun)
    - Encoded spending category
    """

    def __init__(self, contamination: float = 0.02, random_state: int = 42):
        """
        Parameters
        ----------
        contamination : float, default 0.02
            Expected proportion of anomalies in the dataset (e.g. 2%).
        random_state : int, default 42
            Seed for reproducible tree splits.
        """
        self.contamination = float(contamination)
        self.random_state = int(random_state)
        self.model = IsolationForest(
            contamination=self.contamination,
            random_state=self.random_state,
            n_estimators=100,
        )
        self.encoder = OrdinalEncoder(handle_unknown="use_encoded_value", unknown_value=-1)
        self.is_fitted = False

    def fit_predict(self, df: pd.DataFrame) -> pd.DataFrame:
        """
        Train the Isolation Forest on expense transactions and return flagged anomalies.
        """
        if df.empty:
            return pd.DataFrame()

        expense_df = df[df["type"] == "expense"].copy()
        if len(expense_df) < 10:
            return pd.DataFrame()

        dates = pd.to_datetime(expense_df["transaction_date"])
        expense_df["day_of_week"] = dates.dt.dayofweek
        expense_df["log_amount"] = np.log1p(expense_df["amount"].clip(lower=0))

        cats = expense_df[["category"]].astype(str)
        cat_encoded = self.encoder.fit_transform(cats)

        X = np.column_stack([
            expense_df["log_amount"].to_numpy(),
            expense_df["day_of_week"].to_numpy(),
            cat_encoded,
        ])

        preds = self.model.fit_predict(X)  # -1 for anomaly, 1 for inlier
        scores = self.model.score_samples(X)  # Lower score = more anomalous

        expense_df["anomaly_flag"] = preds
        expense_df["anomaly_score"] = np.round(scores, 4)

        flagged = expense_df[expense_df["anomaly_flag"] == -1].copy()

        if flagged.empty:
            return pd.DataFrame()

        results = []
        for _, row in flagged.iterrows():
            amt = float(row["amount"])
            score = float(row["anomaly_score"])
            results.append({
                "transaction_id": row.get("transaction_id", "N/A"),
                "transaction_date": str(row["transaction_date"]),
                "description": str(row["description"]),
                "category": str(row["category"]),
                "amount": amt,
                "anomaly_score": score,
                "flag_reason": f"Multidimensional Isolation Forest path score: {score:.4f} (Contamination: {self.contamination:.1%})",
                "human_review_status": "Requires Review",
            })

        out_df = pd.DataFrame(results)
        # Sort by most negative anomaly score (most isolated)
        return out_df.sort_values(by="anomaly_score", ascending=True).reset_index(drop=True)
