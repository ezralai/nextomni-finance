"""
Unit Tests for Localized Malaysian NLP, Demographic Benchmarks, and Spending Personas.
"""

from __future__ import annotations

import pandas as pd
import pytest

from src.features.malaysian_nlp import (
    classify_malaysian_merchant,
    detect_payment_rail,
    normalize_merchant_description,
)
from src.analytics.malaysian_benchmarks import (
    analyze_payday_cycle,
    evaluate_spending_against_dosm_benchmark,
)
from src.models.spending_personas import (
    classify_spending_persona,
    extract_behavioral_features,
)


# ==============================================================================
# 1. Malaysian NLP & Merchant Classification Tests
# ==============================================================================

def test_detect_payment_rail():
    """Verify detection of DuitNow QR, TNG, and FPX payment rails."""
    assert detect_payment_rail("MBB DUITNOW QR - KEDAI KOPI") == "DUITNOW_QR"
    assert detect_payment_rail("PAYNET QR REF 991823") == "DUITNOW_QR"
    assert detect_payment_rail("TNG EWALLET AUTO RELOAD") == "TNG_EWALLET"
    assert detect_payment_rail("TOUCH N GO RFID TOLL") == "TNG_EWALLET"
    assert detect_payment_rail("FPX B2C BILLPLZ PAYMENT") == "FPX"
    assert detect_payment_rail("CASH WITHDRAWAL ATM") == "CARD_ATM"
    assert detect_payment_rail("SALARY PAYMENT JANM") == "STANDARD_LEDGER"


def test_normalize_merchant_description():
    """Verify stripping of payment prefix noise, reference codes, and terminal IDs."""
    raw = "MBB DUITNOW QR - PETRONAS MESRA 1829 KL REF: 99182"
    cleaned = normalize_merchant_description(raw)
    assert "PETRONAS" in cleaned
    assert "MBB" not in cleaned
    assert "REF" not in cleaned


def test_classify_malaysian_merchant_gazetteer():
    """Verify classification of iconic Malaysian merchants across F&B, Fuel, and Groceries."""
    # F&B Hawker / Mamak
    mamak_res = classify_malaysian_merchant("REST PELITA ROTI CANAI & TEH TARIK")
    assert mamak_res["category_l1"] == "food_and_beverage"
    assert mamak_res["is_localized_match"] is True

    # Fuel & Automotive
    fuel_res = classify_malaysian_merchant("PETRONAS PRIMAX 95 BANGI")
    assert fuel_res["category_l1"] == "transportation"
    assert fuel_res["category_l2"] == "fuel"
    assert fuel_res["normalized_merchant"] == "Petronas"

    # Expressway Tolls
    toll_res = classify_malaysian_merchant("PLUS HIGHWAY TOLL PLAZA JURU")
    assert toll_res["category_l1"] == "transportation"
    assert toll_res["category_l2"] == "toll"

    # Supermarkets
    grocer_res = classify_malaysian_merchant("99 SPEEDMART TAMAN MELAWATI")
    assert grocer_res["category_l1"] == "food_and_beverage"
    assert grocer_res["category_l2"] == "groceries"

    # DIY & Retail
    diy_res = classify_malaysian_merchant("MR DIY MID VALLEY")
    assert diy_res["category_l1"] == "shopping"
    assert diy_res["normalized_merchant"] == "MR DIY"


# ==============================================================================
# 2. Demographic DOSM Benchmark Evaluation Tests
# ==============================================================================

def test_evaluate_spending_against_dosm_benchmark():
    """Verify Z-score calculations and contextual advice against M40 baseline."""
    category_totals = {
        "food_and_beverage": 1800.0,
        "transportation": 900.0,
        "housing_and_utilities": 1200.0,
        "groceries": 700.0,
        "savings": 1400.0,
    }
    total_income = 6000.0

    eval_result = evaluate_spending_against_dosm_benchmark(
        category_totals=category_totals,
        total_income=total_income,
        state_code="MY-10",
        income_tier="M40",
    )

    assert eval_result["income_tier"] == "M40"
    assert eval_result["state_code"] == "MY-10"
    assert len(eval_result["categories"]) > 0

    # Food: 1800 / 6000 = 30.0%
    # M40 Baseline Food: 22.4 * 1.05 = ~23.52%
    food_cat = next(c for c in eval_result["categories"] if c["category"] == "food_and_beverage")
    assert food_cat["user_pct"] == 30.0
    assert food_cat["z_score"] > 1.0  # Elevated spending


# ==============================================================================
# 3. Payday Cycle & Velocity Tests
# ==============================================================================

def test_analyze_payday_cycle():
    """Verify detection of post-payday surge velocity."""
    txns = pd.DataFrame([
        # Post-payday window (Day 26 to Day 5) - High volume
        {"transaction_date": "2025-01-28", "amount": 250.0, "type": "expense", "category": "dining"},
        {"transaction_date": "2025-01-29", "amount": 300.0, "type": "expense", "category": "shopping"},
        {"transaction_date": "2025-02-01", "amount": 200.0, "type": "expense", "category": "dining"},
        # Mid-month window (Day 6 to Day 20) - Low/moderate
        {"transaction_date": "2025-02-10", "amount": 30.0, "type": "expense", "category": "dining"},
        {"transaction_date": "2025-02-15", "amount": 40.0, "type": "expense", "category": "groceries"},
    ])

    payday_res = analyze_payday_cycle(txns)
    assert payday_res["detected_cycle"] == "MALAYSIAN_END_OF_MONTH"
    assert payday_res["surge_velocity_ratio"] > 1.5
    assert "Payday surge detected" in payday_res["recommendation"]


# ==============================================================================
# 4. Spending Persona Classifier Tests
# ==============================================================================

def test_classify_spending_persona_urban_commuter():
    """Verify user with high transport/fuel is classified as Urban Commuter."""
    txns = pd.DataFrame([
        {"transaction_date": "2025-01-05", "amount": 100.0, "type": "expense", "category": "fuel"},
        {"transaction_date": "2025-01-12", "amount": 100.0, "type": "expense", "category": "petrol"},
        {"transaction_date": "2025-01-15", "amount": 80.0, "type": "expense", "category": "toll"},
        {"transaction_date": "2025-01-20", "amount": 50.0, "type": "expense", "category": "transit"},
        {"transaction_date": "2025-01-22", "amount": 70.0, "type": "expense", "category": "dining"},
        {"transaction_date": "2025-01-25", "amount": 1500.0, "type": "income", "category": "salary"},
    ])

    persona = classify_spending_persona(txns)
    assert persona["id"] == "urban_commuter"
    assert "Commuter" in persona["title"]
    assert persona["confidence"] >= 0.60


def test_classify_spending_persona_foodie():
    """Verify user with high dining/café is classified as Foodie & Café Hopper."""
    txns = pd.DataFrame([
        {"transaction_date": "2025-01-05", "amount": 300.0, "type": "expense", "category": "dining"},
        {"transaction_date": "2025-01-06", "amount": 250.0, "type": "expense", "category": "restaurant"},
        {"transaction_date": "2025-01-11", "amount": 180.0, "type": "expense", "category": "cafe"},
        {"transaction_date": "2025-01-15", "amount": 50.0, "type": "expense", "category": "groceries"},
        {"transaction_date": "2025-01-25", "amount": 1500.0, "type": "income", "category": "salary"},
    ])

    persona = classify_spending_persona(txns)
    assert persona["id"] == "foodie_cafe_hopper"
    assert "Foodie" in persona["title"]
