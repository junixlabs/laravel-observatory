"""Tests for the unified WhereBuilder query builder."""

import pytest
from fastapi import HTTPException

from app.services.query_builder import WhereBuilder, validate_date


class TestValidateDate:
    def test_valid_iso_date(self):
        assert validate_date("2026-03-12") == "2026-03-12"

    def test_valid_iso_datetime(self):
        assert validate_date("2026-03-12T10:00:00") == "2026-03-12T10:00:00"

    def test_valid_iso_with_z(self):
        assert validate_date("2026-03-12T10:00:00Z") == "2026-03-12T10:00:00Z"

    def test_none_returns_none(self):
        assert validate_date(None) is None

    def test_invalid_date_raises_422(self):
        with pytest.raises(HTTPException) as exc_info:
            validate_date("not-a-date", "start_date")
        assert exc_info.value.status_code == 422
        assert "start_date" in exc_info.value.detail

    def test_empty_string_raises_422(self):
        with pytest.raises(HTTPException) as exc_info:
            validate_date("", "end_date")
        assert exc_info.value.status_code == 422


class TestWhereBuilderProject:
    def test_project_filter(self):
        wb = WhereBuilder()
        wb.project("proj-1")
        where, params = wb.build()
        assert "toString(project_id) = %(project_id)s" in where
        assert params["project_id"] == "proj-1"

    def test_project_none_skipped(self):
        wb = WhereBuilder()
        wb.project(None)
        where, params = wb.build()
        assert where == ""
        assert params == {}


class TestWhereBuilderDateRange:
    def test_date_range_both(self):
        wb = WhereBuilder()
        wb.date_range("2026-03-01", "2026-03-12")
        where, params = wb.build()
        assert "timestamp >= %(start_date)s" in where
        assert "timestamp <= %(end_date)s" in where
        assert params["start_date"] == "2026-03-01"
        assert params["end_date"] == "2026-03-12"

    def test_date_range_start_only(self):
        wb = WhereBuilder()
        wb.date_range(start_date="2026-03-01")
        where, params = wb.build()
        assert "start_date" in params
        assert "end_date" not in params

    def test_date_range_custom_field(self):
        wb = WhereBuilder()
        wb.date_range("2026-03-01", field="started_at")
        where, params = wb.build()
        assert "started_at >= %(start_date)s" in where

    def test_date_range_best_effort(self):
        wb = WhereBuilder()
        wb.date_range("2026-03-01", best_effort=True)
        where, params = wb.build()
        assert "parseDateTimeBestEffort" in where

    def test_invalid_date_raises_422(self):
        wb = WhereBuilder()
        with pytest.raises(HTTPException) as exc_info:
            wb.date_range("invalid-date")
        assert exc_info.value.status_code == 422


class TestWhereBuilderStatusCode:
    def test_status_2xx(self):
        wb = WhereBuilder()
        wb.status_code("2xx")
        where, _ = wb.build()
        assert "status_code >= 200 AND status_code < 300" in where

    def test_status_error(self):
        wb = WhereBuilder()
        wb.status_code("error")
        where, _ = wb.build()
        assert "status_code >= 400" in where

    def test_status_none_skipped(self):
        wb = WhereBuilder()
        wb.status_code(None)
        where, _ = wb.build()
        assert where == ""

    def test_invalid_status_raises_422(self):
        wb = WhereBuilder()
        with pytest.raises(HTTPException) as exc_info:
            wb.status_code("1xx")
        assert exc_info.value.status_code == 422
        assert "Invalid status filter" in exc_info.value.detail


class TestWhereBuilderMethods:
    def test_eq(self):
        wb = WhereBuilder()
        wb.eq("module", "auth")
        where, params = wb.build()
        assert "module = %(module)s" in where
        assert params["module"] == "auth"

    def test_eq_none_skipped(self):
        wb = WhereBuilder()
        wb.eq("module", None)
        where, _ = wb.build()
        assert where == ""

    def test_eq_custom_param_name(self):
        wb = WhereBuilder()
        wb.eq("module", "auth", param_name="mod")
        _, params = wb.build()
        assert params["mod"] == "auth"

    def test_like(self):
        wb = WhereBuilder()
        wb.like("endpoint", "/api")
        where, params = wb.build()
        assert "endpoint LIKE %(endpoint)s" in where
        assert params["endpoint"] == "%/api%"

    def test_like_none_skipped(self):
        wb = WhereBuilder()
        wb.like("endpoint", None)
        where, _ = wb.build()
        assert where == ""

    def test_user_search(self):
        wb = WhereBuilder()
        wb.user_search("john")
        where, params = wb.build()
        assert "user_id = %(user)s" in where
        assert "user_name LIKE %(user_pattern)s" in where
        assert params["user"] == "john"
        assert params["user_pattern"] == "%john%"

    def test_not_empty(self):
        wb = WhereBuilder()
        wb.not_empty("module")
        where, _ = wb.build()
        assert "module IS NOT NULL AND module != ''" in where

    def test_inbound_only(self):
        wb = WhereBuilder()
        wb.inbound_only()
        where, _ = wb.build()
        assert "is_outbound = 0" in where

    def test_request_type_inbound(self):
        wb = WhereBuilder()
        wb.request_type("inbound")
        where, _ = wb.build()
        assert "is_outbound = 0" in where

    def test_request_type_outbound(self):
        wb = WhereBuilder()
        wb.request_type("outbound")
        where, _ = wb.build()
        assert "is_outbound = 1" in where

    def test_request_type_none_skipped(self):
        wb = WhereBuilder()
        wb.request_type(None)
        where, _ = wb.build()
        assert where == ""

    def test_raw(self):
        wb = WhereBuilder()
        wb.raw("status_code >= 400")
        where, _ = wb.build()
        assert "status_code >= 400" in where

    def test_raw_with_params(self):
        wb = WhereBuilder()
        wb.raw("field = %(val)s", val=42)
        _, params = wb.build()
        assert params["val"] == 42


class TestWhereBuilderBuild:
    def test_build_empty(self):
        wb = WhereBuilder()
        where, params = wb.build()
        assert where == ""
        assert params == {}

    def test_build_with_conditions(self):
        wb = WhereBuilder()
        wb.project("p1").eq("module", "auth")
        where, _ = wb.build()
        assert where.startswith("WHERE ")
        assert " AND " in where

    def test_build_conditions_empty(self):
        wb = WhereBuilder()
        conds, _ = wb.build_conditions()
        assert conds == "1=1"

    def test_build_conditions_with_data(self):
        wb = WhereBuilder()
        wb.project("p1")
        conds, _ = wb.build_conditions()
        assert "toString(project_id)" in conds
        assert not conds.startswith("WHERE")

    def test_build_and_empty(self):
        wb = WhereBuilder()
        clause, _ = wb.build_and()
        assert clause == ""

    def test_build_and_with_conditions(self):
        wb = WhereBuilder()
        wb.eq("status", "active")
        clause, _ = wb.build_and()
        assert clause.startswith("AND ")

    def test_build_returns_params_copy(self):
        wb = WhereBuilder()
        wb.eq("x", 1)
        _, p1 = wb.build()
        _, p2 = wb.build()
        p1["extra"] = True
        assert "extra" not in p2

    def test_chaining(self):
        wb = WhereBuilder()
        result = wb.project("p1").date_range("2026-01-01").eq("module", "auth")
        assert result is wb  # chainable
        where, params = wb.build()
        assert "project_id" in where
        assert "start_date" in params
        assert "module" in params
