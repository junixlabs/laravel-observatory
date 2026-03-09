"""Settings API endpoints for project configuration."""

from typing import List, Optional

from fastapi import APIRouter, Depends, HTTPException, status
from pydantic import BaseModel

from app.api.auth import verify_auth
from app.config import get_settings
from app.services import api_keys as api_keys_service

router = APIRouter()


class ProjectSettings(BaseModel):
    """Project settings response model."""
    project_name: str
    dsn_host: str
    dsn_endpoint: str
    api_key_count: int
    api_key_preview: Optional[str] = None


class DSNInfo(BaseModel):
    """DSN information for client SDKs."""
    format: str
    example: str
    host: str
    endpoint: str
    has_api_key: bool


class ApiKeyResponse(BaseModel):
    """API key response model (without full key)."""
    id: str
    name: str
    prefix: str
    created_at: str
    last_used_at: Optional[str] = None


class ApiKeyCreateRequest(BaseModel):
    """Request model for creating an API key."""
    name: str


class ApiKeyCreateResponse(BaseModel):
    """Response model for newly created API key (includes full key once)."""
    id: str
    name: str
    key: str
    prefix: str
    created_at: str


class ApiKeyListResponse(BaseModel):
    """Response model for list of API keys."""
    api_keys: List[ApiKeyResponse]


@router.get("/settings/project", response_model=ProjectSettings)
async def get_project_settings(_: bool = Depends(verify_auth)) -> ProjectSettings:
    """Get project settings for display in the dashboard."""
    settings = get_settings()

    # Get API key info from SQLite
    api_key_count = api_keys_service.get_api_key_count()
    api_key_preview = api_keys_service.get_first_api_key_preview()

    return ProjectSettings(
        project_name="SidMonitor",
        dsn_host=settings.host if settings.host != "0.0.0.0" else "localhost",
        dsn_endpoint="/api/ingest",
        api_key_count=api_key_count,
        api_key_preview=api_key_preview,
    )


@router.get("/settings/dsn", response_model=DSNInfo)
async def get_dsn_info(_: bool = Depends(verify_auth)) -> DSNInfo:
    """Get DSN configuration information."""
    settings = get_settings()

    host = settings.host if settings.host != "0.0.0.0" else "localhost"
    port = settings.port

    # Build example DSN
    dsn_host = f"{host}:{port}" if port != 80 else host

    # Check if there are any API keys configured
    has_api_key = api_keys_service.get_api_key_count() > 0

    return DSNInfo(
        format="https://<api-key>@<host>/api/ingest",
        example=f"https://your-api-key@{dsn_host}/api/ingest",
        host=dsn_host,
        endpoint="/api/ingest",
        has_api_key=has_api_key,
    )


@router.get("/settings/api-keys", response_model=ApiKeyListResponse)
async def list_api_keys(_: bool = Depends(verify_auth)) -> ApiKeyListResponse:
    """List all API keys (without full key values)."""
    keys = api_keys_service.list_api_keys()

    return ApiKeyListResponse(
        api_keys=[
            ApiKeyResponse(
                id=key.id,
                name=key.name,
                prefix=key.key_prefix,
                created_at=key.created_at,
                last_used_at=key.last_used_at,
            )
            for key in keys
        ]
    )


@router.post("/settings/api-keys", response_model=ApiKeyCreateResponse, status_code=status.HTTP_201_CREATED)
async def create_api_key(request: ApiKeyCreateRequest, _: bool = Depends(verify_auth)) -> ApiKeyCreateResponse:
    """Create a new API key."""
    if not request.name or not request.name.strip():
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="API key name is required",
        )

    api_key, full_key = api_keys_service.create_api_key(request.name.strip())

    return ApiKeyCreateResponse(
        id=api_key.id,
        name=api_key.name,
        key=full_key,
        prefix=api_key.key_prefix,
        created_at=api_key.created_at,
    )


@router.delete("/settings/api-keys/{key_id}", status_code=status.HTTP_204_NO_CONTENT)
async def revoke_api_key(key_id: str, _: bool = Depends(verify_auth)):
    """Revoke an API key."""
    success = api_keys_service.revoke_api_key(key_id)

    if not success:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="API key not found or already revoked",
        )
