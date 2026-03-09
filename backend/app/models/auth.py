"""
Pydantic models for authentication request/response schemas.
"""
from datetime import datetime
from typing import Optional
from pydantic import BaseModel, EmailStr, Field
import uuid


class UserRegister(BaseModel):
    """Schema for user registration request."""
    email: EmailStr
    password: str = Field(..., min_length=8, description="Password must be at least 8 characters")
    name: str = Field(..., min_length=1, max_length=255)


class UserLogin(BaseModel):
    """Schema for user login request."""
    email: EmailStr
    password: str


class UserResponse(BaseModel):
    """Schema for user response (excludes password)."""
    id: uuid.UUID
    email: str
    name: str
    avatar_url: Optional[str] = None
    created_at: datetime

    class Config:
        from_attributes = True


class TokenResponse(BaseModel):
    """Schema for JWT token response."""
    access_token: str
    token_type: str = "bearer"
    user: UserResponse


class UserUpdate(BaseModel):
    """Schema for updating user profile (all fields optional)."""
    name: Optional[str] = Field(None, min_length=1, max_length=255)
    avatar_url: Optional[str] = Field(None, max_length=500)
