import pytest
from src.service import run_ml_pipeline


def test_run_ml_pipeline_empty():
    res = run_ml_pipeline([])
    assert res["status"] == "empty"
    assert res["anomalies"] == []
    assert res["forecast"] == []


def test_run_ml_pipeline_valid_records():
    records = [
        {
            "id": f"txn-{i}",
            "transacted_at": f"2026-05-{10 + (i % 15):02d}T10:00:00",
            "amount": 25.50 + i * 2,
            "type": "expense",
            "category": "dining",
            "description": f"Mamak Stall {i}",
        }
        for i in range(20)
    ]
    res = run_ml_pipeline(records)
    assert res["status"] == "success"
    assert res["records_analyzed"] == 20
    assert "duration_ms" in res
    assert isinstance(res["anomalies"], list)


def test_run_ml_pipeline_recurring_detection():
    # 4 consecutive monthly Netflix charges (approx 30 days apart)
    records = [
        {
            "id": "tx-1",
            "transacted_at": "2026-01-15T10:00:00",
            "amount": 54.90,
            "type": "expense",
            "category": "entertainment",
            "description": "Netflix Subscription",
        },
        {
            "id": "tx-2",
            "transacted_at": "2026-02-14T10:00:00",
            "amount": 54.90,
            "type": "expense",
            "category": "entertainment",
            "description": "Netflix Subscription",
        },
        {
            "id": "tx-3",
            "transacted_at": "2026-03-16T10:00:00",
            "amount": 54.90,
            "type": "expense",
            "category": "entertainment",
            "description": "Netflix Subscription",
        },
        {
            "id": "tx-4",
            "transacted_at": "2026-04-15T10:00:00",
            "amount": 54.90,
            "type": "expense",
            "category": "entertainment",
            "description": "Netflix Subscription",
        },
    ]

    res = run_ml_pipeline(records)
    assert res["status"] == "success"
    assert len(res["recurring"]) == 1

    rec = res["recurring"][0]
    assert "Netflix" in rec["merchant"]
    assert rec["cadence"] == "Monthly"
    assert rec["cost_type"] == "Fixed Amount"
    assert rec["average_amount"] == 54.90
    assert rec["estimated_amount"] == 54.90
    assert rec["monthly_equivalent"] == 54.90
    assert rec["recurrence_count"] == 4
    assert rec["next_expected_date"] != ""


def test_run_ml_pipeline_tier1_ridge_forecasting():
    """Verify Tier 1 Ridge regression activates on accounts with >= 5 monthly periods."""
    records = []
    base_expenses = [1200.0, 1250.0, 1180.0, 1300.0, 1270.0, 1310.0]
    for month_idx, exp in enumerate(base_expenses, start=1):
        records.append({
            "id": f"tx-m{month_idx}",
            "transacted_at": f"2026-0{month_idx}-10T12:00:00",
            "amount": exp,
            "type": "expense",
            "category": "living",
            "description": "Monthly apartment bills",
        })

    res = run_ml_pipeline(records, forecast_horizon_months=3)
    assert res["status"] == "success"
    assert len(res["forecast"]) == 3

    for step in res["forecast"]:
        assert step["method"] == "ridge_autoregression"
        assert step["confidence_level"] == 0.85
        assert step["predicted_expense"] > 0.0
        assert step["month"] in ["2026-07", "2026-08", "2026-09"]


def test_run_ml_pipeline_tier2_weighted_trend_forecasting():
    """Verify Tier 2 weighted trend activates for 2 to 4 monthly periods."""
    records = [
        {
            "id": "tx-1",
            "transacted_at": "2026-02-15T12:00:00",
            "amount": 800.0,
            "type": "expense",
            "category": "groceries",
            "description": "Supermarket",
        },
        {
            "id": "tx-2",
            "transacted_at": "2026-03-15T12:00:00",
            "amount": 850.0,
            "type": "expense",
            "category": "groceries",
            "description": "Supermarket",
        },
        {
            "id": "tx-3",
            "transacted_at": "2026-04-15T12:00:00",
            "amount": 900.0,
            "type": "expense",
            "category": "groceries",
            "description": "Supermarket",
        },
    ]

    res = run_ml_pipeline(records, forecast_horizon_months=3)
    assert res["status"] == "success"
    assert len(res["forecast"]) == 3

    for step in res["forecast"]:
        assert step["method"] == "weighted_trend_baseline"
        assert step["confidence_level"] == 0.70
        assert step["predicted_expense"] >= 800.0


def test_run_ml_pipeline_tier3_cold_start_forecasting():
    """Verify Tier 3 daily run-rate gracefully projects for a brand new account (< 2 months)."""
    # User created account and logged 5 transactions over 10 days in May
    records = [
        {
            "id": f"tx-cold-{i}",
            "transacted_at": f"2026-05-{1 + i * 2:02d}T10:00:00",
            "amount": 50.0,
            "type": "expense",
            "category": "dining",
            "description": f"Lunch Day {i}",
        }
        for i in range(5)
    ]

    res = run_ml_pipeline(records, forecast_horizon_months=3)
    assert res["status"] == "success"
    assert len(res["forecast"]) == 3

    for step in res["forecast"]:
        assert step["method"] == "daily_run_rate_projection"
        assert step["confidence_level"] == 0.55
        # 250 spent over 9 days = ~27.78/day * 30 days = ~833.33/month
        assert 700.0 <= step["predicted_expense"] <= 1000.0


def test_run_ml_pipeline_category_breakdown():
    """Verify hierarchical category breakdown and trend output."""
    records = [
        {"id": "t1", "transacted_at": "2026-04-05", "amount": 600.0, "type": "expense", "category": "groceries", "description": "Market"},
        {"id": "t2", "transacted_at": "2026-04-10", "amount": 200.0, "type": "expense", "category": "dining", "description": "Dinner"},
        {"id": "t3", "transacted_at": "2026-05-05", "amount": 620.0, "type": "expense", "category": "groceries", "description": "Market"},
        {"id": "t4", "transacted_at": "2026-05-10", "amount": 250.0, "type": "expense", "category": "dining", "description": "Dinner"},
    ]

    res = run_ml_pipeline(records)
    assert res["status"] == "success"
    cat_forecast = res.get("category_forecast", {})
    assert "categories" in cat_forecast
    assert len(cat_forecast["categories"]) == 2
    cats = {c["category"]: c for c in cat_forecast["categories"]}
    assert "groceries" in cats
    assert "dining" in cats
    assert cats["groceries"]["forecast_amount"] > 0
    assert cats["dining"]["trend"] in ["Surging (+5%)", "Declining (-5%)", "Stable"]


