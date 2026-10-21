"""
Malaysian Consumer Spending Persona Clustering & Archetype Classifier.

Extracts normalized behavioral feature vectors from user transactions and assigns
archetypes calibrated to Malaysian daily lifestyle dynamics:
- The Urban Commuter (Tolls, Petrol, RapidKL)
- The Foodie & Café Hopper (Mamak, Kopitiam, Weekend dining)
- The Pragmatic Family Anchor (Hypermarkets, Utilities, 99 Speedmart)
- The Digital Native / Deal Hunter (Shopee, Lazada, FPX)
- The Frugal Builder (High savings rate, disciplined living)
"""

from __future__ import annotations

from typing import Any
import numpy as np
import pandas as pd


PERSONA_ARCHETYPES: dict[str, dict[str, Any]] = {
    "urban_commuter": {
        "id": "urban_commuter",
        "title": "Urban Commuter",
        "badge": "🚗 Commuter & Transit",
        "icon": "Car",
        "description": "Your spending is heavily driven by daily commuting, expressway tolls, fuel, and quick meals on the go.",
        "traits": [
            "Transport, Fuel & Tolls exceed 18% of your overall budget",
            "Weekday morning spending spikes for fuel and breakfast",
            "Active Touch 'n Go and RapidKL usage",
        ],
        "financial_tip": "Look into Touch 'n Go RFID automated top-ups and cash-back fuel cards to save RM 30–RM 60 monthly.",
    },
    "foodie_cafe_hopper": {
        "id": "foodie_cafe_hopper",
        "title": "Foodie & Café Hopper",
        "badge": "🍜 Foodie & Café Hopper",
        "icon": "Coffee",
        "description": "You love exploring Malaysian food culture, frequenting mamak hangouts, kopitiams, and artisan cafés.",
        "traits": [
            "Dining, hawker, and café expenses account for over 30% of total outflow",
            "High frequency of DuitNow QR micro-transactions",
            "Pronounced weekend dining surges with friends & family",
        ],
        "financial_tip": "Cap spontaneous supper runs and pack lunch two days a week to redirect ~RM 200 into monthly savings.",
    },
    "family_anchor": {
        "id": "family_anchor",
        "title": "Pragmatic Family Anchor",
        "badge": "🏡 Pragmatic Family Anchor",
        "icon": "Home",
        "description": "Your financial priorities center on household sustenance, regular grocery runs, utilities, and dependable family care.",
        "traits": [
            "Supermarket and grocery spending (Lotus's, Giant, 99 Speedmart) exceeds 25%",
            "Stable utility and recurring bill payments (TNB, Unifi, Water)",
            "Predictable, planned weekly expenditure rhythm",
        ],
        "financial_tip": "Utilize supermarket loyalty points and bulk purchases on staples to capture 5–8% annual cash rebates.",
    },
    "digital_native": {
        "id": "digital_native",
        "title": "Digital Native & Deal Hunter",
        "badge": "📦 Digital Native & Deal Hunter",
        "icon": "ShoppingBag",
        "description": "You are a savvy modern spender who leverages e-commerce platforms, flash sales, and digital payment channels.",
        "traits": [
            "E-commerce (Shopee, Lazada, TikTok Shop) forms over 18% of expenses",
            "High FPX and digital wallet utilization",
            "Spending peaks around double-date campaign days (10.10, 11.11, payday sales)",
        ],
        "financial_tip": "Implement a 24-hour cooling-off rule before checkout to eliminate impulse e-commerce purchases.",
    },
    "frugal_builder": {
        "id": "frugal_builder",
        "title": "Frugal Wealth Builder",
        "badge": "🌱 Frugal Wealth Builder",
        "icon": "TrendingUp",
        "description": "You demonstrate disciplined financial restraint, maintaining a high savings surplus and minimizing lifestyle creep.",
        "traits": [
            "Consistently maintains a savings rate of 35% or higher",
            "Low discretionary outflow and controlled impulse spending",
            "Steady capital accumulation for long-term independence",
        ],
        "financial_tip": "Ensure surplus cash is working in high-yield instruments or diversified index funds rather than stagnant low-yield accounts.",
    },
}


def extract_behavioral_features(df: pd.DataFrame) -> dict[str, float]:
    """
    Extract normalized behavioral spending features from clean transactions.

    Returns
    -------
    dict[str, float]
        Dictionary of financial behavior indicators.
    """
    if df.empty:
        return {
            "dining_ratio": 0.0,
            "groceries_ratio": 0.0,
            "transport_ratio": 0.0,
            "shopping_ratio": 0.0,
            "weekend_skew": 0.0,
            "savings_rate": 0.0,
            "total_income": 0.0,
            "total_expense": 0.0,
        }

    income_mask = df["type"] == "income"
    expense_mask = df["type"] == "expense"

    total_income = float(df.loc[income_mask, "amount"].sum())
    total_expense = float(df.loc[expense_mask, "amount"].sum())
    net_savings = max(0.0, total_income - total_expense)
    savings_rate = (net_savings / total_income) if total_income > 0 else 0.0

    denom = total_expense if total_expense > 0 else 1.0

    expenses = df[expense_mask]
    cat_lower = expenses["category"].str.lower().fillna("")

    # Category masks
    dining_keywords = ["food", "beverage", "dining", "restaurant", "cafe", "hawker"]
    groceries_keywords = ["grocery", "groceries", "supermarket", "speedmart"]
    transport_keywords = ["transport", "transit", "fuel", "petrol", "toll"]
    shopping_keywords = ["shopping", "ecommerce", "shopee", "retail"]

    is_dining = cat_lower.apply(lambda x: any(k in x for k in dining_keywords))
    is_groceries = cat_lower.apply(lambda x: any(k in x for k in groceries_keywords))
    is_transport = cat_lower.apply(lambda x: any(k in x for k in transport_keywords))
    is_shopping = cat_lower.apply(lambda x: any(k in x for k in shopping_keywords))

    dining_amount = float(expenses.loc[is_dining, "amount"].sum())
    groceries_amount = float(expenses.loc[is_groceries, "amount"].sum())
    transport_amount = float(expenses.loc[is_transport, "amount"].sum())
    shopping_amount = float(expenses.loc[is_shopping, "amount"].sum())

    # Weekend vs weekday skew
    dates = pd.to_datetime(expenses["transaction_date"])
    is_weekend = dates.dt.dayofweek >= 5
    weekend_amount = float(expenses.loc[is_weekend, "amount"].sum())
    weekend_skew = (weekend_amount / denom) if denom > 0 else 0.0

    return {
        "dining_ratio": round(dining_amount / denom, 4),
        "groceries_ratio": round(groceries_amount / denom, 4),
        "transport_ratio": round(transport_amount / denom, 4),
        "shopping_ratio": round(shopping_amount / denom, 4),
        "weekend_skew": round(weekend_skew, 4),
        "savings_rate": round(savings_rate, 4),
        "total_income": round(total_income, 2),
        "total_expense": round(total_expense, 2),
    }


def classify_spending_persona(df: pd.DataFrame) -> dict[str, Any]:
    """
    Score user transaction features against Malaysian financial archetypes
    and return the best-matching spending persona.
    """
    features = extract_behavioral_features(df)

    if features["total_expense"] == 0.0 and features["total_income"] == 0.0:
        persona = PERSONA_ARCHETYPES["frugal_builder"].copy()
        persona["confidence"] = 0.50
        persona["features"] = features
        return persona

    # Affinity score calculation for each archetype
    scores: dict[str, float] = {
        # Frugal Builder: rewarded for high savings rate
        "frugal_builder": features["savings_rate"] * 2.5,
        # Foodie & Café Hopper: rewarded for dining ratio and weekend skew
        "foodie_cafe_hopper": (features["dining_ratio"] * 2.2) + (features["weekend_skew"] * 0.8),
        # Urban Commuter: rewarded for transport ratio
        "urban_commuter": features["transport_ratio"] * 3.0,
        # Pragmatic Family Anchor: rewarded for grocery ratio
        "family_anchor": features["groceries_ratio"] * 2.8,
        # Digital Native: rewarded for shopping ratio
        "digital_native": features["shopping_ratio"] * 2.8,
    }

    # Find highest scoring archetype
    best_persona_key = max(scores, key=scores.get)  # type: ignore[arg-type]
    raw_score = scores[best_persona_key]

    # Normalize confidence into [0.60, 0.98]
    confidence = round(min(0.98, max(0.60, 0.50 + (raw_score * 0.4))), 2)

    result = PERSONA_ARCHETYPES[best_persona_key].copy()
    result["confidence"] = confidence
    result["features"] = features
    return result
