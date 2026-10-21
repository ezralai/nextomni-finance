"""
External Malaysian Spending Data Ingestion & OpenDOSM Collection Pipeline.

Fetches, sanitizes, and normalizes macroeconomic consumer datasets from:
- OpenDOSM / data.gov.my Household Income & Expenditure Survey (HIES)
- Bank Negara Malaysia (BNM) & PayNet digital payment channel statistics

Features resilient tiered caching to operate seamlessly across both online
and offline air-gapped production environments.
"""

from __future__ import annotations

from pathlib import Path
from typing import Any
import urllib.request
import urllib.error
import pandas as pd


# Local Cache Paths
EXTERNAL_DATA_DIR = Path(__file__).resolve().parent.parent.parent / "data" / "external"
HIES_LOCAL_PATH = EXTERNAL_DATA_DIR / "opendosm_hies_state.csv"
PAYNET_LOCAL_PATH = EXTERNAL_DATA_DIR / "paynet_payment_statistics.csv"

# Remote OpenDOSM Endpoints
OPENDOSM_HIES_REMOTE_URL = (
    "https://raw.githubusercontent.com/dosm-malaysia/data-open/main/datasets/hies_state.csv"
)

CANONICAL_COICOP_CATEGORIES = [
    "food_and_beverage",
    "groceries",
    "housing_and_utilities",
    "transportation",
    "health_and_wellness",
    "shopping",
    "recreation_and_culture",
    "restaurants_and_hotels",
    "miscellaneous",
]


def load_opendosm_hies_state(use_cache: bool = True) -> pd.DataFrame:
    """
    Ingest OpenDOSM Household Income & Expenditure Survey (HIES) state dataset.

    Attempts to fetch from remote GitHub/OpenDOSM endpoint if online and use_cache is False,
    falling back reliably to the validated local snapshot in ml/data/external/.

    Returns
    -------
    pd.DataFrame
        Cleaned, validated DataFrame containing income, expenditure, and category breakdowns.
    """
    df: pd.DataFrame | None = None

    if not use_cache:
        try:
            req = urllib.request.Request(
                OPENDOSM_HIES_REMOTE_URL,
                headers={"User-Agent": "NextOmniFinance-DataCollector/1.0"},
            )
            with urllib.request.urlopen(req, timeout=3.0) as resp:
                df = pd.read_csv(resp)
        except (urllib.error.URLError, TimeoutError, OSError):
            # Graceful fallback to verified local snapshot
            df = None

    if df is None:
        if not HIES_LOCAL_PATH.is_file():
            raise FileNotFoundError(f"OpenDOSM HIES local cache not found at {HIES_LOCAL_PATH}")
        df = pd.read_csv(HIES_LOCAL_PATH)

    # Clean and validate schema
    expected_cols = ["state_code", "state_name", "income_median", "income_mean", "expenditure_mean"]
    for col in expected_cols:
        if col not in df.columns:
            raise ValueError(f"Missing mandatory column '{col}' in OpenDOSM HIES dataset.")

    # Calculate category consumption shares (% of expenditure_mean)
    for cat in CANONICAL_COICOP_CATEGORIES:
        if cat in df.columns:
            df[f"{cat}_pct"] = (df[cat] / df["expenditure_mean"]) * 100.0

    return df


def load_paynet_payment_statistics() -> pd.DataFrame:
    """
    Load PayNet & Bank Negara Malaysia payment rail benchmarks.
    """
    if not PAYNET_LOCAL_PATH.is_file():
        raise FileNotFoundError(f"PayNet statistics local cache not found at {PAYNET_LOCAL_PATH}")
    return pd.read_csv(PAYNET_LOCAL_PATH)


def get_state_consumption_basket(state_code: str = "MY-10") -> dict[str, Any]:
    """
    Retrieve consumption profile and category percentages for a specific Malaysian state.

    Parameters
    ----------
    state_code : str
        ISO 3166-2:MY state code (e.g., 'MY-10' for Selangor, 'MY-14' for KL).

    Returns
    -------
    dict[str, Any]
        Dictionary of state income, expenditure, and category amounts/percentages.
    """
    df = load_opendosm_hies_state()
    row = df[df["state_code"] == state_code]

    if row.empty:
        # Default to national baseline (Selangor MY-10)
        row = df[df["state_code"] == "MY-10"]

    record = row.iloc[0]

    basket: dict[str, float] = {}
    percentages: dict[str, float] = {}

    for cat in CANONICAL_COICOP_CATEGORIES:
        if cat in record:
            basket[cat] = float(record[cat])
            percentages[cat] = round(float(record.get(f"{cat}_pct", (record[cat] / record["expenditure_mean"]) * 100.0)), 2)

    return {
        "state_code": str(record["state_code"]),
        "state_name": str(record["state_name"]),
        "income_median": float(record["income_median"]),
        "income_mean": float(record["income_mean"]),
        "expenditure_mean": float(record["expenditure_mean"]),
        "category_amounts": basket,
        "category_percentages": percentages,
    }


def list_supported_states() -> list[dict[str, str]]:
    """
    List all supported Malaysian states and federal territories in OpenDOSM.
    """
    df = load_opendosm_hies_state()
    return [
        {"code": str(row["state_code"]), "name": str(row["state_name"])}
        for _, row in df[["state_code", "state_name"]].iterrows()
    ]
