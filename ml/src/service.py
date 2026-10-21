"""
NextOmni Finance Machine Learning Prediction & Anomaly Inference Pipeline.

Exposes a JSON CLI and programmatic entry point for cross-service inference:
- Category-level statistical outlier detection (IQR)
- Multidimensional IsolationForest anomaly detection
- Out-of-sample spending forecasting with benchmark models
- Recurring subscription and billing detection
"""

from __future__ import annotations

import argparse
import json
from pathlib import Path
import sys
import time
from typing import Any, Dict, List

# Add ml root to sys.path
ML_ROOT = Path(__file__).resolve().parent.parent
if str(ML_ROOT) not in sys.path:
    sys.path.insert(0, str(ML_ROOT))

import numpy as np
import pandas as pd

from src.data.cleaning import clean_transactions
from src.analytics.spending import compute_overall_summary, compute_monthly_cashflow
from src.models.anomaly_detection import (
    detect_statistical_anomalies,
    IsolationForestAnomalyDetector,
)
from src.models.forecasting import ExpenseRegressionForecaster
from src.analytics.recurring import detect_recurring_expenses


def run_ml_pipeline(
    records: List[Dict[str, Any]],
    forecast_horizon_months: int = 3,
    iqr_multiplier: float = 2.0,
) -> Dict[str, Any]:
    """
    Execute full predictive intelligence and anomaly detection pipeline.

    Parameters
    ----------
    records : List[Dict[str, Any]]
        List of raw transaction dicts from Laravel ledger.
    forecast_horizon_months : int, default 3
        Number of forward monthly periods to project.
    iqr_multiplier : float, default 2.0
        Outlier threshold for statistical anomaly detection.

    Returns
    -------
    Dict[str, Any]
        Structured JSON response with summary, anomalies, forecasts, and recurring items.
    """
    start_time = time.perf_counter()

    if not records:
        return {
            "status": "empty",
            "message": "No transaction records provided for ML inference.",
            "duration_ms": round((time.perf_counter() - start_time) * 1000, 2),
            "anomalies": [],
            "forecast": [],
            "recurring": [],
        }

    # Normalize into DataFrame with expected column schema
    df_raw = pd.DataFrame(records)

    # Map column names if passed from Laravel
    column_mapping = {
        "transacted_at": "transaction_date",
        "date": "transaction_date",
    }
    for old_col, new_col in column_mapping.items():
        if old_col in df_raw.columns and new_col not in df_raw.columns:
            df_raw[new_col] = df_raw[old_col]

    if "transaction_date" not in df_raw.columns:
        raise ValueError("Missing 'transaction_date' or 'transacted_at' column in input records.")

    if "type" not in df_raw.columns:
        df_raw["type"] = "expense"

    if "amount" not in df_raw.columns:
        raise ValueError("Missing 'amount' column in input records.")

    if "description" not in df_raw.columns:
        df_raw["description"] = "Unlabeled Transaction"

    if "category" not in df_raw.columns:
        df_raw["category"] = "general"

    # 1. Clean transactions
    cleaned = clean_transactions(df_raw)

    # 2. Overall Summary & Monthly Cashflow
    summary = compute_overall_summary(cleaned)
    monthly_cashflow = compute_monthly_cashflow(cleaned)

    # 3. Statistical & Machine Learning Anomaly Detection
    anomalies_list: List[Dict[str, Any]] = []
    try:
        stat_anomalies = detect_statistical_anomalies(
            cleaned, iqr_multiplier=iqr_multiplier, min_category_samples=3
        )
        if not stat_anomalies.empty:
            for _, row in stat_anomalies.iterrows():
                anomalies_list.append({
                    "id": str(row.get("id", "")),
                    "date": str(row.get("date_str", "")),
                    "description": str(row.get("description", "")),
                    "amount": float(row.get("amount", 0.0)),
                    "category": str(row.get("category", "")),
                    "method": "statistical_iqr",
                    "reason": f"Amount is above {iqr_multiplier}x IQR category threshold",
                    "severity": "medium",
                })
    except Exception as e:
        pass

    try:
        if len(cleaned[cleaned["type"] == "expense"]) >= 10:
            iso_forest = IsolationForestAnomalyDetector(contamination=0.05)
            ml_anomalies = iso_forest.fit_predict(cleaned)
            flagged = ml_anomalies[ml_anomalies["is_anomaly"]]
            for _, row in flagged.iterrows():
                # Avoid duplicates from IQR
                if not any(a["id"] == str(row.get("id", "")) for a in anomalies_list):
                    anomalies_list.append({
                        "id": str(row.get("id", "")),
                        "date": str(row.get("date_str", "")),
                        "description": str(row.get("description", "")),
                        "amount": float(row.get("amount", 0.0)),
                        "category": str(row.get("category", "")),
                        "method": "isolation_forest",
                        "anomaly_score": float(row.get("anomaly_score", 0.0)),
                        "reason": "Multidimensional outlier detected by Isolation Forest",
                        "severity": "high" if row.get("anomaly_score", 0) > 0.7 else "medium",
                    })
    except Exception as e:
        pass

    # 4. Spending Forecasting
    forecast_list: List[Dict[str, Any]] = []
    try:
        expense_series = cleaned[cleaned["type"] == "expense"].set_index("transaction_date")["amount"]
        monthly_series = expense_series.resample("ME").sum()

        if len(monthly_series) >= 3:
            forecaster = ExpenseRegressionForecaster(model_type="ridge")
            forecaster.fit(monthly_series)
            projected = forecaster.forecast(steps=forecast_horizon_months)

            last_date = monthly_series.index[-1]
            for step_idx, val in enumerate(projected, start=1):
                next_month = (last_date + pd.DateOffset(months=step_idx)).strftime("%Y-%m")
                forecast_list.append({
                    "month": next_month,
                    "predicted_expense": max(0.0, round(float(val), 2)),
                    "confidence_level": 0.85,
                })
    except Exception as e:
        pass

    # 5. Recurring Bill Detection
    recurring_list: List[Dict[str, Any]] = []
    try:
        recurring_df = detect_recurring_expenses(cleaned)
        if not recurring_df.empty:
            for _, row in recurring_df.iterrows():
                avg_amt = float(row.get("average_amount", 0.0))
                recurring_list.append({
                    "merchant": str(row.get("merchant", "")),
                    "category": str(row.get("category", "general")),
                    "cadence": str(row.get("cadence", "Monthly")),
                    "cost_type": str(row.get("cost_type", "Fixed Amount")),
                    "average_amount": avg_amt,
                    "estimated_amount": avg_amt,  # backward compatibility alias
                    "monthly_equivalent": float(row.get("monthly_equivalent", avg_amt)),
                    "annual_projected": float(row.get("annual_projected", avg_amt * 12.0)),
                    "recurrence_count": int(row.get("recurrence_count", 0)),
                    "confidence": 0.9 if str(row.get("cost_type")) == "Fixed Amount" else 0.75,
                    "last_billed_date": str(row.get("last_billed_date", "")),
                    "next_expected_date": str(row.get("next_expected_date", "")),
                })
    except Exception as e:
        pass

    duration_ms = round((time.perf_counter() - start_time) * 1000, 2)

    return {
        "status": "success",
        "duration_ms": duration_ms,
        "records_analyzed": len(cleaned),
        "summary": {
            "total_income": float(summary.get("total_income", 0.0)),
            "total_expenses": float(summary.get("total_expenses", 0.0)),
            "net_savings": float(summary.get("net_savings", 0.0)),
            "savings_rate_pct": float(summary.get("savings_rate_pct", 0.0)),
        },
        "anomalies": anomalies_list,
        "forecast": forecast_list,
        "recurring": recurring_list,
    }


def main():
    parser = argparse.ArgumentParser(description="NextOmni Finance Machine Learning Intelligence Service")
    parser.add_argument("--input", type=str, help="Path to input JSON transactions file")
    parser.add_argument("--stdin", action="store_true", help="Read input JSON payload from stdin")
    parser.add_argument("--horizon", type=int, default=3, help="Forecast horizon months")

    args = parser.parse_args()

    payload_data = None
    if args.input:
        with open(args.input, "r", encoding="utf-8") as f:
            payload_data = json.load(f)
    elif args.stdin:
        payload_data = json.loads(sys.stdin.read())
    else:
        # Default mock demo data for testing pipeline
        now = pd.Timestamp.now()
        demo_txns = []
        for i in range(30):
            d = now - pd.Timedelta(days=i * 3)
            demo_txns.append({
                "id": f"txn-{i}",
                "transacted_at": d.isoformat(),
                "amount": 45.0 + (i % 5) * 12.0,
                "type": "expense",
                "category": "dining" if i % 2 == 0 else "groceries",
                "description": f"Merchant {i % 4}",
            })
        payload_data = demo_txns

    result = run_ml_pipeline(payload_data, forecast_horizon_months=args.horizon)
    print(json.dumps(result, indent=2))


if __name__ == "__main__":
    main()
