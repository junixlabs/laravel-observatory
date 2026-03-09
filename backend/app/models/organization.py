"""
Pydantic models for organization request/response schemas.
"""
import uuid
from datetime import datetime
from enum import Enum
from typing import List, Optional

from pydantic import BaseModel, Field


class MemberRole(str, Enum):
    """Organization member role enum."""
    owner = "owner"
    admin = "admin"
    member = "member"


class OrganizationCreate(BaseModel):
    """Schema for creating a new organization."""
    name: str = Field(..., min_length=1, max_length=255, description="Organization name")


class OrganizationUpdate(BaseModel):
    """Schema for updating organization (all fields optional)."""
    name: Optional[str] = Field(None, min_length=1, max_length=255)


class OrganizationResponse(BaseModel):
    """Schema for organization response."""
    id: uuid.UUID
    name: str
    slug: str
    plan: str
    owner_id: uuid.UUID
    created_at: datetime

    class Config:
        from_attributes = True


class OrganizationListResponse(BaseModel):
    """Schema for list of organizations."""
    organizations: List[OrganizationResponse]


class OrganizationMemberResponse(BaseModel):
    """Schema for organization member response."""
    id: uuid.UUID
    user_id: uuid.UUID
    user_email: str
    user_name: str
    role: MemberRole
    joined_at: Optional[datetime]

    class Config:
        from_attributes = True


class InviteMemberRequest(BaseModel):
    """Schema for inviting a member to organization."""
    email: str = Field(..., description="Email address of the user to invite")
    role: MemberRole = Field(..., description="Role to assign to the member")

    class Config:
        use_enum_values = True
