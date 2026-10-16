# Data Dictionary: Canonical Financial Transaction Schema

This document defines the canonical schema utilized by the NextOmni Personal Finance Intelligence and Machine Learning engine.

---

## 1. Core Transaction Schema

| Column Name | Data Type | Nullable | Example Value | Description |
| :--- | :--- | :--- | :--- | :--- |
| `transaction_id` | `VARCHAR(64)` | No | `TXN-1001` | Unique transaction identifier or deduplication hash. |
| `transaction_date`| `DATE` | No | `2025-01-15` | The date the transaction occurred or was posted. |
| `description` | `TEXT` | No | `Grab Ride to KLCC` | Raw description provided by financial institution or user. |
| `merchant` | `VARCHAR(100)` | Yes | `Grab Ride` | Cleaned merchant or entity name. Defaults to `description`. |
| `amount` | `DECIMAL(15,4)` | No | `28.50` | Positive scalar representing transaction value. |
| `currency` | `VARCHAR(3)` | No | `MYR` | ISO 4217 3-letter currency code (e.g., `MYR`, `USD`, `SGD`). |
| `type` | `VARCHAR(20)` | No | `expense` | Transaction direction. Allowed values: `expense`, `income`, `transfer`. |
| `category` | `VARCHAR(50)` | No | `transportation`| Standardized lowercase category slug. Defaults to `uncategorized`. |
| `account` | `VARCHAR(100)` | No | `Maybank Premier` | Name or ID of the originating financial account. |
| `reference_number`| `VARCHAR(100)`| Yes | `TX-892101` | External bank reference or receipt number. |
| `is_synthetic` | `BOOLEAN` | No | `True` | Flag indicating synthetic or real-world data provenance. |

---

## 2. Validation & Cleaning Rules

1. **Amount Sanitization**:
   - Amounts are treated as absolute positive scalars in the clean dataset.
   - If an expense input has a negative value (e.g., `-50.00`), the cleaning pipeline converts it via `abs()` to `50.00` with `type = 'expense'`.
2. **Date Format Standard**:
   - Dates must conform to standard ISO formats (`YYYY-MM-DD`). 
   - Dates prior to year `2000` or beyond `2040` are flagged for review.
3. **Duplicate Detection Key**:
   - Exact duplicates are identified using the composite tuple: `(transaction_date, amount, lowercase_description)`.
4. **Category Slugs**:
   - Categories are stripped of extra whitespace, converted to lowercase, and internal spaces are replaced with underscores (`food & beverage` $\rightarrow$ `food_&_beverage`).
