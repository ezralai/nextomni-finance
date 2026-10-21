"""
Statistical baselines, regression forecasters, and spending persona models package.
"""

from src.models.spending_personas import (
    classify_spending_persona,
    extract_behavioral_features,
    PERSONA_ARCHETYPES,
)

__all__ = [
    "classify_spending_persona",
    "extract_behavioral_features",
    "PERSONA_ARCHETYPES",
]
