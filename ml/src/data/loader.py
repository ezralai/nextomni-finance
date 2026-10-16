"""
Financial Transaction Data Ingestion and Pipeline Coordinator.

Provides high-level entry points for ingesting CSV files, executing validation,
applying data cleaning, and returning clean canonical data with audit reports.
"""

from __future__ import annotations

from pathlib import Path
import pandas as pd

from src.data.validation import ValidationReport, validate_dataframe
from src.data.cleaning import clean_transactions


def load_transaction_csv(file_path: str | Path) -> tuple[pd.DataFrame, ValidationReport]:
    """
    Ingest a CSV transaction file, validate records, clean data, and return report.

    Parameters
    ----------
    file_path : str or Path
        Path to the transaction CSV file.

    Returns
    -------
    tuple of (pd.DataFrame, ValidationReport)
        The standardized DataFrame and the comprehensive validation summary report.

    Raises
    ------
    FileNotFoundError
        If the file does not exist.
    ValueError
        If the file cannot be parsed or lacks mandatory columns.
    """
    path = Path(file_path)
    if not path.is_file():
        raise FileNotFoundError(f"Transaction file not found: {path.resolve()}")

    try:
        raw_df = pd.read_csv(path)
    except Exception as exc:
        raise ValueError(f"Failed to parse CSV file at {path}: {exc}") from exc

    # 1. Validation & Hygiene
    validated_df, report = validate_dataframe(raw_df)

    # 2. Cleaning & Standardization
    cleaned_df = clean_transactions(validated_df)

    return cleaned_df, report
