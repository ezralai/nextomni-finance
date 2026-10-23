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
from src.features.engineering import build_monthly_spending_series
from src.models.evaluation import prepare_modeling_dataset
from src.models.category_forecasting import forecast_category_expenses
from src.models.spending_personas import classify_spending_persona


def generate_spending_forecast(
    cleaned: pd.DataFrame,
    forecast_horizon_months: int = 3,
    recurring_floor: float = 0.0,
) -> tuple[List[Dict[str, Any]], Dict[str, Any]]:
    """
    Generate out-of-sample monthly expense projections across 3 tiered strategies:
    - Tier 1: Autoregressive Ridge Regression (>= 5 monthly periods)
    - Tier 2: Weighted Trend Baseline (2 to 4 monthly periods)
    - Tier 3: Daily Run-Rate Projection (< 2 monthly periods / cold-start)
    Also constructs hierarchical category-level breakdowns and budget overrun alerts.
    """
    expenses = cleaned[cleaned["type"] == "expense"]
    if expenses.empty:
        base_date = pd.Timestamp.now()
        return [
            {
                "month": (base_date + pd.DateOffset(months=i)).strftime("%Y-%m"),
                "predicted_expense": 0.0,
                "confidence_level": 1.0,
                "method": "zero_expense_baseline",
                "lower_bound_80": 0.0,
                "upper_bound_80": 0.0,
                "lower_bound_95": 0.0,
                "upper_bound_95": 0.0,
                "margin_of_error_80": 0.0,
                "margin_of_error_95": 0.0,
            }
            for i in range(1, forecast_horizon_months + 1)
        ], {}

    monthly = build_monthly_spending_series(cleaned)
    num_months = len(monthly)
    last_date = (
        pd.to_datetime(str(monthly.iloc[-1]["period"]))
        if not monthly.empty
        else pd.to_datetime(expenses["transaction_date"].max())
    )
    forecast_list: List[Dict[str, Any]] = []

    # ---------------------------------------------------------
    # Tier 1: Autoregressive Ridge Regression (>= 5 monthly periods)
    # ---------------------------------------------------------
    if num_months >= 5:
        try:
            modeling_df = prepare_modeling_dataset(monthly)
            if len(modeling_df) >= 2:
                forecaster = ExpenseRegressionForecaster(model_type="ridge", alpha=1.0)
                forecaster.fit(modeling_df, modeling_df["actual_expense"])

                history = list(monthly["actual_expense"].to_numpy(dtype=float))
                for step in range(1, forecast_horizon_months + 1):
                    target_dt = last_date + pd.DateOffset(months=step)
                    row = pd.DataFrame([{
                        "lag_1": history[-1],
                        "lag_2": history[-2],
                        "lag_3": history[-3],
                        "rolling_3m_mean": float(np.mean(history[-3:])),
                        "days_in_month": float(target_dt.days_in_month),
                    }])
                    intervals = forecaster.predict_with_intervals(
                        row, horizon_step=step, recurring_floor=recurring_floor
                    )
                    pred = intervals["predicted_expense"]
                    history.append(pred)
                    forecast_list.append({
                        "month": target_dt.strftime("%Y-%m"),
                        "predicted_expense": pred,
                        "confidence_level": 0.85,
                        "method": "ridge_autoregression",
                        "lower_bound_80": intervals["lower_bound_80"],
                        "upper_bound_80": intervals["upper_bound_80"],
                        "lower_bound_95": intervals["lower_bound_95"],
                        "upper_bound_95": intervals["upper_bound_95"],
                        "margin_of_error_80": intervals["margin_of_error_80"],
                        "margin_of_error_95": intervals["margin_of_error_95"],
                    })
        except Exception:
            forecast_list = []

    # ---------------------------------------------------------
    # Tier 2: Weighted Trend Baseline (2 to 4 monthly periods)
    # ---------------------------------------------------------
    if not forecast_list and num_months >= 2:
        try:
            history = list(monthly["actual_expense"].to_numpy(dtype=float))
            weights = np.linspace(0.5, 1.0, len(history))
            weighted_base = float(np.average(history, weights=weights))
            slope = (history[-1] - history[0]) / max(len(history) - 1, 1)
            hist_std = float(np.std(history)) if len(history) > 1 else max(weighted_base * 0.15, 15.0)

            for step in range(1, forecast_horizon_months + 1):
                target_dt = last_date + pd.DateOffset(months=step)
                # Dampen slope by 0.8^step to avoid runaway extrapolation
                pred = weighted_base + slope * (0.8 ** step)
                pred = max(pred, recurring_floor, 0.0)

                step_factor = float(np.sqrt(1.0 + (step / 3.0)))
                sigma = max(hist_std * step_factor, pred * 0.08, 15.0)
                margin_80 = round(float(1.282 * sigma), 2)
                margin_95 = round(float(1.960 * sigma), 2)

                forecast_list.append({
                    "month": target_dt.strftime("%Y-%m"),
                    "predicted_expense": round(pred, 2),
                    "confidence_level": 0.70,
                    "method": "weighted_trend_baseline",
                    "lower_bound_80": round(max(pred - margin_80, recurring_floor, 0.0), 2),
                    "upper_bound_80": round(pred + margin_80, 2),
                    "lower_bound_95": round(max(pred - margin_95, recurring_floor, 0.0), 2),
                    "upper_bound_95": round(pred + margin_95, 2),
                    "margin_of_error_80": margin_80,
                    "margin_of_error_95": margin_95,
                })
        except Exception:
            forecast_list = []

    # ---------------------------------------------------------
    # Tier 3: Daily Run-Rate Projection (< 2 months / cold-start)
    # ---------------------------------------------------------
    if not forecast_list:
        try:
            min_date = pd.to_datetime(expenses["transaction_date"].min())
            max_date = pd.to_datetime(expenses["transaction_date"].max())
            days_span = max(1, (max_date - min_date).days + 1)
            daily_rate = float(expenses["amount"].sum()) / days_span
            daily_std = float(expenses.groupby("transaction_date")["amount"].sum().std()) if days_span > 2 else (daily_rate * 0.3)

            for step in range(1, forecast_horizon_months + 1):
                target_dt = last_date + pd.DateOffset(months=step)
                days_in_m = float(target_dt.days_in_month)
                monthly_estimate = daily_rate * days_in_m
                pred = max(monthly_estimate, recurring_floor, 0.0)

                sigma = max(float(daily_std * np.sqrt(days_in_m)), pred * 0.15, 20.0)
                step_factor = float(np.sqrt(1.0 + (step / 3.0)))
                sigma *= step_factor
                margin_80 = round(float(1.282 * sigma), 2)
                margin_95 = round(float(1.960 * sigma), 2)

                forecast_list.append({
                    "month": target_dt.strftime("%Y-%m"),
                    "predicted_expense": round(pred, 2),
                    "confidence_level": 0.55,
                    "method": "daily_run_rate_projection",
                    "lower_bound_80": round(max(pred - margin_80, recurring_floor, 0.0), 2),
                    "upper_bound_80": round(pred + margin_80, 2),
                    "lower_bound_95": round(max(pred - margin_95, recurring_floor, 0.0), 2),
                    "upper_bound_95": round(pred + margin_95, 2),
                    "margin_of_error_80": margin_80,
                    "margin_of_error_95": margin_95,
                })
        except Exception:
            forecast_list = []

    # ---------------------------------------------------------
    # Hierarchical Category Projections & Overrun Analytics
    # ---------------------------------------------------------
    category_summary: Dict[str, Any] = {}
    try:
        table_df, kpis = forecast_category_expenses(cleaned)
        if not table_df.empty:
            category_summary = {
                "forecast_period": kpis.get("forecast_period"),
                "total_projected_expense": float(kpis.get("total_projected_expense", 0.0)),
                "categories_at_risk_count": int(kpis.get("categories_at_risk_count", 0)),
                "categories": table_df.to_dict(orient="records"),
            }
    except Exception:
        category_summary = {}

    return forecast_list, category_summary


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
            "category_forecast": {},
            "recurring": [],
            "spending_persona": {},
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

    # 4. Recurring Bill Detection (computed before forecasting to establish baseline obligations floor)
    recurring_list: List[Dict[str, Any]] = []
    recurring_floor_total: float = 0.0
    try:
        recurring_df = detect_recurring_expenses(cleaned)
        if not recurring_df.empty:
            for _, row in recurring_df.iterrows():
                avg_amt = float(row.get("average_amount", 0.0))
                monthly_eq = float(row.get("monthly_equivalent", avg_amt))
                recurring_floor_total += monthly_eq
                recurring_list.append({
                    "merchant": str(row.get("merchant", "")),
                    "category": str(row.get("category", "general")),
                    "cadence": str(row.get("cadence", "Monthly")),
                    "cost_type": str(row.get("cost_type", "Fixed Amount")),
                    "average_amount": avg_amt,
                    "estimated_amount": avg_amt,  # backward compatibility alias
                    "monthly_equivalent": monthly_eq,
                    "annual_projected": float(row.get("annual_projected", avg_amt * 12.0)),
                    "recurrence_count": int(row.get("recurrence_count", 0)),
                    "confidence": 0.9 if str(row.get("cost_type")) == "Fixed Amount" else 0.75,
                    "last_billed_date": str(row.get("last_billed_date", "")),
                    "next_expected_date": str(row.get("next_expected_date", "")),
                })
    except Exception as e:
        pass

    # 5. Multi-Tier Spending Forecasting & Category Breakdown
    forecast_list: List[Dict[str, Any]] = []
    category_forecast: Dict[str, Any] = {}
    try:
        forecast_list, category_forecast = generate_spending_forecast(
            cleaned=cleaned,
            forecast_horizon_months=forecast_horizon_months,
            recurring_floor=recurring_floor_total,
        )
    except Exception as e:
        pass

    # 6. Malaysian Consumer Spending Persona Classification
    spending_persona: Dict[str, Any] = {}
    try:
        spending_persona = classify_spending_persona(cleaned)
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
        "category_forecast": category_forecast,
        "recurring": recurring_list,
        "spending_persona": spending_persona,
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
