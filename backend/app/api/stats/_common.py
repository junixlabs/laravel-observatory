"""Shared utilities for stats endpoints."""

import logging
import math
from typing import Optional

from app.constants import DEFAULT_PROJECT_ID

logger = logging.getLogger(__name__)


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


def build_stats_where_clause(
    project_id: Optional[str] = None,
    start_date: Optional[str] = None,
    end_date: Optional[str] = None,
    request_type: Optional[str] = None,
) -> tuple[str, dict]:
    """Build WHERE clause for stats queries. Returns (where_clause, params)."""
    conditions = []
    params = {}
    if project_id:
        conditions.append("toString(project_id) = %(project_id)s")
        params["project_id"] = project_id
    if start_date:
        conditions.append("timestamp >= parseDateTimeBestEffort(%(start_date)s)")
        params["start_date"] = start_date
    if end_date:
        conditions.append("timestamp <= parseDateTimeBestEffort(%(end_date)s)")
        params["end_date"] = end_date
    if request_type == "inbound":
        conditions.append("is_outbound = 0")
    elif request_type == "outbound":
        conditions.append("is_outbound = 1")
    where_clause = "WHERE " + " AND ".join(conditions) if conditions else ""
    return where_clause, params
