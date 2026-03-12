"""Shared utilities for stats endpoints."""

import math


def safe_float(value, default: float = 0.0) -> float:
    """Convert value to float, handling NaN and None."""
    if value is None:
        return default
    try:
        f = float(value)
        if math.isnan(f) or math.isinf(f):
            return default
        return f
    except (TypeError, ValueError):
        return default
