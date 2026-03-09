"""
Pydantic models for project request/response schemas.
"""
from datetime import datetime
from typing import Optional, List
from pydantic import BaseModel, Field
import uuid


class ProjectCreate(BaseModel):
    """Schema for project creation request."""
    name: str = Field(..., min_length=1, max_length=255, description="Project name")
    platform: Optional[str] = Field(default="laravel", max_length=50, description="Platform (laravel, node, python, etc.)")
    environment: str = Field(default="production", max_length=50, description="Environment (production, staging, development)")


class ProjectUpdate(BaseModel):
    """Schema for project update request (all fields optional)."""
    name: Optional[str] = Field(None, min_length=1, max_length=255, description="Project name")
    platform: Optional[str] = Field(None, max_length=50, description="Platform")
    environment: Optional[str] = Field(None, max_length=50, description="Environment")


class ProjectResponse(BaseModel):
    """Schema for project response."""
    id: uuid.UUID
    name: str
    slug: str
    platform: str
    environment: str
    dsn: str
    created_at: datetime
    created_by: uuid.UUID

    class Config:
        from_attributes = True


class ProjectListResponse(BaseModel):
    """Schema for project list response."""
    projects: List[ProjectResponse]


class ApiKeyCreate(BaseModel):
    """Schema for API key creation request."""
    name: str = Field(..., min_length=1, max_length=255, description="API key name/description")
    scopes: List[str] = Field(default=["ingest"], description="API key scopes")


class ApiKeyResponse(BaseModel):
    """Schema for API key response (without full key)."""
    id: uuid.UUID
    name: str
    key_prefix: str
    scopes: List[str]
    created_at: datetime
    last_used_at: Optional[datetime] = None

    class Config:
        from_attributes = True


class ApiKeyCreatedResponse(BaseModel):
    """Schema for API key creation response (includes full key - only shown once!)."""
    id: uuid.UUID
    name: str
    key_prefix: str
    key: str  # Full API key - only shown on creation!
    scopes: List[str]
    created_at: datetime
    last_used_at: Optional[datetime] = None

    class Config:
        from_attributes = True


class ApiKeyListResponse(BaseModel):
    """Schema for API key list response."""
    api_keys: List[ApiKeyResponse]


class DsnResponse(BaseModel):
    """Schema for DSN response."""
    dsn: str
