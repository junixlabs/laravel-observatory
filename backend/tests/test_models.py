"""Tests for Pydantic model validation."""

import pytest
from pydantic import ValidationError

from app.models.ingest import InboundLogEntry
from app.models.jobs import JobLogEntry, ScheduledTaskLogEntry
from app.models.outbound import OutboundLogEntry


class TestInboundLogEntry:
    def test_valid_entry(self, sample_inbound_log):
        entry = InboundLogEntry(**sample_inbound_log)
        assert entry.request_id == "req-001"
        assert entry.status_code == 200

    def test_missing_required_fields(self):
        with pytest.raises(ValidationError):
            InboundLogEntry(request_id="req-1")  # missing required fields

    def test_optional_fields_default(self):
        entry = InboundLogEntry(
            request_id="req-1",
            timestamp="2026-03-12T10:00:00Z",
            endpoint="/api/test",
            method="GET",
            status_code=200,
            response_time_ms=10.0,
        )
        assert entry.user_id is None
        assert entry.module is None


class TestOutboundLogEntry:
    def test_valid_entry(self, sample_outbound_log):
        entry = OutboundLogEntry(**sample_outbound_log)
        assert entry.service_name == "stripe"
        assert entry.status_code == 200

    def test_service_name_alias(self):
        """Test legacy alias third_party_service → service_name."""
        entry = OutboundLogEntry(
            request_id="req-1",
            timestamp="2026-03-12T10:00:00Z",
            third_party_service="stripe",
            target_host="api.stripe.com",
            target_url="https://api.stripe.com/v1/charges",
            method="POST",
            status_code=200,
            latency_ms=100.0,
        )
        assert entry.service_name == "stripe"

    def test_default_values(self):
        entry = OutboundLogEntry(
            request_id="req-1",
            timestamp="2026-03-12T10:00:00Z",
            service_name="test",
            target_host="test.com",
            target_url="https://test.com/api",
            method="GET",
            status_code=200,
            latency_ms=50.0,
        )
        assert entry.retry_count == 0
        assert entry.request_size == 0


class TestJobLogEntry:
    def test_valid_entry(self, sample_job_log):
        entry = JobLogEntry(**sample_job_log)
        assert entry.job_class == "App\\Jobs\\SendEmail"
        assert entry.status == "completed"

    def test_missing_required_raises(self):
        with pytest.raises(ValidationError):
            JobLogEntry(job_id="j-1")


class TestScheduledTaskLogEntry:
    def test_valid_entry(self, sample_scheduled_task_log):
        entry = ScheduledTaskLogEntry(**sample_scheduled_task_log)
        assert entry.command == "app:cleanup"
        assert entry.exit_code == 0

    def test_missing_required_raises(self):
        with pytest.raises(ValidationError):
            ScheduledTaskLogEntry(task_id="t-1")
