"""
Data Science & Machine Learning Analytics for Malaysian Consumer Spending Data.

Implements econometric and statistical learning algorithms on OpenDOSM benchmarks:
1. Unsupervised State Clustering (PCA + K-Means) on consumption baskets.
2. Econometric Engel Curve / Category Income Elasticity Estimation.
3. Parametric Expenditure Distribution Modeling (Log-Normal & Gamma MLE).
"""

from __future__ import annotations

from typing import Any
import numpy as np
import pandas as pd
from scipy import stats
from sklearn.cluster import KMeans
from sklearn.decomposition import PCA
from sklearn.linear_model import LinearRegression
from sklearn.preprocessing import StandardScaler


def cluster_malaysian_state_expenditures(
    hies_df: pd.DataFrame,
    n_clusters: int = 3,
) -> dict[str, Any]:
    """
    Cluster Malaysian states based on their consumption basket distributions using PCA & K-Means.

    Parameters
    ----------
    hies_df : pd.DataFrame
        DataFrame from load_opendosm_hies_state().
    n_clusters : int
        Number of clusters to partition (default: 3).

    Returns
    -------
    dict[str, Any]
        Dictionary containing clustered DataFrame, PCA coordinates, cluster centers,
        and macroeconomic persona labels.
    """
    feature_cols = [
        col for col in hies_df.columns if col.endswith("_pct")
    ]
    if not feature_cols:
        # Fallback to absolute category columns if pct columns not yet computed
        feature_cols = [
            "food_and_beverage", "groceries", "housing_and_utilities",
            "transportation", "health_and_wellness", "shopping", "restaurants_and_hotels",
        ]

    X = hies_df[feature_cols].copy().fillna(0)

    # Standardize features
    scaler = StandardScaler()
    X_scaled = scaler.fit_transform(X)

    # Dimensionality Reduction with PCA
    pca = PCA(n_components=2, random_state=42)
    coords = pca.fit_transform(X_scaled)

    # K-Means Clustering
    kmeans = KMeans(n_clusters=n_clusters, random_state=42, n_init=10)
    clusters = kmeans.fit_predict(X_scaled)

    df_result = hies_df.copy()
    df_result["cluster"] = clusters
    df_result["pca_x"] = coords[:, 0].round(4)
    df_result["pca_y"] = coords[:, 1].round(4)

    # Generate Cluster Macro Profiles
    cluster_profiles = []
    for c_id in range(n_clusters):
        c_subset = df_result[df_result["cluster"] == c_id]
        mean_exp = float(c_subset["expenditure_mean"].mean())
        mean_inc = float(c_subset["income_mean"].mean())
        states = c_subset["state_name"].tolist()

        if mean_exp >= 6000:
            archetype = "Urban High-Velocity & Discretionary (KL, Putrajaya, Selangor)"
            description = "High dining out, transport/tolls, and housing costs; lowest food-at-home proportion."
        elif mean_exp >= 4300:
            archetype = "Developing Balanced Regional (Penang, Johor, Melaka, N. Sembilan)"
            description = "Moderate living costs, diversified retail and services spending."
        else:
            archetype = "Essential & Staple-Weighted (Perak, Kedah, Kelantan, Perlis, Borneo)"
            description = "High proportion of budget dedicated to essential groceries, fuel, and utilities."

        cluster_profiles.append({
            "cluster_id": c_id,
            "archetype": archetype,
            "description": description,
            "state_count": len(c_subset),
            "states": states,
            "avg_income_mean": round(mean_inc, 2),
            "avg_expenditure_mean": round(mean_exp, 2),
        })

    return {
        "clustered_df": df_result,
        "pca_variance_ratio": [round(float(v), 4) for v in pca.explained_variance_ratio_],
        "total_variance_explained": round(float(pca.explained_variance_ratio_.sum()), 4),
        "cluster_profiles": cluster_profiles,
    }


def estimate_category_income_elasticity(hies_df: pd.DataFrame) -> dict[str, Any]:
    """
    Estimate category income elasticity of demand using empirical Log-Log Engel Curve regressions:
        ln(Expenditure_cat) = alpha + beta * ln(Income_mean)

    Beta (slope) represents the percentage change in spending per 1% increase in income:
    - beta < 1.0: Inelastic Necessity (Food, Groceries, Utilities)
    - beta >= 1.0: Elastic Luxury / Discretionary (Dining Out, Recreation, Shopping)
    """
    results: list[dict[str, Any]] = []

    income_series = hies_df["income_mean"].values
    log_income = np.log(income_series).reshape(-1, 1)

    categories = [
        "food_and_beverage",
        "groceries",
        "housing_and_utilities",
        "transportation",
        "health_and_wellness",
        "shopping",
        "recreation_and_culture",
        "restaurants_and_hotels",
    ]

    for cat in categories:
        if cat not in hies_df.columns:
            continue

        cat_spend = hies_df[cat].values
        # Filter non-zero values for log transform
        valid_mask = cat_spend > 0
        if valid_mask.sum() < 3:
            continue

        y_log = np.log(cat_spend[valid_mask])
        x_log = log_income[valid_mask]

        model = LinearRegression()
        model.fit(x_log, y_log)

        elasticity = float(model.coef_[0])
        r2 = float(model.score(x_log, y_log))

        if elasticity < 0.8:
            classification = "Inelastic Necessity (Essential Staple)"
            insight = "Spending increases slowly with income; households allocate relatively less as wealth grows."
        elif elasticity <= 1.15:
            classification = "Unitary Elastic (Standard Good)"
            insight = "Spending scales proportionally with household earnings."
        else:
            classification = "Elastic Luxury (Discretionary)"
            insight = "Highly sensitive to income gains; higher-earning households spend disproportionately more."

        results.append({
            "category": cat,
            "elasticity_beta": round(elasticity, 3),
            "r_squared": round(r2, 3),
            "classification": classification,
            "economic_insight": insight,
        })

    # Sort from most inelastic to most elastic
    results.sort(key=lambda x: x["elasticity_beta"])

    return {
        "model_type": "Log-Log Ordinary Least Squares (OLS) Engel Curve",
        "sample_size": len(hies_df),
        "elasticities": results,
    }


def fit_expenditure_distribution(amounts: list[float] | np.ndarray) -> dict[str, Any]:
    """
    Fit parametric probability density distributions (Log-Normal vs Gamma)
    to empirical expenditure amounts using Maximum Likelihood Estimation (MLE).
    """
    arr = np.asarray(amounts, dtype=float)
    arr = arr[arr > 0]

    if len(arr) < 5:
        return {
            "error": "Insufficient data points for parametric distribution fitting (minimum 5 required).",
        }

    # 1. Fit Log-Normal Distribution
    s_ln, loc_ln, scale_ln = stats.lognorm.fit(arr, floc=0)
    ks_stat_ln, p_val_ln = stats.kstest(arr, "lognorm", args=(s_ln, loc_ln, scale_ln))

    # 2. Fit Gamma Distribution
    a_gamma, loc_gamma, scale_gamma = stats.gamma.fit(arr, floc=0)
    ks_stat_gamma, p_val_gamma = stats.kstest(arr, "gamma", args=(a_gamma, loc_gamma, scale_gamma))

    best_dist = "Log-Normal" if ks_stat_ln <= ks_stat_gamma else "Gamma"

    percentiles = {
        "p10": round(float(np.percentile(arr, 10)), 2),
        "p25": round(float(np.percentile(arr, 25)), 2),
        "p50_median": round(float(np.median(arr)), 2),
        "p75": round(float(np.percentile(arr, 75)), 2),
        "p90": round(float(np.percentile(arr, 90)), 2),
        "p99": round(float(np.percentile(arr, 99)), 2),
    }

    return {
        "sample_size": len(arr),
        "mean": round(float(np.mean(arr)), 2),
        "std_dev": round(float(np.std(arr)), 2),
        "percentiles": percentiles,
        "best_fit_distribution": best_dist,
        "log_normal": {
            "shape_sigma": round(float(s_ln), 4),
            "scale_median": round(float(scale_ln), 4),
            "ks_statistic": round(float(ks_stat_ln), 4),
            "p_value": round(float(p_val_ln), 4),
        },
        "gamma": {
            "alpha_shape": round(float(a_gamma), 4),
            "scale_beta": round(float(scale_gamma), 4),
            "ks_statistic": round(float(ks_stat_gamma), 4),
            "p_value": round(float(p_val_gamma), 4),
        },
    }
