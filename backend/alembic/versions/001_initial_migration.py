"""Initial migration - multi-tenancy models

Revision ID: 001
Revises:
Create Date: 2025-12-14 11:50:20

"""
from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects.postgresql import UUID

# revision identifiers, used by Alembic.
revision = '001'
down_revision = None
branch_labels = None
depends_on = None


def upgrade() -> None:
    # Create users table
    op.create_table(
        'users',
        sa.Column('id', UUID(as_uuid=True), primary_key=True),
        sa.Column('email', sa.String(255), nullable=False, unique=True, index=True),
        sa.Column('password_hash', sa.String(255), nullable=False),
        sa.Column('name', sa.String(255), nullable=False),
        sa.Column('avatar_url', sa.String(500), nullable=True),
        sa.Column('email_verified', sa.Boolean(), nullable=False, default=False),
        sa.Column('created_at', sa.DateTime(), nullable=False, server_default=sa.text('NOW()')),
        sa.Column('updated_at', sa.DateTime(), nullable=False, server_default=sa.text('NOW()'))
    )

    # Create organizations table
    op.create_table(
        'organizations',
        sa.Column('id', UUID(as_uuid=True), primary_key=True),
        sa.Column('name', sa.String(255), nullable=False),
        sa.Column('slug', sa.String(100), nullable=False, unique=True, index=True),
        sa.Column('owner_id', UUID(as_uuid=True), sa.ForeignKey('users.id'), nullable=False),
        sa.Column('plan', sa.String(50), nullable=False, default='free'),
        sa.Column('created_at', sa.DateTime(), nullable=False, server_default=sa.text('NOW()')),
        sa.Column('updated_at', sa.DateTime(), nullable=False, server_default=sa.text('NOW()'))
    )
    op.create_index('idx_org_owner', 'organizations', ['owner_id'])

    # Create organization_members table
    op.create_table(
        'organization_members',
        sa.Column('id', UUID(as_uuid=True), primary_key=True),
        sa.Column('organization_id', UUID(as_uuid=True), sa.ForeignKey('organizations.id'), nullable=False),
        sa.Column('user_id', UUID(as_uuid=True), sa.ForeignKey('users.id'), nullable=False),
        sa.Column('role', sa.String(50), nullable=False),
        sa.Column('invited_at', sa.DateTime(), nullable=False, server_default=sa.text('NOW()')),
        sa.Column('joined_at', sa.DateTime(), nullable=True)
    )
    op.create_index('idx_org_user', 'organization_members', ['organization_id', 'user_id'], unique=True)
    op.create_index('idx_org_members', 'organization_members', ['organization_id'])
    op.create_index('idx_user_orgs', 'organization_members', ['user_id'])

    # Create projects table
    op.create_table(
        'projects',
        sa.Column('id', UUID(as_uuid=True), primary_key=True),
        sa.Column('organization_id', UUID(as_uuid=True), sa.ForeignKey('organizations.id'), nullable=False),
        sa.Column('name', sa.String(255), nullable=False),
        sa.Column('slug', sa.String(100), nullable=False),
        sa.Column('platform', sa.String(50), nullable=False),
        sa.Column('dsn_public_key', sa.String(32), nullable=False, unique=True, index=True),
        sa.Column('environment', sa.String(50), nullable=False, default='production'),
        sa.Column('created_by', UUID(as_uuid=True), sa.ForeignKey('users.id'), nullable=False),
        sa.Column('created_at', sa.DateTime(), nullable=False, server_default=sa.text('NOW()'))
    )
    op.create_index('idx_org_project_slug', 'projects', ['organization_id', 'slug'], unique=True)
    op.create_index('idx_org_projects', 'projects', ['organization_id'])
    op.create_index('idx_project_creator', 'projects', ['created_by'])

    # Create project_members table
    op.create_table(
        'project_members',
        sa.Column('id', UUID(as_uuid=True), primary_key=True),
        sa.Column('project_id', UUID(as_uuid=True), sa.ForeignKey('projects.id'), nullable=False),
        sa.Column('user_id', UUID(as_uuid=True), sa.ForeignKey('users.id'), nullable=False),
        sa.Column('role', sa.String(50), nullable=False)
    )
    op.create_index('idx_project_user', 'project_members', ['project_id', 'user_id'], unique=True)
    op.create_index('idx_project_members', 'project_members', ['project_id'])
    op.create_index('idx_user_projects', 'project_members', ['user_id'])

    # Create api_keys table
    op.create_table(
        'api_keys',
        sa.Column('id', UUID(as_uuid=True), primary_key=True),
        sa.Column('project_id', UUID(as_uuid=True), sa.ForeignKey('projects.id'), nullable=False),
        sa.Column('name', sa.String(255), nullable=False),
        sa.Column('key_prefix', sa.String(10), nullable=False),
        sa.Column('key_hash', sa.String(255), nullable=False),
        sa.Column('scopes', sa.JSON(), nullable=False, default=[]),
        sa.Column('created_by', UUID(as_uuid=True), sa.ForeignKey('users.id'), nullable=False),
        sa.Column('last_used_at', sa.DateTime(), nullable=True),
        sa.Column('revoked_at', sa.DateTime(), nullable=True),
        sa.Column('created_at', sa.DateTime(), nullable=False, server_default=sa.text('NOW()'))
    )
    op.create_index('idx_project_api_keys', 'api_keys', ['project_id'])
    op.create_index('idx_key_hash', 'api_keys', ['key_hash'])
    op.create_index('idx_api_key_creator', 'api_keys', ['created_by'])

    # Create invitations table
    op.create_table(
        'invitations',
        sa.Column('id', UUID(as_uuid=True), primary_key=True),
        sa.Column('organization_id', UUID(as_uuid=True), sa.ForeignKey('organizations.id'), nullable=False),
        sa.Column('email', sa.String(255), nullable=False, index=True),
        sa.Column('role', sa.String(50), nullable=False),
        sa.Column('token', sa.String(255), nullable=False, unique=True, index=True),
        sa.Column('invited_by', UUID(as_uuid=True), sa.ForeignKey('users.id'), nullable=False),
        sa.Column('expires_at', sa.DateTime(), nullable=False),
        sa.Column('accepted_at', sa.DateTime(), nullable=True),
        sa.Column('created_at', sa.DateTime(), nullable=False, server_default=sa.text('NOW()'))
    )
    op.create_index('idx_org_invitations', 'invitations', ['organization_id'])
    op.create_index('idx_email_invitations', 'invitations', ['email'])
    op.create_index('idx_invitation_inviter', 'invitations', ['invited_by'])


def downgrade() -> None:
    # Drop tables in reverse order of creation (respecting foreign key constraints)
    op.drop_table('invitations')
    op.drop_table('api_keys')
    op.drop_table('project_members')
    op.drop_table('projects')
    op.drop_table('organization_members')
    op.drop_table('organizations')
    op.drop_table('users')
