"""
Financial Transaction Data Cleaning and Normalization Pipeline.

Transforms raw, validated tabular records into standard canonical form:
- Normalizes datetime objects to standard ISO dates
- Normalizes monetary values to absolute positive scalar values
- Standardizes categorical labels (lowercase slugs, default fallbacks)
- Sorts records strictly chronologically
"""

from __future__ import annotations

import pandas as pd


def clean_transactions(df: pd.DataFrame) -> pd.DataFrame:
    """
    Standardize validated transaction records into canonical schema.

    Parameters
    ----------
    df : pd.DataFrame
        Validated transaction DataFrame.

    Returns
    -------
    pd.DataFrame
        Standardized, type-safe, and chronologically sorted DataFrame.
    """
    if df.empty:
        return df.copy()

    clean = df.copy()

    # 1. Parse and format dates
    clean["transaction_date"] = pd.to_datetime(clean["transaction_date"])
    clean["date_str"] = clean["transaction_date"].dt.strftime("%Y-%m-%d")

    # 2. Normalize monetary amounts (positive float scalar for model boundaries)
    clean["amount"] = clean["amount"].astype(float).abs()

    # 3. Normalize string and categorical columns
    clean["description"] = clean["description"].astype(str).str.strip()

    if "merchant" in clean.columns:
        clean["merchant"] = clean["merchant"].fillna("Unknown Merchant").astype(str).str.strip()
    else:
        clean["merchant"] = clean["description"]

    if "category" in clean.columns:
        clean["category"] = (
            clean["category"]
            .fillna("uncategorized")
            .astype(str)
            .str.strip()
            .str.lower()
            .str.replace(" ", "_")
        )
    else:
        clean["category"] = "uncategorized"

    if "type" in clean.columns:
        clean["type"] = clean["type"].fillna("expense").astype(str).str.strip().str.lower()
    else:
        clean["type"] = "expense"

    if "currency" in clean.columns:
        clean["currency"] = clean["currency"].fillna("MYR").astype(str).str.strip().str.upper()
    else:
        clean["currency"] = "MYR"

    if "account" in clean.columns:
        clean["account"] = clean["account"].fillna("Default Account").astype(str).str.strip()

    # 4. Strict chronological ordering
    clean = clean.sort_values(by=["transaction_date", "description"]).reset_index(drop=True)

    return clean
