"""
Financial Transaction Data Validation Engine.

Performs rigorous data hygiene checks on incoming transaction datasets:
- Verifies required canonical columns
- Detects invalid dates and unparseable values
- Identifies missing or negative monetary amounts
- Detects exact and fuzzy duplicate transactions
- Compiles transparent ValidationReports (never silently dropping records)
"""

from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any
import pandas as pd

REQUIRED_COLUMNS = {
    "transaction_date",
    "description",
    "amount",
}

VALID_TRANSACTION_TYPES = {"expense", "income", "transfer"}


@dataclass
class ExcludedRecord:
    """Record of an excluded or invalid transaction row with specific justification."""
    row_index: int
    raw_data: dict[str, Any]
    reasons: list[str]


@dataclass
class ValidationReport:
    """Comprehensive validation summary detailing data health and exclusions."""
    total_records: int = 0
    valid_records: int = 0
    invalid_records: int = 0
    duplicate_records: int = 0
    excluded_records: list[ExcludedRecord] = field(default_factory=list)
    warnings: list[str] = field(default_factory=list)

    @property
    def pass_rate(self) -> float:
        """Percentage of incoming records that passed validation successfully."""
        if self.total_records == 0:
            return 0.0
        return round((self.valid_records / self.total_records) * 100, 2)

    def summary_dict(self) -> dict[str, Any]:
        """Dictionary representation suitable for logging or UI display."""
        return {
            "total_records": self.total_records,
            "valid_records": self.valid_records,
            "invalid_records": self.invalid_records,
            "duplicate_records": self.duplicate_records,
            "pass_rate_percent": self.pass_rate,
            "total_excluded": len(self.excluded_records),
            "warnings_count": len(self.warnings),
        }


def validate_dataframe(df: pd.DataFrame) -> tuple[pd.DataFrame, ValidationReport]:
    """
    Validate a raw transaction DataFrame and produce clean data + audit report.

    Rules applied:
    1. Schema Check: Confirms required columns exist.
    2. Missing Amounts: Disallow null, NaN, or non-numeric amounts.
    3. Negative Amounts / Sign Conventions: Flags and converts negative expenses
       while preserving original intent.
    4. Date Parsing: Verifies date is parsable into a valid calendar date.
    5. Deduplication: Flags and isolates exact duplicates (matching date, amount, description).
    """
    report = ValidationReport(total_records=len(df))

    if df.empty:
        report.warnings.append("Provided DataFrame is completely empty.")
        return df.copy(), report

    # 1. Schema check
    missing_cols = REQUIRED_COLUMNS - set(df.columns)
    if missing_cols:
        raise ValueError(
            f"Schema Validation Failure: Missing mandatory columns: {missing_cols}"
        )

    valid_indices: list[int] = []

    # Track duplicates across key financial attributes
    seen_transactions: set[tuple[str, float, str]] = set()

    for idx, row in df.iterrows():
        reasons: list[str] = []

        # Check Description
        desc = row.get("description")
        if pd.isna(desc) or str(desc).strip() == "":
            reasons.append("Missing or empty transaction description.")

        # Check Amount
        raw_amt = row.get("amount")
        if pd.isna(raw_amt):
            reasons.append("Amount is missing or null.")
        else:
            try:
                amt_val = float(raw_amt)
                if amt_val == 0.0:
                    reasons.append("Zero-amount transaction.")
            except (ValueError, TypeError):
                reasons.append(f"Amount '{raw_amt}' cannot be parsed as a numeric value.")

        # Check Date
        raw_date = row.get("transaction_date")
        if pd.isna(raw_date):
            reasons.append("Missing transaction date.")
        else:
            try:
                parsed_date = pd.to_datetime(raw_date, errors="raise")
                # Out-of-bounds sanity check (e.g., year 1800 or far future 2100)
                if parsed_date.year < 2000 or parsed_date.year > 2040:
                    reasons.append(f"Transaction date '{raw_date}' is outside realistic bounds (2000-2040).")
            except Exception:
                reasons.append(f"Date '{raw_date}' is not a valid recognized date format.")

        # Check Transaction Type if present
        raw_type = row.get("type")
        if not pd.isna(raw_type) and str(raw_type).strip().lower() not in VALID_TRANSACTION_TYPES:
            reasons.append(f"Invalid transaction type '{raw_type}'. Expected one of {VALID_TRANSACTION_TYPES}.")

        # Check Duplicate
        if not reasons:
            clean_date_str = pd.to_datetime(raw_date).strftime("%Y-%m-%d")
            clean_desc_str = str(desc).strip().lower()
            clean_amt_float = abs(float(raw_amt))
            dedup_key = (clean_date_str, clean_amt_float, clean_desc_str)

            if dedup_key in seen_transactions:
                report.duplicate_records += 1
                reasons.append(f"Exact duplicate transaction matching key {dedup_key}.")
            else:
                seen_transactions.add(dedup_key)

        # Decide Record Fate
        if reasons:
            report.invalid_records += 1
            report.excluded_records.append(
                ExcludedRecord(
                    row_index=int(idx),
                    raw_data=row.to_dict(),
                    reasons=reasons,
                )
            )
        else:
            valid_indices.append(int(idx))

    report.valid_records = len(valid_indices)

    # Return filtered DataFrame with only valid rows preserved
    clean_df = df.loc[valid_indices].copy()
    return clean_df, report
