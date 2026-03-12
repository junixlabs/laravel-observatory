"""Tests for health check endpoints."""


class TestHealthEndpoints:
    def test_health(self, client):
        response = client.get("/health")
        assert response.status_code == 200
        assert response.json() == {"status": "healthy"}

    def test_ready(self, client):
        response = client.get("/ready")
        assert response.status_code == 200
        assert response.json() == {"status": "ready"}
