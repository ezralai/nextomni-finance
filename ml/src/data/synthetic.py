"""
Synthetic Financial Transaction Data Generator.

Generates realistic, labeled personal finance transactions across an 18-month timeline.
Includes deterministic recurring bills, seasonal spending, and deliberate anomalies
for evaluating data-cleaning pipelines, forecasting models, and anomaly detectors.
"""

from __future__ import annotations

import csv
import datetime
import random
from pathlib import Path
from typing import Any

# Seed for absolute reproducibility across test runs and educational exploration
RANDOM_SEED = 42

CANONICAL_COLUMNS = [
    "transaction_id",
    "transaction_date",
    "description",
    "merchant",
    "amount",
    "currency",
    "type",
    "category",
    "account",
    "reference_number",
    "is_synthetic",
]


def generate_synthetic_transactions(
    start_date: datetime.date = datetime.date(2025, 1, 1),
    num_months: int = 18,
    currency: str = "MYR",
    account_name: str = "Maybank Premier",
    seed: int = RANDOM_SEED,
) -> list[dict[str, Any]]:
    """
    Generate realistic multi-month transaction ledger.

    Embedded behaviors:
    1. Monthly Salary credited on the 25th of every month.
    2. Fixed recurring monthly expenses (Rent on 1st, Utilities on 5th, Subscriptions on 15th).
    3. Daily living expenses (Groceries, Dining, Transport) with weekend variance.
    4. Deliberate anomalies (high-value electronics, duplicate transaction, edge cases).
    """
    random.seed(seed)
    transactions: list[dict[str, Any]] = []
    txn_counter = 1000

    current_date = start_date
    end_date = start_date + datetime.timedelta(days=num_months * 30.5)

    merchants_by_category = {
        "groceries": ["Jaya Grocer", "Village Grocer", "Lotus's", "AEON Supermarket"],
        "dining": ["Starbucks Coffee", "Zus Coffee", "Nando's", "Din Tai Fung", "Kopitiam", "GrabFood"],
        "transportation": ["Grab Ride", "Touch 'n Go RFID", "Petronas Gas", "Shell Fuel"],
        "utilities": ["Tenaga Nasional Berhad", "Air Selangor Water", "Maxis Fibre Home Internet"],
        "entertainment": ["Netflix Subscription", "Spotify Family", "GSC Cinemas", "Steam Games"],
        "shopping": ["Uniqlo Mid Valley", "Shopee Online", "Lazada Direct", "IKEA Damansara"],
        "healthcare": ["Guardian Pharmacy", "Watsons Drugstore", "Klinik Mediviron"],
    }

    while current_date <= end_date:
        day = current_date.day
        is_weekend = current_date.weekday() >= 5

        # ----------------------------------------------------------------------
        # 1. Income: Salary on 25th of each month (or nearest Friday)
        # ----------------------------------------------------------------------
        if day == 25:
            txn_counter += 1
            transactions.append({
                "transaction_id": f"TXN-{txn_counter}",
                "transaction_date": current_date.isoformat(),
                "description": "Monthly Corporate Salary Transfer",
                "merchant": "NextOmni Tech Sdn Bhd",
                "amount": 6800.00,
                "currency": currency,
                "type": "income",
                "category": "salary",
                "account": account_name,
                "reference_number": f"SAL-{current_date.strftime('%Y%m')}",
                "is_synthetic": True,
            })

        # ----------------------------------------------------------------------
        # 2. Fixed Recurring Monthly Bills
        # ----------------------------------------------------------------------
        if day == 1:
            txn_counter += 1
            transactions.append({
                "transaction_id": f"TXN-{txn_counter}",
                "transaction_date": current_date.isoformat(),
                "description": "Monthly Residential Rent",
                "merchant": "Sunway Geo Residences",
                "amount": 1850.00,
                "currency": currency,
                "type": "expense",
                "category": "rent",
                "account": account_name,
                "reference_number": f"RENT-{current_date.strftime('%Y%m')}",
                "is_synthetic": True,
            })

        if day == 5:
            # Electricity & Water
            txn_counter += 1
            transactions.append({
                "transaction_id": f"TXN-{txn_counter}",
                "transaction_date": current_date.isoformat(),
                "description": "Electricity Utility Bill",
                "merchant": "Tenaga Nasional Berhad",
                "amount": round(random.uniform(140.0, 210.0), 2),
                "currency": currency,
                "type": "expense",
                "category": "utilities",
                "account": account_name,
                "reference_number": f"UTIL-{current_date.strftime('%Y%m')}",
                "is_synthetic": True,
            })

        if day == 15:
            # Internet & Subscriptions
            txn_counter += 1
            transactions.append({
                "transaction_id": f"TXN-{txn_counter}",
                "transaction_date": current_date.isoformat(),
                "description": "Home High-Speed Fibre Internet",
                "merchant": "Maxis Fibre Home Internet",
                "amount": 139.00,
                "currency": currency,
                "type": "expense",
                "category": "utilities",
                "account": account_name,
                "reference_number": f"NET-{current_date.strftime('%Y%m')}",
                "is_synthetic": True,
            })

        # ----------------------------------------------------------------------
        # 3. Variable Daily Living Expenses
        # ----------------------------------------------------------------------
        # Groceries: Weekly cadence (mostly on weekends)
        if (is_weekend and random.random() < 0.65) or (not is_weekend and random.random() < 0.15):
            txn_counter += 1
            merchant = random.choice(merchants_by_category["groceries"])
            amt = round(random.uniform(75.0, 240.0), 2)
            transactions.append({
                "transaction_id": f"TXN-{txn_counter}",
                "transaction_date": current_date.isoformat(),
                "description": f"Supermarket Purchase at {merchant}",
                "merchant": merchant,
                "amount": amt,
                "currency": currency,
                "type": "expense",
                "category": "groceries",
                "account": account_name,
                "reference_number": f"POS-{random.randint(100000, 999999)}",
                "is_synthetic": True,
            })

        # Dining & Coffee: 1-3 times a day on weekends, 0-2 on weekdays
        daily_dining_count = random.randint(1, 3) if is_weekend else random.choices([0, 1, 2], weights=[0.25, 0.55, 0.20])[0]
        for _ in range(daily_dining_count):
            txn_counter += 1
            merchant = random.choice(merchants_by_category["dining"])
            # Higher dining spend on weekends (e.g. dinner with friends)
            amt = round(random.uniform(35.0, 120.0) if is_weekend else random.uniform(12.0, 45.0), 2)
            transactions.append({
                "transaction_id": f"TXN-{txn_counter}",
                "transaction_date": current_date.isoformat(),
                "description": f"Food & Beverage - {merchant}",
                "merchant": merchant,
                "amount": amt,
                "currency": currency,
                "type": "expense",
                "category": "dining",
                "account": account_name,
                "reference_number": f"POS-{random.randint(100000, 999999)}",
                "is_synthetic": True,
            })

        # Transportation: Gas, tolls, or rideshare
        if random.random() < 0.35:
            txn_counter += 1
            merchant = random.choice(merchants_by_category["transportation"])
            amt = round(random.uniform(15.0, 75.0), 2)
            transactions.append({
                "transaction_id": f"TXN-{txn_counter}",
                "transaction_date": current_date.isoformat(),
                "description": f"Transit & Mobility - {merchant}",
                "merchant": merchant,
                "amount": amt,
                "currency": currency,
                "type": "expense",
                "category": "transportation",
                "account": account_name,
                "reference_number": f"TX-{random.randint(100000, 999999)}",
                "is_synthetic": True,
            })

        # Discretionary Shopping / Entertainment
        if random.random() < 0.12:
            txn_counter += 1
            cat = random.choice(["shopping", "entertainment", "healthcare"])
            merchant = random.choice(merchants_by_category[cat])
            amt = round(random.uniform(25.0, 180.0), 2)
            transactions.append({
                "transaction_id": f"TXN-{txn_counter}",
                "transaction_date": current_date.isoformat(),
                "description": f"{cat.title()} Expense - {merchant}",
                "merchant": merchant,
                "amount": amt,
                "currency": currency,
                "type": "expense",
                "category": cat,
                "account": account_name,
                "reference_number": f"CHG-{random.randint(100000, 999999)}",
                "is_synthetic": True,
            })

        current_date += datetime.timedelta(days=1)

    # --------------------------------------------------------------------------
    # 4. Injected Controlled Anomalies (For Anomaly Detection Evaluation)
    # --------------------------------------------------------------------------
    # Anomaly 1: High-ticket Apple Store purchase in Month 4
    transactions.append({
        "transaction_id": "TXN-ANOMALY-01",
        "transaction_date": (start_date + datetime.timedelta(days=110)).isoformat(),
        "description": "MacBook Pro M3 Hardware Upgrade",
        "merchant": "Apple Store The Exchange TRX",
        "amount": 7999.00,
        "currency": currency,
        "type": "expense",
        "category": "shopping",
        "account": account_name,
        "reference_number": "APL-TRX-9901",
        "is_synthetic": True,
    })

    # Anomaly 2: High dental treatment expense in Month 9
    transactions.append({
        "transaction_id": "TXN-ANOMALY-02",
        "transaction_date": (start_date + datetime.timedelta(days=265)).isoformat(),
        "description": "Emergency Dental Surgery",
        "merchant": "Sunway Medical Specialist Clinic",
        "amount": 1650.00,
        "currency": currency,
        "type": "expense",
        "category": "healthcare",
        "account": account_name,
        "reference_number": "MED-SMC-4412",
        "is_synthetic": True,
    })

    # Anomaly 3: Exact duplicate transaction (to evaluate deduplication engine)
    duplicate_source = transactions[15].copy()
    duplicate_source["transaction_id"] = f"{duplicate_source['transaction_id']}-DUP"
    transactions.append(duplicate_source)

    # Sort strictly chronologically
    transactions.sort(key=lambda x: x["transaction_date"])
    return transactions


def save_synthetic_csv(
    output_path: Path | str = "data/synthetic/transactions_sample.csv",
    num_months: int = 18,
) -> Path:
    """Generate and write synthetic transactions to a CSV file."""
    dest = Path(output_path)
    dest.parent.mkdir(parents=True, exist_ok=True)

    records = generate_synthetic_transactions(num_months=num_months)

    with open(dest, mode="w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=CANONICAL_COLUMNS)
        writer.writeheader()
        writer.writerows(records)

    return dest


if __name__ == "__main__":
    out = save_synthetic_csv()
    print(f"Generated synthetic transaction dataset at: {out.resolve()}")
