"""
Localized Malaysian Financial NLP & Merchant Entity Extraction Engine.

Handles multi-lingual Malaysian bank and card statement descriptions
(Bahasa Melayu, English, Manglish, Chinese, and Tamil transliterations),
identifying payment channels (DuitNow QR, TNG, FPX) and mapping merchant entities
to standardized financial categories.
"""

from __future__ import annotations

import re
from typing import Any


# Payment Rail Regex Signatures
PAYMENT_RAIL_SIGNATURES: list[tuple[re.Pattern, str]] = [
    (re.compile(r"\b(duitnow\s*qr|paynet\s*qr|mbb\s*qr|qr\s*pay)\b", re.I), "DUITNOW_QR"),
    (re.compile(r"\b(tng\s*(?:ewallet|reload|rfid)?|touch\s*['’]?n\s*go)\b", re.I), "TNG_EWALLET"),
    (re.compile(r"\b(fpx(?:\s*b2c)?|billplz|toyyibpay|curlec)\b", re.I), "FPX"),
    (re.compile(r"\b(grabpay|boost\s*(?:ewallet)?|shopeepay)\b", re.I), "EWALLET_OTHER"),
    (re.compile(r"\b(visa|mastercard|meps\s*atm|cash\s*withdrawal)\b", re.I), "CARD_ATM"),
]

# High-Precision Malaysian Merchant & Venue Entity Gazetteer
# (Pattern regex, Normalized Entity, L1 Category, L2 Subcategory)
MALAYSIAN_MERCHANT_GAZETTEER: list[tuple[re.Pattern, str, str, str]] = [
    # Fuel Stations & Automotive
    (re.compile(r"\b(petronas(?:\s*mesra|\s*primax)?|setel)\b", re.I), "Petronas", "transportation", "fuel"),
    (re.compile(r"\b(shell(?:\s*select|\s*v-?power)?)\b", re.I), "Shell", "transportation", "fuel"),
    (re.compile(r"\b(petron(?:\s*blaze|\s*treats)?)\b", re.I), "Petron", "transportation", "fuel"),
    (re.compile(r"\b(caltex(?:\s*starcash)?|bhp(?:\s*petrol)?)\b", re.I), "Caltex/BHP", "transportation", "fuel"),

    # Expressway Tolls & Public Commute
    (re.compile(r"\b(plus\s*(?:highway|expressway|toll)|ldp\s*toll|kesas|sprint\s*toll|smart\s*tunnel|mex\s*toll|duke\s*toll)\b", re.I), "Expressway Toll", "transportation", "toll"),
    (re.compile(r"\b(rapid\s*kl|my50|prasarana|mrt|lrt|monorail|ktm\s*komuter)\b", re.I), "RapidKL Transit", "transportation", "public_transit"),
    (re.compile(r"\b(grab\s*car|grab\s*ride|airasia\s*ride|maxim\s*ride)\b", re.I), "E-Hailing Ride", "transportation", "e_hailing"),

    # Hawker, Mamak, Kopitiam & Local Dining
    (re.compile(r"\b(roti\s*canai|nasi\s*kandar|nasi\s*lemak|mee\s*goreng|maggi\s*goreng|teh\s*tarik|thosai|murtabak)\b", re.I), "Mamak / Warung Hawker", "food_and_beverage", "hawker_mamak"),
    (re.compile(r"\b(restoran\s*pelita|nasi\s*kandar\s*kayu|restoran\s*mahabub|restoran\s*subaidah|syed\s*bistro)\b", re.I), "Pelita / Kayu Nasi Kandar", "food_and_beverage", "hawker_mamak"),
    (re.compile(r"\b(kopitiam|kopi\s*o|cham|bak\s*kut\s*teh|char\s*kway\s*teow|wan\s*tan\s*mee|pan\s*mee|yong\s*tau\s*foo)\b", re.I), "Traditional Kopitiam", "food_and_beverage", "kopitiam"),
    (re.compile(r"\b(oriental\s*kopi|hometown\s*hainam|oldtown\s*white\s*coffee|toast\s*box)\b", re.I), "Oriental Kopi / OldTown", "food_and_beverage", "kopitiam"),
    (re.compile(r"\b(mcdonald['’]?s|mcd|kfc|marrybrown|a&w|texas\s*chicken|subway|burger\s*king)\b", re.I), "Fast Food Chain", "food_and_beverage", "fast_food"),
    (re.compile(r"\b(starbucks|zus\s*coffee|gigi\s*coffee|kenangan\s*coffee|chagee|mixue|tealive|gong\s*cha)\b", re.I), "Café & Bubble Tea", "food_and_beverage", "cafe_beverage"),
    (re.compile(r"\b(haidilao|suki-?ya|seoul\s*garden|sushi\s*king|sushi\s*zanmai|bar-?b-?q\s*plaza)\b", re.I), "Banquet & Hotpot Dining", "food_and_beverage", "casual_dining"),

    # Supermarkets, Hypermarkets & Groceries
    (re.compile(r"\b(99\s*speedmart|speedmart)\b", re.I), "99 Speedmart", "food_and_beverage", "groceries"),
    (re.compile(r"\b(lotus['’]?s|tesco)\b", re.I), "Lotus's Supermarket", "food_and_beverage", "groceries"),
    (re.compile(r"\b(giant\s*(?:hypermarket)?|aeon\s*big|aeon\s*supermarket|econsave|mydin|nsk\s*(?:trade\s*city)?)\b", re.I), "Hypermarket Retailer", "food_and_beverage", "groceries"),
    (re.compile(r"\b(jaya\s*grocer|village\s*grocer|bens\s*independent|qra|cold\s*storage)\b", re.I), "Premium Grocer", "food_and_beverage", "groceries"),

    # Convenience Stores
    (re.compile(r"\b(familymart|7-?eleven|kk\s*(?:super\s*)?mart|cu\s*mart|mynews(?:\.com)?)\b", re.I), "Convenience Store", "food_and_beverage", "convenience"),

    # Home, Variety Retail & DIY
    (re.compile(r"\b(mr\s*d\.?i\.?y\.?|mr\s*diy|mr\s*dollar|mr\s*toy)\b", re.I), "MR DIY", "shopping", "home_essentials"),
    (re.compile(r"\b(eco-?shop|kaison|ninso|daiso|nitori|ikea)\b", re.I), "Home Furnishing & Value Retail", "shopping", "home_essentials"),

    # Health, Wellness & Pharmacy
    (re.compile(r"\b(watsons|guardian|caring\s*pharmacy|big\s*pharmacy|alpro\s*pharmacy)\b", re.I), "Pharmacy & Personal Care", "health_and_wellness", "pharmacy"),

    # E-Commerce & Marketplace
    (re.compile(r"\b(shopee(?:\s*pay)?|lazada|tiktok\s*shop|taobao)\b", re.I), "E-Commerce Marketplace", "shopping", "ecommerce"),

    # Telecommunications & Utilities
    (re.compile(r"\b(maxis|hotlink|celcom|digi|u\s*mobile|yes\s*5g|unifi|tm\s*broadband)\b", re.I), "Telco & Internet", "utilities", "telecom"),
    (re.compile(r"\b(tenaga\s*nasional|tnb|air\s*selangor|syabas|indah\s*water|sada|swk\s*energy)\b", re.I), "Public Utilities", "utilities", "electricity_water"),
]


def detect_payment_rail(description: str) -> str:
    """
    Detect the underlying Malaysian payment channel/rail from statement micro-copy.
    """
    if not description:
        return "UNKNOWN"

    for pattern, rail in PAYMENT_RAIL_SIGNATURES:
        if pattern.search(description):
            return rail

    return "STANDARD_LEDGER"


def normalize_merchant_description(description: str) -> str:
    """
    Sanitize and strip noise from raw Malaysian banking statement strings.
    Strips card transaction IDs, terminal numbers, and generic gateway tokens.
    """
    if not description:
        return ""

    text = description.strip()

    # Remove standard payment prefix noise
    noise_prefixes = [
        r"^MBB\s*(?:DUITNOW\s*QR|QR|TRSF|CARD)\s*-\s*",
        r"^CIMB\s*(?:CLICKS|DEBIT|QR)\s*-\s*",
        r"^TNG\s*(?:RELOAD|QR|PAYMENT)\s*-\s*",
        r"^PAYNET\s*(?:QR|REF\s*\d+)?\s*-\s*",
        r"^FPX\s*(?:TRANSACTION|PAYMENT)?\s*-\s*",
    ]
    for np in noise_prefixes:
        text = re.sub(np, "", text, flags=re.I)

    # Strip reference sequences like 'REF: 9918231', 'TRACE: 102938'
    text = re.sub(r"\b(?:REF|TRACE|TERM|AUTH|STAN)\s*:?\s*[A-Z0-9_-]+\b", "", text, flags=re.I)

    # Strip trailing numeric sequences (terminal/store IDs, e.g. 'PETRONAS 1928 KL')
    text = re.sub(r"\s+\d{4,}\s*", " ", text)

    # Normalize whitespace
    text = re.sub(r"\s+", " ", text).strip()
    return text


def classify_malaysian_merchant(description: str) -> dict[str, Any]:
    """
    Classify a Malaysian transaction into standardized entity and category hierarchies.

    Parameters
    ----------
    description : str
        Raw or partially cleaned transaction description.

    Returns
    -------
    dict[str, Any]
        Dictionary with keys:
        - normalized_merchant: str
        - category_l1: str
        - category_l2: str
        - payment_channel: str
        - confidence: float
    """
    cleaned_desc = normalize_merchant_description(description)
    rail = detect_payment_rail(description)

    # Stage 1: Match against Malaysian Merchant Gazetteer
    for pattern, entity_name, cat_l1, cat_l2 in MALAYSIAN_MERCHANT_GAZETTEER:
        if pattern.search(description) or pattern.search(cleaned_desc):
            return {
                "normalized_merchant": entity_name,
                "category_l1": cat_l1,
                "category_l2": cat_l2,
                "payment_channel": rail,
                "confidence": 0.95,
                "is_localized_match": True,
            }

    # Fallback heuristic for generic descriptions
    return {
        "normalized_merchant": cleaned_desc or "Uncategorized Merchant",
        "category_l1": "uncategorized",
        "category_l2": "general",
        "payment_channel": rail,
        "confidence": 0.40,
        "is_localized_match": False,
    }
