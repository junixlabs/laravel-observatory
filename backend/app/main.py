from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from contextlib import asynccontextmanager

from app.config import get_settings
from app.database import init_db
from app.api import logs, stats, health, ingest, settings as settings_router, auth, projects, organizations, jobs, jobs_query, outbound, inbound, frontend_logs
from app.services.clickhouse import init_database as init_clickhouse
from app.exceptions import AppException, app_exception_handler


@asynccontextmanager
async def lifespan(app: FastAPI):
    # Startup
    await init_db()  # Initialize PostgreSQL tables
    try:
        init_clickhouse()  # Initialize ClickHouse tables (jobs, scheduled_tasks)
    except Exception as e:
        import logging
        logging.warning(f"ClickHouse initialization failed (not critical for auth/org APIs): {e}")
    yield
    # Shutdown


def create_app() -> FastAPI:
    app_settings = get_settings()

    app = FastAPI(
        title="Sid Monitoring API",
        description="API for Log Monitoring Dashboard",
        version="0.1.0",
        lifespan=lifespan,
    )

    app.add_exception_handler(AppException, app_exception_handler)

    app.add_middleware(
        CORSMiddleware,
        allow_origins=app_settings.cors_origins_list,
        allow_credentials=True,
        allow_methods=["*"],
        allow_headers=["*"],
    )

    app.include_router(health.router, tags=["Health"])
    app.include_router(auth.router, prefix="/api", tags=["Authentication"])
    app.include_router(organizations.router, prefix="/api", tags=["Organizations"])
    app.include_router(projects.router, prefix="/api", tags=["Projects"])
    # IMPORTANT: outbound.router MUST be before logs.router
    # because logs.router has /logs/{log_id} which would match "outbound" as log_id
    app.include_router(outbound.router, prefix="/api", tags=["Outbound Logs"])
    app.include_router(inbound.router, prefix="/api", tags=["Inbound Logs"])
    app.include_router(logs.router, prefix="/api", tags=["Logs"])
    app.include_router(stats.router, prefix="/api", tags=["Stats"])
    app.include_router(ingest.router, prefix="/api", tags=["Ingest"])
    app.include_router(jobs.router, prefix="/api/v1", tags=["Jobs"])
    app.include_router(jobs_query.router, prefix="/api/v1", tags=["Jobs Query"])
    app.include_router(settings_router.router, prefix="/api", tags=["Settings"])
    app.include_router(frontend_logs.router, prefix="/api", tags=["Frontend Logs"])

    return app


app = create_app()
