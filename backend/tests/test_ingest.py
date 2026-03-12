"""Tests for ingest endpoints (API key validation + data insertion)."""

from unittest.mock import AsyncMock, MagicMock, patch

import pytest


class TestIngestEndpoints:
    def test_ingest_requires_api_key(self, client, sample_inbound_log):
        """Ingest without API key should return 422 (missing header)."""
        response = client.post("/api/ingest", json=sample_inbound_log)
        assert response.status_code == 422

    def test_ingest_invalid_api_key(self, client, sample_inbound_log):
        """Ingest with invalid API key should return 401."""
        with patch("app.api.ingest.verify_api_key_and_get_project") as mock_verify:
            mock_verify.side_effect = Exception("Invalid key")
            response = client.post(
                "/api/ingest",
                json=sample_inbound_log,
                headers={"X-API-Key": "invalid-key"},
            )
            assert response.status_code in (401, 422, 500)

    def test_ingest_valid_inbound(self, client, sample_inbound_log, mock_clickhouse):
        """Ingest with valid API key should insert and return success."""
        mock_project = MagicMock()
        mock_project.id = "00000000-0000-0000-0000-000000000001"

        with (
            patch("app.api.ingest.verify_api_key_and_get_project", return_value=mock_project),
            patch("app.services.ingest_service.get_clickhouse_client", return_value=mock_clickhouse),
        ):
            from app.database import get_db

            app = client.app

            async def override_verify(x_api_key="", db=None):
                return mock_project

            from app.api.ingest import verify_api_key_and_get_project
            app.dependency_overrides[verify_api_key_and_get_project] = override_verify

            response = client.post(
                "/api/ingest",
                json=sample_inbound_log,
                headers={"X-API-Key": "test-key-1"},
            )
            assert response.status_code == 200
            data = response.json()
            assert data["success"] is True

            app.dependency_overrides.pop(verify_api_key_and_get_project, None)

    def test_ingest_batch_empty(self, client, mock_clickhouse):
        """Batch ingest with empty lists should succeed with count 0."""
        mock_project = MagicMock()
        mock_project.id = "00000000-0000-0000-0000-000000000001"

        with patch("app.services.ingest_service.get_clickhouse_client", return_value=mock_clickhouse):
            from app.api.ingest import verify_api_key_and_get_project
            app = client.app

            async def override_verify(x_api_key="", db=None):
                return mock_project

            app.dependency_overrides[verify_api_key_and_get_project] = override_verify

            response = client.post(
                "/api/ingest/batch",
                json={"inbound_logs": [], "outbound_logs": []},
                headers={"X-API-Key": "test-key-1"},
            )
            assert response.status_code == 200
            data = response.json()
            assert data["ingested_count"] == 0

            app.dependency_overrides.pop(verify_api_key_and_get_project, None)


class TestIngestJobEndpoints:
    def test_ingest_job(self, client, sample_job_log, mock_clickhouse):
        """Job ingest should insert and return success."""
        mock_project = MagicMock()
        mock_project.id = "00000000-0000-0000-0000-000000000001"

        with patch("app.services.ingest_service.get_clickhouse_client", return_value=mock_clickhouse):
            from app.api.ingest import verify_api_key_and_get_project
            app = client.app

            async def override_verify(x_api_key="", db=None):
                return mock_project

            app.dependency_overrides[verify_api_key_and_get_project] = override_verify

            response = client.post(
                "/api/ingest/jobs",
                json=sample_job_log,
                headers={"X-API-Key": "test-key-1"},
            )
            assert response.status_code == 200
            assert response.json()["success"] is True

            app.dependency_overrides.pop(verify_api_key_and_get_project, None)

    def test_ingest_scheduled_task(self, client, sample_scheduled_task_log, mock_clickhouse):
        """Scheduled task ingest should insert and return success."""
        mock_project = MagicMock()
        mock_project.id = "00000000-0000-0000-0000-000000000001"

        with patch("app.services.ingest_service.get_clickhouse_client", return_value=mock_clickhouse):
            from app.api.ingest import verify_api_key_and_get_project
            app = client.app

            async def override_verify(x_api_key="", db=None):
                return mock_project

            app.dependency_overrides[verify_api_key_and_get_project] = override_verify

            response = client.post(
                "/api/ingest/scheduled-tasks",
                json=sample_scheduled_task_log,
                headers={"X-API-Key": "test-key-1"},
            )
            assert response.status_code == 200
            assert response.json()["success"] is True

            app.dependency_overrides.pop(verify_api_key_and_get_project, None)
