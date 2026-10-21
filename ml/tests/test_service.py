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
