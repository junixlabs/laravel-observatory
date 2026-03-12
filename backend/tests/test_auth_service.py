"""Tests for authentication service (JWT, password hashing)."""

from datetime import timedelta
from unittest.mock import patch

import pytest
from fastapi import HTTPException

from app.services.auth import (
    create_access_token,
    decode_token,
    hash_password,
    verify_password,
)


class TestPasswordHashing:
    def test_hash_password_returns_string(self):
        hashed = hash_password("mypassword")
        assert isinstance(hashed, str)
        assert hashed != "mypassword"

    def test_hash_password_different_each_time(self):
        h1 = hash_password("same")
        h2 = hash_password("same")
        assert h1 != h2  # bcrypt uses random salt

    def test_verify_password_correct(self):
        hashed = hash_password("correct")
        assert verify_password("correct", hashed) is True

    def test_verify_password_incorrect(self):
        hashed = hash_password("correct")
        assert verify_password("wrong", hashed) is False

    def test_verify_password_empty(self):
        hashed = hash_password("notempty")
        assert verify_password("", hashed) is False


class TestJWT:
    def test_create_and_decode_token(self):
        token = create_access_token(data={"sub": "user-123"})
        payload = decode_token(token)
        assert payload["sub"] == "user-123"
        assert "exp" in payload

    def test_create_token_with_custom_expiry(self):
        token = create_access_token(
            data={"sub": "user-123"},
            expires_delta=timedelta(hours=1),
        )
        payload = decode_token(token)
        assert payload["sub"] == "user-123"

    def test_decode_invalid_token_raises(self):
        with pytest.raises(HTTPException) as exc_info:
            decode_token("invalid.token.here")
        assert exc_info.value.status_code == 401

    def test_decode_token_wrong_secret(self):
        token = create_access_token(data={"sub": "user-123"})
        with patch("app.services.auth.settings") as mock_settings:
            mock_settings.jwt_secret_key = "different-secret"
            mock_settings.jwt_algorithm = "HS256"
            with pytest.raises(HTTPException) as exc_info:
                decode_token(token)
            assert exc_info.value.status_code == 401

    def test_token_contains_sub_claim(self):
        token = create_access_token(data={"sub": "abc"})
        payload = decode_token(token)
        assert payload.get("sub") == "abc"

    def test_token_extra_data_preserved(self):
        token = create_access_token(data={"sub": "user-1", "role": "admin"})
        payload = decode_token(token)
        assert payload["role"] == "admin"
