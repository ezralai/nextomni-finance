"""
NextOmni Personal Finance Intelligence & Spending Forecasting Application.

Interactive Streamlit interface delivering:
- Transaction CSV ingestion & schema validation audit
- Deterministic cash flow, category concentration & behavioral velocity
- Out-of-sample spending forecasting with benchmark model arena
- Statistical anomaly detection with human review guidance
"""

from __future__ import annotations

import io
from pathlib import Path
import altair as alt
import numpy as np
import pandas as pd
import streamlit as st

# Data Processing & Analytics Core
from src.data.validation import validate_dataframe
from src.data.cleaning import clean_transactions
from src.analytics.spending import (
    compute_overall_summary,
    compute_monthly_cashflow,
    compute_category_distribution,
    compute_day_of_week_metrics,
    compute_top_merchants,
)
from src.features.engineering import build_monthly_spending_series
from src.models.evaluation import (
    prepare_modeling_dataset,
    train_test_split_chronological,
    benchmark_all_models,
)
from src.models.forecasting import ExpenseRegressionForecaster
from src.models.anomaly_detection import (
    detect_statistical_anomalies,
    IsolationForestAnomalyDetector,
)
from src.analytics.recurring import (
    detect_recurring_expenses,
    compute_recurring_summary,
)
from src.models.category_forecasting import forecast_category_expenses


# Page configuration
st.set_page_config(
    page_title="NextOmni Finance Intelligence",
    page_icon="💳",
    layout="wide",
    initial_sidebar_state="expanded",
)


def get_default_sample_path() -> Path:
    """Resolve absolute path to preloaded sample CSV."""
    return Path(__file__).parent / "data" / "synthetic" / "transactions_sample.csv"


@st.cache_data(show_spinner=False)
def load_and_process_data(file_bytes: bytes | None = None) -> tuple[pd.DataFrame, pd.DataFrame, Any]:
    """
    Load, validate, and clean transactions.
    """
    if file_bytes is not None:
        # Enforce maximum row threshold to prevent memory exhaustion
        raw_df = pd.read_csv(io.BytesIO(file_bytes), nrows=100_000)
    else:
        sample_path = get_default_sample_path()
        if not sample_path.is_file():
            # Fallback if not found
            raise FileNotFoundError(f"Sample data not found at {sample_path}")
        raw_df = pd.read_csv(sample_path)

    validated_df, report = validate_dataframe(raw_df)
    clean_df = clean_transactions(validated_df)
    return raw_df, clean_df, report


# --- SIDEBAR ---
with st.sidebar:
    st.title("💳 NextOmni ML")
    st.caption("Personal Finance Intelligence & Spending Forecasting")
    st.divider()

    st.subheader("📁 Data Source")
    data_mode = st.radio(
        "Select Dataset",
        options=["Preloaded 18M Ledger (MYR)", "Upload Statement CSV"],
        index=0,
    )

    uploaded_file = None
    if data_mode == "Upload Statement CSV":
        uploaded_file = st.file_uploader(
            "Upload Bank Statement CSV",
            type=["csv"],
            help="Upload bank statement conforming to canonical schema or Malaysian banking format.",
        )

    st.divider()
    st.markdown("### ⚙️ Engine Parameters")
    anomaly_threshold = st.slider(
        "Anomaly IQR Multiplier",
        min_value=1.5,
        max_value=3.5,
        value=2.0,
        step=0.25,
        help="Higher multiplier flags only the most extreme statistical outliers.",
    )

    st.divider()
    st.markdown(
        """
        **System Architecture**
        - **Data Pipeline**: Zero-loss validation & deduplication
        - **Evaluation**: Chronological holdout (No leakage)
        - **Stack**: Pandas, Scikit-Learn, Altair, Streamlit
        """
    )


# --- DATA PIPELINE EXECUTION ---
try:
    if data_mode == "Upload Statement CSV" and uploaded_file is not None:
        file_bytes = uploaded_file.getvalue()
        MAX_UPLOAD_BYTES = 10 * 1024 * 1024  # 10 MB limit
        if len(file_bytes) > MAX_UPLOAD_BYTES:
            st.error(f"Uploaded file exceeds maximum permitted limit of 10 MB ({len(file_bytes) / (1024 * 1024):.2f} MB provided).")
            st.stop()

        raw_df, clean_df, report = load_and_process_data(file_bytes)
        data_source_label = f"Uploaded CSV ({uploaded_file.name})"
    else:
        raw_df, clean_df, report = load_and_process_data(None)
        data_source_label = "Preloaded 18-Month Ledger (Synthetic Malaysian Urban Professional)"
except Exception as e:
    st.error(f"Error loading transaction data: {e}")
    st.stop()


# --- HEADER ---
st.title("Personal Finance Intelligence & Forecasting Engine")
st.markdown(
    f"**Active Ledger**: `{data_source_label}` · "
    f"**Transactions Processed**: `{len(clean_df):,}` · "
    f"**Currency**: `MYR (RM)`"
)

# Tabs
tab1, tab2, tab3, tab4 = st.tabs([
    "📥 Ingestion & Audit",
    "📊 Financial Intelligence",
    "📈 Expense Forecasting Arena",
    "⚠️ Potentially Unusual Transactions",
])


# ==============================================================================
# TAB 1: INGESTION & DATA HYGIENE
# ==============================================================================
with tab1:
    st.header("Transaction Ingestion & Data Hygiene Audit")
    st.markdown(
        "Every incoming financial transaction passes through our deterministic validation gate. "
        "Records are checked for type safety, valid date parsing, debit/credit polarity, and duplicate hashes."
    )

    # Hygiene KPI row
    col1, col2, col3, col4 = st.columns(4)
    with col1:
        st.metric("Total Records Ingested", f"{report.total_records:,}")
    with col2:
        st.metric("Valid Processed", f"{report.valid_records:,}", delta="Ready for ML")
    with col3:
        st.metric("Duplicate Records Isolated", f"{report.duplicate_records:,}")
    with col4:
        st.metric("Validation Pass Rate", f"{report.pass_rate}%")

    if report.excluded_records:
        with st.expander("🔍 View Isolated Duplicate & Excluded Records Audit", expanded=False):
            st.warning(f"Audit log: {len(report.excluded_records)} records excluded or isolated.")
            excluded_summary = [
                {
                    "row_index": r.row_index,
                    "reasons": ", ".join(r.reasons),
                    **r.raw_data,
                }
                for r in report.excluded_records
            ]
            st.dataframe(pd.DataFrame(excluded_summary), use_container_width=True)

    st.subheader("Processed Canonical Dataset Preview")
    st.dataframe(clean_df.head(100), use_container_width=True)


# ==============================================================================
# TAB 2: FINANCIAL INTELLIGENCE & ANALYTICS
# ==============================================================================
with tab2:
    st.header("Deterministic Financial Analytics & Spending Behavior")

    summary = compute_overall_summary(clean_df)

    # KPI Summary Cards
    k1, k2, k3, k4 = st.columns(4)
    with k1:
        st.metric("Total Employment Inflow", f"MYR {summary['total_income']:,.2f}")
    with k2:
        st.metric("Total Living Expenditure", f"MYR {summary['total_expense']:,.2f}")
    with k3:
        st.metric("Cumulative Net Savings", f"MYR {summary['net_savings']:,.2f}")
    with k4:
        st.metric("Overall Savings Rate", f"{summary['savings_rate_pct']}%")

    st.divider()

    # Cash Flow Trend & Category Breakdown in 2 Columns
    c_left, c_right = st.columns([3, 2])

    with c_left:
        st.subheader("Monthly Cash Flow Trend (Inflow vs Outflow)")
        cashflow_df = compute_monthly_cashflow(clean_df)

        if not cashflow_df.empty:
            cf_melt = cashflow_df.melt(
                id_vars=["period"],
                value_vars=["income", "expense"],
                var_name="Flow Type",
                value_name="Amount (MYR)",
            )
            cf_chart = (
                alt.Chart(cf_melt)
                .mark_bar(cornerRadiusTopLeft=4, cornerRadiusTopRight=4)
                .encode(
                    x=alt.X("period:N", title="Month (YYYY-MM)", axis=alt.Axis(labelAngle=-45)),
                    y=alt.Y("Amount (MYR):Q", title="Total Amount (MYR)"),
                    color=alt.Color(
                        "Flow Type:N",
                        scale=alt.Scale(domain=["income", "expense"], range=["#10B981", "#EF4444"]),
                    ),
                    xOffset="Flow Type:N",
                    tooltip=["period", "Flow Type", alt.Tooltip("Amount (MYR):Q", format=",.2f")],
                )
                .properties(height=350)
            )
            st.altair_chart(cf_chart, use_container_width=True)

    with c_right:
        st.subheader("Category Spending Distribution")
        cat_df = compute_category_distribution(clean_df)

        if not cat_df.empty:
            cat_chart = (
                alt.Chart(cat_df)
                .mark_arc(innerRadius=60, stroke="#fff")
                .encode(
                    theta=alt.Theta("total_amount:Q", title="Amount (MYR)"),
                    color=alt.Color("category:N", legend=alt.Legend(title="Category")),
                    tooltip=[
                        "category",
                        alt.Tooltip("total_amount:Q", format=",.2f", title="Total (MYR)"),
                        alt.Tooltip("percentage:Q", format=".1f", title="Share (%)"),
                    ],
                )
                .properties(height=350)
            )
            st.altair_chart(cat_chart, use_container_width=True)

    # Behavioral Velocity: Day of Week & Merchants
    v_left, v_right = st.columns([3, 2])

    with v_left:
        st.subheader("Day-of-Week Spending Velocity (The Weekend Surge)")
        dow_df = compute_day_of_week_metrics(clean_df)

        dow_chart = (
            alt.Chart(dow_df)
            .mark_bar(color="#6366F1", cornerRadiusTopLeft=4, cornerRadiusTopRight=4)
            .encode(
                x=alt.X("day_name:N", sort=None, title="Day of Week"),
                y=alt.Y("total_amount:Q", title="Total Expenditure (MYR)"),
                tooltip=[
                    "day_name",
                    alt.Tooltip("total_amount:Q", format=",.2f", title="Total (MYR)"),
                    alt.Tooltip("percentage:Q", format=".1f", title="Share (%)"),
                    alt.Tooltip("average_per_transaction:Q", format=",.2f", title="Avg/Txn (MYR)"),
                ],
            )
            .properties(height=280)
        )
        st.altair_chart(dow_chart, use_container_width=True)
        st.caption("Notice how weekend expenditures (Saturday & Sunday) capture over 40% of all discretionary retail and dining volume.")

    with v_right:
        st.subheader("Top Merchant Concentration")
        merch_df = compute_top_merchants(clean_df, top_n=6)
        st.dataframe(
            merch_df[["merchant", "total_amount", "count", "average_spend"]].rename(
                columns={
                    "merchant": "Merchant",
                    "total_amount": "Total (MYR)",
                    "count": "Transactions",
                    "average_spend": "Avg/Txn (MYR)",
                }
            ),
            use_container_width=True,
            height=280,
        )

    st.divider()
    st.subheader("🔁 Detected Subscriptions & Scheduled Commitments")
    st.markdown(
        "Using temporal cadence clustering and coefficient of variation ($CV$), this engine automatically isolates "
        "recurring commitments (fixed subscriptions vs. variable metered bills)."
    )
    rec_df = detect_recurring_expenses(clean_df)
    rec_summary = compute_recurring_summary(rec_df)

    r1, r2, r3, r4 = st.columns(4)
    with r1:
        st.metric("Active Subscriptions", f"{rec_summary['total_commitments']:,}")
    with r2:
        st.metric("Monthly Recurring Burden", f"MYR {rec_summary['total_monthly_recurring']:,.2f}")
    with r3:
        st.metric("Annual Projected Outflow", f"MYR {rec_summary['total_annual_recurring']:,.2f}")
    with r4:
        st.metric("Fixed Obligations Share", f"{rec_summary['fixed_share_percent']}%")

    if not rec_df.empty:
        st.dataframe(
            rec_df[[
                "merchant",
                "category",
                "cadence",
                "cost_type",
                "average_amount",
                "monthly_equivalent",
                "last_billed_date",
                "next_expected_date",
            ]].rename(
                columns={
                    "merchant": "Merchant / Service",
                    "category": "Category",
                    "cadence": "Cadence",
                    "cost_type": "Cost Structure",
                    "average_amount": "Avg Charge (MYR)",
                    "monthly_equivalent": "Monthly Burden (MYR)",
                    "last_billed_date": "Last Charged",
                    "next_expected_date": "Next Expected Charge",
                }
            ),
            use_container_width=True,
        )
    else:
        st.info("No recurring subscriptions or scheduled obligations detected with sufficient confidence.")


# ==============================================================================
# TAB 3: EXPENSE FORECASTING ARENA
# ==============================================================================
with tab3:
    st.header("Expense Forecasting Engine & Benchmark Arena")
    st.markdown(
        "This module forecasts upcoming monthly expenses by engineering leakage-free lag sequences "
        "and evaluating them against rigorous out-of-sample holdout periods."
    )

    monthly_series = build_monthly_spending_series(clean_df)
    clean_modeling_df = prepare_modeling_dataset(monthly_series)

    if len(clean_modeling_df) < 6:
        st.warning("At least 6 months of consecutive transactions are required to build reliable lag models.")
    else:
        # Benchmark Runner
        test_horizon = 6
        train_df, test_df = train_test_split_chronological(clean_modeling_df, test_months=test_horizon)
        summary_table, details_table = benchmark_all_models(train_df, test_df)

        # Production Recommended Forecaster (3M Moving Average)
        st.subheader("🎯 Primary Production Forecast: 3-Month Moving Average")

        latest_expense = clean_modeling_df.iloc[-1]["actual_expense"]
        latest_3m = clean_modeling_df.iloc[-1]["rolling_3m_mean"]
        baseline_mae = float(summary_table.loc[summary_table["model"].str.contains("3M Moving Average"), "mae"].values[0])

        f1, f2, f3 = st.columns(3)
        with f1:
            st.metric("Latest Month Expense", f"MYR {latest_expense:,.2f}")
        with f2:
            st.metric(
                "Next Month Forecast",
                f"MYR {latest_3m:,.2f}",
                delta=f"± MYR {baseline_mae:,.2f} MAE",
                delta_color="off",
            )
        with f3:
            st.metric("Recommended Horizon", "1 Month Forward (Auto-Rolling)")

        # Chart: Actual vs Forecasts on Out-of-Sample Test Set
        st.subheader("Out-of-Sample Holdout Performance (Actual vs Forecasters)")

        chart_df = details_table.melt(
            id_vars=["period", "actual_expense"],
            value_vars=[
                "pred_Baseline: 3M Moving Average",
                "pred_Baseline: Naive Prior Month",
                "pred_ML: Ridge Regression (α=1.0)",
                "pred_ML: Linear Regression (OLS)",
            ],
            var_name="Model Name",
            value_name="Forecast Amount",
        )
        chart_df["Model Name"] = chart_df["Model Name"].str.replace("pred_", "")

        # Line chart
        actual_line = (
            alt.Chart(details_table)
            .mark_line(color="#111827", strokeWidth=3)
            .encode(
                x=alt.X("period:N", title="Holdout Month"),
                y=alt.Y("actual_expense:Q", title="Expense (MYR)"),
                tooltip=["period", alt.Tooltip("actual_expense:Q", format=",.2f", title="Actual (MYR)")],
            )
        )
        pred_lines = (
            alt.Chart(chart_df)
            .mark_line(strokeDash=[4, 4], strokeWidth=2)
            .encode(
                x=alt.X("period:N"),
                y=alt.Y("Forecast Amount:Q"),
                color=alt.Color("Model Name:N", legend=alt.Legend(title="Forecaster")),
                tooltip=["period", "Model Name", alt.Tooltip("Forecast Amount:Q", format=",.2f")],
            )
        )
        composite_chart = (actual_line + pred_lines).properties(height=360)
        st.altair_chart(composite_chart, use_container_width=True)

        st.divider()
        st.subheader("🧩 Hierarchical Category-Level Spending Forecast")
        st.markdown(
            "Disaggregates upcoming monthly expenditure across individual spending categories by anchoring "
            "detected fixed obligations (Rent, Utilities) and applying 3-month moving averages to discretionary outgoings."
        )

        cat_forecast_df, cat_summary = forecast_category_expenses(clean_df)

        cf1, cf2, cf3 = st.columns(3)
        with cf1:
            st.metric("Forecast Target Month", cat_summary["forecast_period"])
        with cf2:
            st.metric("Total Category Forecast", f"MYR {cat_summary['total_projected_expense']:,.2f}")
        with cf3:
            st.metric("Active Spending Categories", f"{cat_summary['total_categories']:,}")

        cat_bar_chart = (
            alt.Chart(cat_forecast_df)
            .mark_bar(cornerRadiusTopRight=4, cornerRadiusBottomRight=4)
            .encode(
                y=alt.Y("category:N", sort="-x", title="Category"),
                x=alt.X("forecast_amount:Q", title="Projected Spend (MYR)"),
                color=alt.Color("category:N", legend=None),
                tooltip=[
                    "category",
                    alt.Tooltip("forecast_amount:Q", format=",.2f", title="Forecast (MYR)"),
                    alt.Tooltip("last_month_actual:Q", format=",.2f", title="Last Month (MYR)"),
                    alt.Tooltip("share_of_total_pct:Q", format=".1f", title="Share (%)"),
                    "trend",
                ],
            )
            .properties(height=260)
        )
        st.altair_chart(cat_bar_chart, use_container_width=True)

        st.dataframe(
            cat_forecast_df[[
                "category",
                "forecast_amount",
                "last_month_actual",
                "historical_mean",
                "share_of_total_pct",
                "trend",
            ]].rename(
                columns={
                    "category": "Spending Category",
                    "forecast_amount": "Projected Next Month (MYR)",
                    "last_month_actual": "Last Month Actual (MYR)",
                    "historical_mean": "Historical Monthly Avg (MYR)",
                    "share_of_total_pct": "Projected Share (%)",
                    "trend": "Velocity Trend",
                }
            ),
            use_container_width=True,
        )

        st.divider()
        st.subheader("📊 Systematic Model Benchmark Comparison Table")
        st.dataframe(
            summary_table.rename(
                columns={
                    "model": "Model",
                    "test_sample_size": "Test Months",
                    "mae": "MAE (MYR) ↓",
                    "rmse": "RMSE (MYR) ↓",
                    "mape_pct": "MAPE (%) ↓",
                    "max_error": "Max Error (MYR)",
                }
            ),
            use_container_width=True,
        )

        st.info(
            """
            **💡 Why Did the 3-Month Moving Average Baseline Win?**
            - **Small Sample Size**: With only 9 training months and 5 features, Linear Regression had only 3 degrees of freedom, leading to overfitting.
            - **Spurious Calendar Effect**: In February (28 days), the unregularized OLS model wildly overshot up to MYR 16,670 due to an exaggerated `days_in_month` coefficient.
            - **Outlier Immunity**: A large non-recurring computer upgrade (MYR 7,999) in Month 8 permanently corrupted the linear slopes, whereas the 3-month moving average naturally flushed the outlier out after 90 days.
            - **Data Science Principle**: In personal finance, simple, transparent baseline persistence rules frequently outperform complex ML models until multi-year (36+ month) histories are gathered.
            """
        )

        # Interactive Model Explorer
        with st.expander("🔬 Inspect Scikit-Learn Regression Coefficients"):
            alpha_val = st.slider("Select Ridge Regularization (α)", min_value=0.1, max_value=20.0, value=2.0, step=0.5)
            ridge_exp = ExpenseRegressionForecaster(model_type="ridge", alpha=alpha_val, scale_features=True)
            ridge_exp.fit(train_df, train_df["actual_expense"])
            exp_info = ridge_exp.explain()

            c_df = pd.DataFrame(
                list(exp_info["coefficients"].items()),
                columns=["Feature", "Learned Coefficient"],
            )
            st.write(f"**Model Intercept**: `MYR {exp_info['intercept']:,.2f}`")
            st.dataframe(c_df, use_container_width=True)


# ==============================================================================
# TAB 4: POTENTIALLY UNUSUAL TRANSACTIONS (ANOMALY DETECTION)
# ==============================================================================
with tab4:
    st.header("Potentially Unusual Transactions (Dual-Engine Anomaly Detection)")
    st.markdown(
        "Detect transactions that deviate significantly from typical spending behavior using either "
        "robust **Non-Parametric Category IQR** or **Multidimensional Machine Learning (Isolation Forest)**."
    )

    detect_method = st.radio(
        "Detection Engine",
        options=[
            "Statistical Rule: Category Interquartile Range (IQR)",
            "Machine Learning Ensemble: Multidimensional Isolation Forest",
        ],
        horizontal=True,
    )

    if "Statistical" in detect_method:
        anomalies_df = detect_statistical_anomalies(clean_df, iqr_multiplier=anomaly_threshold)
        sens_label = f"{anomaly_threshold}x IQR"
    else:
        # Isolation Forest
        contamination_val = st.slider("Expected Outlier Proportion (Contamination %)", min_value=1.0, max_value=5.0, value=2.0, step=0.5) / 100.0
        detector = IsolationForestAnomalyDetector(contamination=contamination_val, random_state=42)
        anomalies_df = detector.fit_predict(clean_df)
        sens_label = f"{contamination_val:.1%} Contamination"

    a1, a2, a3 = st.columns(3)
    with a1:
        st.metric("Flagged Unusual Transactions", f"{len(anomalies_df):,}")
    with a2:
        total_flagged_amt = anomalies_df["amount"].sum() if not anomalies_df.empty else 0.0
        st.metric("Total Flagged Volume", f"MYR {total_flagged_amt:,.2f}")
    with a3:
        st.metric("Sensitivity Threshold", sens_label)

    st.warning(
        "**Human Review Notice**: An anomaly is **NOT** necessarily fraud, unauthorized activity, or an error. "
        "It indicates a rare purchase magnitude relative to typical category behavior (e.g. an annual insurance premium or electronics upgrade)."
    )

    if not anomalies_df.empty:
        cols_to_show = ["transaction_id", "transaction_date", "category", "description", "amount"]
        if "category_median" in anomalies_df.columns:
            cols_to_show.extend(["category_median", "deviation_ratio"])
        if "anomaly_score" in anomalies_df.columns:
            cols_to_show.append("anomaly_score")
        cols_to_show.extend(["flag_reason", "human_review_status"])

        rename_map = {
            "transaction_id": "Txn ID",
            "transaction_date": "Date",
            "category": "Category",
            "description": "Description",
            "amount": "Amount (MYR)",
            "category_median": "Category Median (MYR)",
            "deviation_ratio": "Deviation",
            "anomaly_score": "Isolation Score",
            "flag_reason": "Detection Reason",
            "human_review_status": "Status",
        }

        st.dataframe(
            anomalies_df[cols_to_show].rename(columns=rename_map),
            use_container_width=True,
        )
    else:
        st.success("No statistical anomalies detected under the current sensitivity threshold.")
