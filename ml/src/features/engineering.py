"""
Time-Series Feature Engineering Engine.

Constructs regularized chronological sequences and lagged predictive features:
- Lags (t-1, t-2, t-3)
- Rolling window statistical moments (moving average, moving standard deviation)
- Calendar seasonality encodings (month, quarter, days in period)

Guarantees ZERO DATA LEAKAGE by enforcing strict temporal shifts before calculating rolling stats.
"""

from __future__ import annotations

import pandas as pd


def build_monthly_spending_series(df: pd.DataFrame) -> pd.DataFrame:
    """
    Transform transaction ledger into a chronological monthly feature dataset.

    Parameters
    ----------
    df : pd.DataFrame
        Cleaned transaction DataFrame.

    Returns
    -------
    pd.DataFrame
        Monthly sequence with actual expenses and strictly lagged predictors.
    """
    if df.empty:
        return pd.DataFrame()

    work = df.copy()
    work["period"] = pd.to_datetime(work["transaction_date"]).dt.to_period("M").astype(str)

    # 1. Base Monthly Aggregations
    monthly = (
        work.groupby("period")
        .apply(
            lambda g: pd.Series({
                "actual_expense": round(float(g.loc[g["type"] == "expense", "amount"].sum()), 2),
                "actual_income": round(float(g.loc[g["type"] == "income", "amount"].sum()), 2),
                "transaction_count": len(g),
                "expense_count": int((g["type"] == "expense").sum()),
            })
        )
        .reset_index()
    )

    # Sort strictly chronologically
    monthly = monthly.sort_values(by="period").reset_index(drop=True)

    # Convert period string (YYYY-MM) to datetime for calendar features
    period_dt = pd.to_datetime(monthly["period"])
    monthly["year"] = period_dt.dt.year
    monthly["month"] = period_dt.dt.month
    monthly["quarter"] = period_dt.dt.quarter
    monthly["days_in_month"] = period_dt.dt.days_in_month

    # --------------------------------------------------------------------------
    # 2. Strict Lagged Features (Preventing Data Leakage)
    # CRITICAL: Features available at the start of month `t` must ONLY originate
    # from months `t-1, t-2, ...`.
    # --------------------------------------------------------------------------
    monthly["lag_1"] = monthly["actual_expense"].shift(1)  # Prior month spending
    monthly["lag_2"] = monthly["actual_expense"].shift(2)  # 2 months prior
    monthly["lag_3"] = monthly["actual_expense"].shift(3)  # 3 months prior

    # 3. Rolling Window Features over Past Observed Lags
    # Notice: we shift by 1 FIRST, then compute rolling(3), ensuring current month `t`
    # is NEVER included in the rolling mean.
    monthly["rolling_3m_mean"] = (
        monthly["actual_expense"].shift(1).rolling(window=3, min_periods=3).mean().round(2)
    )
    monthly["rolling_3m_std"] = (
        monthly["actual_expense"].shift(1).rolling(window=3, min_periods=3).std().round(2)
    )

    return monthly
