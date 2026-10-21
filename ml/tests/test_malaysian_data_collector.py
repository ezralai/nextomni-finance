"""
Unit Tests for External Malaysian Data Collection and Advanced Spending Analytics.
"""

from __future__ import annotations

import numpy as np
import pandas as pd
import pytest

from src.data.malaysian_data_collector import (
    load_opendosm_hies_state,
    load_paynet_payment_statistics,
    get_state_consumption_basket,
    list_supported_states,
)
from src.analytics.malaysian_spending_analysis import (
    cluster_malaysian_state_expenditures,
    estimate_category_income_elasticity,
    fit_expenditure_distribution,
)


def test_load_opendosm_hies_state():
    """Verify loading and schema normalization of OpenDOSM HIES dataset."""
    df = load_opendosm_hies_state(use_cache=True)

    assert not df.empty
    assert len(df) == 16  # 13 states + 3 federal territories
    assert "state_code" in df.columns
    assert "income_mean" in df.columns
    assert "expenditure_mean" in df.columns

    # Verify percentages are calculated
    assert "food_and_beverage_pct" in df.columns
    assert (df["food_and_beverage_pct"] > 0).all()


def test_get_state_consumption_basket():
    """Verify state consumption basket extraction for Selangor (MY-10)."""
    basket = get_state_consumption_basket("MY-10")

    assert basket["state_code"] == "MY-10"
    assert basket["state_name"] == "Selangor"
    assert basket["income_mean"] > 10000.0
    assert "food_and_beverage" in basket["category_amounts"]
    assert "food_and_beverage" in basket["category_percentages"]


def test_load_paynet_payment_statistics():
    """Verify loading of PayNet and BNM payment channel metrics."""
    df = load_paynet_payment_statistics()

    assert not df.empty
    assert "DUITNOW_QR" in df["payment_channel"].values
    assert "TNG_EWALLET" in df["payment_channel"].values
    assert df["market_share_pct"].sum() == pytest.approx(100.0, abs=1.0)


def test_list_supported_states():
    """Verify list of supported Malaysian states."""
    states = list_supported_states()
    assert len(states) == 16
    codes = [s["code"] for s in states]
    assert "MY-10" in codes  # Selangor
    assert "MY-14" in codes  # KL


def test_cluster_malaysian_state_expenditures():
    """Verify PCA and K-Means clustering of state consumption baskets."""
    df = load_opendosm_hies_state()
    clustered = cluster_malaysian_state_expenditures(df, n_clusters=3)

    assert "clustered_df" in clustered
    assert "pca_variance_ratio" in clustered
    assert "cluster_profiles" in clustered
    assert len(clustered["cluster_profiles"]) == 3

    res_df = clustered["clustered_df"]
    assert "cluster" in res_df.columns
    assert "pca_x" in res_df.columns
    assert "pca_y" in res_df.columns
    assert set(res_df["cluster"].unique()) == {0, 1, 2}


def test_estimate_category_income_elasticity():
    """Verify Engel curve elasticity estimation across consumption categories."""
    df = load_opendosm_hies_state()
    elasticities = estimate_category_income_elasticity(df)

    assert elasticities["model_type"] == "Log-Log Ordinary Least Squares (OLS) Engel Curve"
    assert len(elasticities["elasticities"]) > 0

    results = {item["category"]: item["elasticity_beta"] for item in elasticities["elasticities"]}

    # Food & Groceries should be more inelastic than Restaurants / Recreation
    assert "groceries" in results
    assert "restaurants_and_hotels" in results
    assert results["groceries"] < results["restaurants_and_hotels"]


def test_fit_expenditure_distribution():
    """Verify parametric probability density fitting (Log-Normal vs Gamma)."""
    # Sample log-normal distributed expenditure data
    np.random.seed(42)
    sample_amounts = np.random.lognormal(mean=4.5, sigma=0.6, size=100)

    fit_res = fit_expenditure_distribution(sample_amounts)

    assert fit_res["sample_size"] == 100
    assert "p50_median" in fit_res["percentiles"]
    assert fit_res["best_fit_distribution"] in ["Log-Normal", "Gamma"]
    assert "log_normal" in fit_res
    assert "gamma" in fit_res
    assert fit_res["log_normal"]["p_value"] > 0
