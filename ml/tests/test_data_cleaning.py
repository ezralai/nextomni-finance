"""
Unit and Integration Tests for Data Validation, Cleaning, and Ingestion.
"""

from __future__ import annotations

from pathlib import Path
import pandas as pd
import pytest

from src.data.validation import validate_dataframe, ValidationReport
from src.data.cleaning import clean_transactions
from src.data.loader import load_transaction_csv
from src.data.synthetic import save_synthetic_csv


@pytest.fixture
def sample_csv_path(tmp_path: Path) -> Path:
    """Fixture that generates a small synthetic CSV for isolated testing."""
    test_csv = tmp_path / "test_transactions.csv"
    save_synthetic_csv(output_path=test_csv, num_months=3)
    return test_csv


def test_synthetic_csv_generation_and_loading(sample_csv_path: Path):
    """Ensure synthetic data generates cleanly and loads with expected schema."""
    df, report = load_transaction_csv(sample_csv_path)

    assert not df.empty
    assert isinstance(report, ValidationReport)
    assert report.total_records > 0
    # The synthetic generator intentionally includes 1 duplicate transaction
    assert report.duplicate_records >= 1
    assert report.valid_records == report.total_records - report.duplicate_records
    assert report.pass_rate > 95.0

    # Check canonical column presence
    expected_cols = {
        "transaction_date",
        "date_str",
        "description",
        "merchant",
        "amount",
        "currency",
        "type",
        "category",
        "account",
    }
    assert expected_cols.issubset(set(df.columns))

    # Verify chronological sorting
    dates = pd.to_datetime(df["transaction_date"])
    assert dates.is_monotonic_increasing


def test_validation_detects_missing_required_fields():
    """Ensure records with missing amount, date, or description are caught and logged."""
    dirty_data = pd.DataFrame([
        # Row 0: Valid
        {"transaction_date": "2025-03-01", "description": "Coffee", "amount": 12.50, "type": "expense"},
        # Row 1: Missing amount
        {"transaction_date": "2025-03-02", "description": "Lunch", "amount": None, "type": "expense"},
        # Row 2: Empty description
        {"transaction_date": "2025-03-03", "description": "   ", "amount": 45.00, "type": "expense"},
        # Row 3: Missing date
        {"transaction_date": None, "description": "Dinner", "amount": 80.00, "type": "expense"},
    ])

    clean_df, report = validate_dataframe(dirty_data)

    assert report.total_records == 4
    assert report.valid_records == 1
    assert report.invalid_records == 3
    assert len(report.excluded_records) == 3

    # Check reasons logged
    reasons_found = [r for rec in report.excluded_records for r in rec.reasons]
    assert any("Amount is missing" in r for r in reasons_found)
    assert any("description" in r for r in reasons_found)
    assert any("Missing transaction date" in r for r in reasons_found)


def test_validation_detects_duplicate_records():
    """Ensure identical transactions on the same date with same description and amount are flagged."""
    duplicated_data = pd.DataFrame([
        {"transaction_date": "2025-04-10", "description": "Netflix", "amount": 45.00, "type": "expense"},
        {"transaction_date": "2025-04-10", "description": "Netflix", "amount": 45.00, "type": "expense"},  # Duplicate
        {"transaction_date": "2025-04-10", "description": "Spotify", "amount": 16.90, "type": "expense"},
    ])

    clean_df, report = validate_dataframe(duplicated_data)

    assert report.total_records == 3
    assert report.valid_records == 2
    assert report.duplicate_records == 1
    assert len(clean_df) == 2


def test_validation_rejects_invalid_date_formats():
    """Ensure malformed date strings are rejected cleanly."""
    invalid_dates = pd.DataFrame([
        {"transaction_date": "not-a-date", "description": "Movie", "amount": 25.00},
        {"transaction_date": "2025-99-99", "description": "Snack", "amount": 5.00},
        {"transaction_date": "1850-01-01", "description": "Ancient transaction", "amount": 10.00},
    ])

    clean_df, report = validate_dataframe(invalid_dates)

    assert report.total_records == 3
    assert report.valid_records == 0
    assert report.invalid_records == 3


def test_cleaning_normalizes_categories_and_types():
    """Ensure string categories are normalized to lowercase slugs and amounts to positive scalars."""
    raw_df = pd.DataFrame([
        {
            "transaction_date": "2025-05-01",
            "description": "  Supermarket Groceries  ",
            "merchant": " Jaya Grocer ",
            "amount": -150.75,  # Negative sign should be converted to positive scalar
            "category": "Food & Beverage",
            "currency": "myr",
            "type": "EXPENSE",
        }
    ])

    cleaned = clean_transactions(raw_df)

    assert cleaned.loc[0, "description"] == "Supermarket Groceries"
    assert cleaned.loc[0, "merchant"] == "Jaya Grocer"
    assert cleaned.loc[0, "amount"] == 150.75
    assert cleaned.loc[0, "category"] == "food_&_beverage"
    assert cleaned.loc[0, "currency"] == "MYR"
    assert cleaned.loc[0, "type"] == "expense"


def test_empty_dataframe_handled_gracefully():
    """Ensure empty DataFrames do not throw unhandled exceptions."""
    empty_df = pd.DataFrame(columns=["transaction_date", "description", "amount"])
    clean_df, report = validate_dataframe(empty_df)

    assert clean_df.empty
    assert report.total_records == 0
    assert report.valid_records == 0
    assert len(report.warnings) > 0
