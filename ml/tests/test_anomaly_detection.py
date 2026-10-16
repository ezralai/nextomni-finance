"""
Unit Tests for Statistical Anomaly Detection.
"""

from __future__ import annotations

import pandas as pd
import pytest

from src.models.anomaly_detection import detect_statistical_anomalies


@pytest.fixture
def transactions_with_anomalies() -> pd.DataFrame:
    """Fixture providing normal category expenses with one injected severe outlier."""
    rows = []
    # 10 normal grocery purchases between MYR 30 and MYR 60
    for i in range(10):
        rows.append({
            "transaction_id": f"TXN-GROC-{i}",
            "transaction_date": f"2025-01-{i+1:02d}",
            "description": f"Supermarket visit {i}",
            "amount": 40.0 + (i * 2.0),
            "type": "expense",
            "category": "groceries",
        })

    # Injected grocery anomaly: MYR 1,500.00 bulk purchase
    rows.append({
        "transaction_id": "TXN-ANOMALY-GROC",
        "transaction_date": "2025-01-20",
        "description": "Bulk import grocery store",
        "amount": 1500.0,
        "type": "expense",
        "category": "groceries",
    })

    # Income rows (should NEVER be flagged by expense anomaly detection)
    rows.append({
        "transaction_id": "TXN-SALARY",
        "transaction_date": "2025-01-25",
        "description": "Monthly Salary",
        "amount": 10000.0,
        "type": "income",
        "category": "salary",
    })

    return pd.DataFrame(rows)


def test_detect_statistical_anomalies_flags_outlier(transactions_with_anomalies: pd.DataFrame):
    """Verify that the extreme grocery outlier is correctly flagged."""
    flagged = detect_statistical_anomalies(transactions_with_anomalies, iqr_multiplier=2.0)

    assert not flagged.empty
    assert len(flagged) == 1

    outlier = flagged.iloc[0]
    assert outlier["transaction_id"] == "TXN-ANOMALY-GROC"
    assert outlier["amount"] == 1500.0
    assert outlier["category"] == "groceries"
    assert outlier["human_review_status"] == "Requires Review"
    assert "IQR threshold" in outlier["flag_reason"]


def test_detect_anomalies_empty_and_income_only():
    """Verify empty and non-expense dataframes return empty results cleanly."""
    empty_df = pd.DataFrame()
    assert detect_statistical_anomalies(empty_df).empty

    income_df = pd.DataFrame([{
        "transaction_id": "TX-1",
        "transaction_date": "2025-01-01",
        "description": "Salary",
        "amount": 5000.0,
        "type": "income",
        "category": "salary",
    }])
    assert detect_statistical_anomalies(income_df).empty


def test_detect_anomalies_skips_small_categories():
    """Verify that categories with fewer than min_category_samples are skipped to avoid false alarms."""
    few_txns = pd.DataFrame([
        {
            "transaction_id": "TX-1",
            "transaction_date": "2025-01-01",
            "description": "Niche book",
            "amount": 150.0,
            "type": "expense",
            "category": "rare_books",
        }
    ])
    flagged = detect_statistical_anomalies(few_txns, min_category_samples=5)
    assert flagged.empty


def test_isolation_forest_flags_extreme_outlier(transactions_with_anomalies: pd.DataFrame):
    """Verify that Isolation Forest detects the severe outlier and returns proper scores."""
    from src.models.anomaly_detection import IsolationForestAnomalyDetector

    detector = IsolationForestAnomalyDetector(contamination=0.10, random_state=42)
    anomalies = detector.fit_predict(transactions_with_anomalies)

    assert not anomalies.empty
    assert "anomaly_score" in anomalies.columns
    assert "flag_reason" in anomalies.columns

    # The extreme MYR 1500 grocery anomaly must be in the flagged set
    flagged_ids = anomalies["transaction_id"].tolist()
    assert "TXN-ANOMALY-GROC" in flagged_ids


def test_isolation_forest_empty_and_insufficient_samples():
    """Verify Isolation Forest returns empty DataFrame on empty or tiny datasets (< 10 samples)."""
    from src.models.anomaly_detection import IsolationForestAnomalyDetector

    detector = IsolationForestAnomalyDetector()
    assert detector.fit_predict(pd.DataFrame()).empty

    tiny_df = pd.DataFrame([{
        "transaction_date": "2025-01-01",
        "description": "Coffee",
        "amount": 10.0,
        "type": "expense",
        "category": "dining",
    }])
    assert detector.fit_predict(tiny_df).empty

