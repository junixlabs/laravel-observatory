"""Job and Scheduled Task ingestion endpoints (v1 aliases).

These endpoints are mounted at /api/v1/ and provide alternative paths
for job/task ingestion alongside the main /api/ingest/ endpoints.
"""

import logging
from fastapi import APIRouter, HTTPException, status, Depends
from typing import Optional

from app.models.jobs import (
    JobLogEntry,
    ScheduledTaskLogEntry,
    BatchJobIngestRequest,
    JobIngestResponse,
)
from app.services import ingest_service
from app.api.ingest import verify_api_key_and_get_project
from app.models.database import Project

router = APIRouter()
logger = logging.getLogger(__name__)


@router.post("/ingest/job", response_model=JobIngestResponse)
async def ingest_single_job(
    entry: JobLogEntry,
    project: Optional[Project] = Depends(verify_api_key_and_get_project),
):
    """Ingest a single job log entry."""
    try:
        project_id = project.id if project else None
        ingest_service.insert_job_log(entry, project_id)

        return JobIngestResponse(
            success=True,
            message="Job log entry ingested successfully",
            ingested_count=1,
        )
    except Exception as e:
        logger.exception("Error ingesting job log entry")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Error ingesting job log entry: {str(e)}",
        )


@router.post("/ingest/scheduled-task", response_model=JobIngestResponse)
async def ingest_single_scheduled_task(
    entry: ScheduledTaskLogEntry,
    project: Optional[Project] = Depends(verify_api_key_and_get_project),
):
    """Ingest a single scheduled task log entry."""
    try:
        project_id = project.id if project else None
        ingest_service.insert_scheduled_task_log(entry, project_id)

        return JobIngestResponse(
            success=True,
            message="Scheduled task log entry ingested successfully",
            ingested_count=1,
        )
    except Exception as e:
        logger.exception("Error ingesting scheduled task log entry")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Error ingesting scheduled task log entry: {str(e)}",
        )


@router.post("/ingest/jobs/batch", response_model=JobIngestResponse)
async def ingest_batch_jobs(
    batch: BatchJobIngestRequest,
    project: Optional[Project] = Depends(verify_api_key_and_get_project),
):
    """Ingest multiple job and scheduled task log entries in a batch."""
    try:
        project_id = project.id if project else None
        total = 0

        total += ingest_service.insert_job_logs_batch(batch.job_logs, project_id)
        total += ingest_service.insert_scheduled_task_logs_batch(batch.scheduled_task_logs, project_id)

        return JobIngestResponse(
            success=True,
            message=f"Batch ingested successfully ({total} entries)",
            ingested_count=total,
        )
    except Exception as e:
        logger.exception("Error ingesting batch")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Error ingesting batch: {str(e)}",
        )
