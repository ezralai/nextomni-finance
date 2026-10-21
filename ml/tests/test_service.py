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

