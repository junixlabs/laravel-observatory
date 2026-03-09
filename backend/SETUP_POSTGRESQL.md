# PostgreSQL + SQLAlchemy Setup Guide

## Overview

This document describes the PostgreSQL multi-tenancy setup for SidMonitor. The system now uses:
- **PostgreSQL**: User accounts, organizations, projects, API keys (relational data)
- **ClickHouse**: Logs and error events (time-series data)

## Installation

### 1. Install Python Dependencies

```bash
cd backend
pip install -r requirements.txt
```

New dependencies added:
- `sqlalchemy==2.0.23` - ORM with async support
- `asyncpg==0.29.0` - Async PostgreSQL driver
- `alembic==1.13.0` - Database migrations
- `python-jose[cryptography]==3.3.0` - JWT token handling
- `passlib[bcrypt]==1.7.4` - Password hashing

### 2. Setup PostgreSQL Database

```bash
# Install PostgreSQL (if not already installed)
# macOS
brew install postgresql@15
brew services start postgresql@15

# Ubuntu/Debian
sudo apt-get install postgresql-15

# Create database and user
psql postgres
CREATE USER sidmonitor WITH PASSWORD 'your-password';
CREATE DATABASE sidmonitor OWNER sidmonitor;
GRANT ALL PRIVILEGES ON DATABASE sidmonitor TO sidmonitor;
\q
```

### 3. Configure Environment Variables

Copy and update `.env.example`:

```bash
cp .env.example .env
```

Update the following in `.env`:

```env
# PostgreSQL Database
DATABASE_URL=postgresql+asyncpg://sidmonitor:your-password@localhost:5432/sidmonitor

# JWT Authentication (generate a secure secret key)
JWT_SECRET_KEY=your-secret-key-here  # Use: openssl rand -hex 32
JWT_ALGORITHM=HS256
JWT_ACCESS_TOKEN_EXPIRE_MINUTES=30
```

### 4. Run Database Migrations

```bash
# Run migrations to create all tables
alembic upgrade head

# Verify migration status
alembic current
```

## Database Schema

### Tables Created

1. **users**
   - User accounts with email/password authentication
   - Fields: id, email, password_hash, name, avatar_url, email_verified, timestamps

2. **organizations**
   - Multi-tenant organizations (workspaces)
   - Fields: id, name, slug, owner_id, plan (free/pro/enterprise), timestamps

3. **organization_members**
   - Organization membership with roles (owner/admin/member)
   - Fields: id, organization_id, user_id, role, invited_at, joined_at

4. **projects**
   - Projects within organizations, each with unique DSN
   - Fields: id, organization_id, name, slug, platform, dsn_public_key, environment, created_by, created_at

5. **project_members**
   - Project-level access control (admin/member/viewer)
   - Fields: id, project_id, user_id, role

6. **api_keys**
   - Scoped API keys for project authentication
   - Fields: id, project_id, name, key_prefix, key_hash, scopes, created_by, last_used_at, revoked_at, created_at

7. **invitations**
   - Organization invitations via email
   - Fields: id, organization_id, email, role, token, invited_by, expires_at, accepted_at, created_at

### Relationships

```
User (1) ─── owns ─── (N) Organization
Organization (1) ─── has ─── (N) Project
Organization (1) ─── has ─── (N) OrganizationMember ─── (1) User
Project (1) ─── has ─── (N) ProjectMember ─── (1) User
Project (1) ─── has ─── (N) ApiKey
Organization (1) ─── has ─── (N) Invitation
```

## Usage

### Database Connection

```python
from app.services.database import get_db
from sqlalchemy.ext.asyncio import AsyncSession
from fastapi import Depends

@app.get("/users")
async def get_users(db: AsyncSession = Depends(get_db)):
    from sqlalchemy import select
    from app.models.database import User

    result = await db.execute(select(User))
    users = result.scalars().all()
    return users
```

### Creating Records

```python
from app.models.database import User, Organization
import uuid

# Create a new user
new_user = User(
    id=uuid.uuid4(),
    email="user@example.com",
    password_hash="hashed_password_here",
    name="John Doe",
    email_verified=False
)
db.add(new_user)
await db.commit()
await db.refresh(new_user)

# Create an organization
new_org = Organization(
    id=uuid.uuid4(),
    name="My Organization",
    slug="my-org",
    owner_id=new_user.id,
    plan="free"
)
db.add(new_org)
await db.commit()
```

### Querying with Relationships

```python
from sqlalchemy import select
from sqlalchemy.orm import selectinload

# Get user with all their organizations
result = await db.execute(
    select(User)
    .options(selectinload(User.owned_organizations))
    .where(User.email == "user@example.com")
)
user = result.scalar_one_or_none()

if user:
    for org in user.owned_organizations:
        print(f"Organization: {org.name} ({org.slug})")
```

## Alembic Commands

```bash
# Create a new migration after model changes
alembic revision --autogenerate -m "Description of changes"

# Apply migrations
alembic upgrade head

# Rollback one migration
alembic downgrade -1

# Show current version
alembic current

# Show migration history
alembic history --verbose

# Rollback to specific version
alembic downgrade <revision_id>
```

## Security Notes

1. **JWT Secret Key**: Always use a cryptographically secure random key in production:
   ```bash
   openssl rand -hex 32
   ```

2. **Password Hashing**: Use `passlib[bcrypt]` for password hashing:
   ```python
   from passlib.context import CryptContext

   pwd_context = CryptContext(schemes=["bcrypt"], deprecated="auto")
   hashed_password = pwd_context.hash("plain_password")
   is_valid = pwd_context.verify("plain_password", hashed_password)
   ```

3. **Database URL**: Never commit `.env` file with actual credentials.

## Next Steps

1. **Install dependencies**: `pip install -r requirements.txt`
2. **Setup PostgreSQL database**
3. **Configure `.env` file**
4. **Run migrations**: `alembic upgrade head`
5. **Implement authentication endpoints** (login, register, JWT)
6. **Implement organization/project CRUD APIs**
7. **Update ingest API to use project DSN for auth**

## File Structure

```
backend/
├── alembic/
│   ├── versions/
│   │   └── 001_initial_migration.py
│   ├── env.py
│   ├── script.py.mako
│   └── README
├── alembic.ini
├── app/
│   ├── models/
│   │   └── database.py          # SQLAlchemy models
│   ├── services/
│   │   └── database.py          # DB connection & session
│   └── config.py                # Updated with PostgreSQL settings
├── requirements.txt             # Updated with new dependencies
└── .env.example                 # Updated with PostgreSQL & JWT config
```

## Troubleshooting

### Connection Issues

```bash
# Test PostgreSQL connection
psql -h localhost -U sidmonitor -d sidmonitor

# Check if PostgreSQL is running
brew services list | grep postgresql
# or
sudo systemctl status postgresql
```

### Migration Issues

```bash
# Check current migration status
alembic current

# See pending migrations
alembic heads

# Reset database (CAUTION: destroys all data)
alembic downgrade base
alembic upgrade head
```

### Import Errors

```bash
# Ensure all dependencies are installed
pip install -r requirements.txt

# Check Python path
python -c "import sys; print(sys.path)"
```
