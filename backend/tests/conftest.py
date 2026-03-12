"""Shared test fixtures for backend unit tests."""

import uuid
from unittest.mock import AsyncMock, MagicMock, patch

import pytest
from fastapi.testclient import TestClient

from app.config import Settings
from app.main import create_app
from app.services.auth import create_access_token, hash_password


# ── Settings override ──


@pytest.fixture()
def settings():
    """Test settings with known values."""
    return Settings(
        database_url="postgresql+asyncpg://test:test@localhost:5432/test",
        clickhouse_host="localhost",
        clickhouse_port=8123,
        clickhouse_database="test_db",
        jwt_secret_key="test-secret-key",
        jwt_algorithm="HS256",
        jwt_access_token_expire_minutes=30,
        auth_username="admin",
        auth_password="testpass",
        ingest_api_keys="test-key-1,test-key-2",
        cors_origins="http://localhost:3000",
    )


# ── Mock database sessions ──


@pytest.fixture()
def mock_db():
    """Mock async database session."""
    session = AsyncMock()
    session.commit = AsyncMock()
    session.refresh = AsyncMock()
    session.execute = AsyncMock()
    return session


@pytest.fixture()
def mock_clickhouse():
    """Mock ClickHouse client."""
    client = MagicMock()
    client.insert = MagicMock()
    client.query = MagicMock()
    client.command = MagicMock()
    return client


# ── Auth helpers ──


@pytest.fixture()
def test_user_id():
    """A stable test user UUID."""
    return str(uuid.uuid4())


@pytest.fixture()
def test_token(test_user_id):
    """Valid JWT token for test user."""
    return create_access_token(data={"sub": test_user_id})


@pytest.fixture()
def test_user(test_user_id):
    """Mock User ORM object."""
    user = MagicMock()
    user.id = test_user_id
    user.email = "test@example.com"
    user.name = "Test User"
    user.password_hash = hash_password("password123")
    user.avatar_url = None
    user.email_verified = False
    user.created_at = None
    user.updated_at = None
    return user


# ── Test app client ──


@pytest.fixture()
def client(mock_db, mock_clickhouse, settings):
    """FastAPI test client with mocked dependencies."""
    with (
        patch("app.config.get_settings", return_value=settings),
        patch("app.services.clickhouse.get_clickhouse_client", return_value=mock_clickhouse),
        patch("app.database.get_db", return_value=mock_db),
        patch("app.services.clickhouse.init_database"),
        patch("app.database.init_db", new_callable=AsyncMock),
    ):
        app = create_app()

        # Override get_db dependency
        async def override_get_db():
            yield mock_db

        from app.database import get_db
        app.dependency_overrides[get_db] = override_get_db

        yield TestClient(app)

        app.dependency_overrides.clear()


@pytest.fixture()
def auth_client(client, test_token):
    """Test client with auth headers pre-set."""
    client.headers["Authorization"] = f"Bearer {test_token}"
    return client


# ── Sample data factories ──


@pytest.fixture()
def sample_inbound_log():
    """Sample inbound log entry payload."""
    return {
        "request_id": "req-001",
        "timestamp": "2026-03-12T10:00:00Z",
        "endpoint": "/api/users",
        "method": "GET",
        "status_code": 200,
        "response_time_ms": 45.2,
        "user_id": "user-1",
        "user_name": "John",
        "module": "auth",
    }


@pytest.fixture()
def sample_outbound_log():
    """Sample outbound log entry payload."""
    return {
        "request_id": "req-002",
        "timestamp": "2026-03-12T10:00:00Z",
        "service_name": "stripe",
        "target_host": "api.stripe.com",
        "target_url": "https://api.stripe.com/v1/charges",
        "method": "POST",
        "status_code": 200,
        "latency_ms": 120.5,
    }


@pytest.fixture()
def sample_job_log():
    """Sample job log entry payload."""
    return {
        "job_id": "job-001",
        "job_class": "App\\Jobs\\SendEmail",
        "job_name": "SendEmail",
        "queue_name": "default",
        "connection": "redis",
        "status": "completed",
        "started_at": "2026-03-12T10:00:00Z",
        "duration_ms": 1500,
    }


@pytest.fixture()
def sample_scheduled_task_log():
    """Sample scheduled task log entry payload."""
    return {
        "task_id": "task-001",
        "command": "app:cleanup",
        "description": "Clean up old records",
        "expression": "0 * * * *",
        "timezone": "UTC",
        "status": "completed",
        "scheduled_at": "2026-03-12T10:00:00Z",
        "duration_ms": 500,
        "exit_code": 0,
        "expected_run_time": "2026-03-12T10:00:00Z",
        "delay_ms": 100,
    }
