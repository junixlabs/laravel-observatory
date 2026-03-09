from datetime import datetime
from typing import Optional

from pydantic import BaseModel, Field


class JobLogEntry(BaseModel):
    """Log entry for Laravel queue job execution."""
    job_id: str = Field(..., description="Unique job identifier")
    job_uuid: Optional[str] = Field(None, description="Laravel job UUID")
    job_class: str = Field(..., description="Job class name (e.g., App\\Jobs\\ProcessPayment)")
    job_name: str = Field(..., description="Human-readable job name")
    queue_name: str = Field(default="default", description="Queue name")
    connection: str = Field(default="sync", description="Queue connection")
    status: str = Field(..., description="Job status: pending, running, success, completed, failed, cancelled, timeout, retrying")
    started_at: datetime = Field(..., description="Job start timestamp")
    completed_at: Optional[datetime] = Field(None, description="Job completion timestamp")
    duration_ms: Optional[int] = Field(None, ge=0, description="Execution duration in milliseconds")
    payload: Optional[str] = Field(None, description="Job payload JSON string")
    attempt_number: int = Field(default=1, ge=1, description="Current attempt number")
    max_attempts: int = Field(default=1, ge=1, description="Maximum retry attempts")
    exception_class: Optional[str] = Field(None, description="Exception class on failure")
    exception_message: Optional[str] = Field(None, description="Exception message on failure")
    exception_trace: Optional[str] = Field(None, description="Stack trace on failure")
    user_id: Optional[str] = Field(None, description="User ID who triggered the job")
    memory_usage_mb: Optional[float] = Field(None, ge=0, description="Memory usage in MB")
    metadata: Optional[dict] = Field(default_factory=dict, description="Additional metadata")


class ScheduledTaskLogEntry(BaseModel):
    """Log entry for Laravel scheduled task execution."""
    task_id: str = Field(..., description="Unique task execution identifier")
    command: str = Field(..., description="Artisan command signature")
    description: Optional[str] = Field(None, description="Task description")
    expression: str = Field(..., description="Cron expression")
    timezone: str = Field(default="UTC", description="Task timezone")
    status: str = Field(..., description="Task status: scheduled, running, completed, failed, skipped, missed")
    scheduled_at: datetime = Field(..., description="When task was scheduled to run")
    started_at: Optional[datetime] = Field(None, description="Actual start timestamp")
    completed_at: Optional[datetime] = Field(None, description="Completion timestamp")
    duration_ms: Optional[int] = Field(None, ge=0, description="Execution duration in milliseconds")
    exit_code: Optional[int] = Field(None, description="Command exit code")
    output: Optional[str] = Field(None, description="Command output (truncated)")
    error_message: Optional[str] = Field(None, description="Error message on failure")
    error_trace: Optional[str] = Field(None, description="Error stack trace")
    without_overlapping: bool = Field(default=False, description="Whether overlap prevention is enabled")
    mutex_name: Optional[str] = Field(None, description="Mutex name for overlap prevention")
    expected_run_time: datetime = Field(..., description="Expected run time based on schedule")
    delay_ms: Optional[int] = Field(None, description="Delay from expected run time")
    metadata: Optional[dict] = Field(default_factory=dict, description="Additional metadata")


class BatchJobIngestRequest(BaseModel):
    """Batch request for ingesting job and scheduled task logs."""
    job_logs: list[JobLogEntry] = Field(default_factory=list, description="Job log entries")
    scheduled_task_logs: list[ScheduledTaskLogEntry] = Field(default_factory=list, description="Scheduled task entries")


class JobIngestResponse(BaseModel):
    """Response for job/task ingest operations."""
    success: bool
    message: str
    ingested_count: int = 0


# Query response models

class JobExecution(BaseModel):
    """Job execution record for query responses."""
    job_id: str
    job_uuid: str
    project_id: str
    timestamp: str
    job_class: str
    job_name: str
    queue_name: str
    connection: str
    status: str
    started_at: str
    completed_at: Optional[str] = None
    duration_ms: Optional[int] = None
    payload: Optional[str] = None
    attempt_number: int
    max_attempts: int
    exception_class: Optional[str] = None
    exception_message: Optional[str] = None
    exception_trace: Optional[str] = None
    user_id: Optional[str] = None
    memory_usage_mb: Optional[float] = None


class JobQueueStats(BaseModel):
    """Statistics for a specific queue."""
    queue_name: str
    total_executions: int
    success_count: int
    failure_count: int
    avg_duration_ms: float


class JobClassStats(BaseModel):
    """Statistics for a specific job class."""
    job_class: str
    total_executions: int
    success_count: int
    failure_count: int
    avg_duration_ms: float


class RecentFailure(BaseModel):
    """Recent job failure information."""
    job_id: str
    job_class: str
    timestamp: str
    exception_message: str


class JobHealthStats(BaseModel):
    """Overall job health statistics."""
    total_executions: int
    success_count: int
    failure_count: int
    retrying_count: int
    pending_count: int = 0
    cancelled_count: int = 0
    timeout_count: int = 0
    success_rate: float
    avg_duration_ms: float
    p50_duration_ms: float = 0.0
    p95_duration_ms: float
    p99_duration_ms: float = 0.0
    by_queue: list[JobQueueStats]
    by_job_class: list[JobClassStats]
    recent_failures: list[RecentFailure]


class JobTimelinePoint(BaseModel):
    """Time-series point for job executions."""
    timestamp: str
    total: int
    success: int
    failed: int
    retrying: int
    pending: int = 0
    cancelled: int = 0
    timeout: int = 0


class ScheduledTaskExecution(BaseModel):
    """Scheduled task execution record for query responses."""
    task_id: str
    project_id: str
    timestamp: str
    command: str
    description: str
    expression: str
    timezone: str
    status: str
    scheduled_at: str
    started_at: Optional[str] = None
    completed_at: Optional[str] = None
    duration_ms: Optional[int] = None
    exit_code: Optional[int] = None
    output: str
    error_message: str
    error_trace: str
    without_overlapping: bool
    mutex_name: str
    expected_run_time: str
    delay_ms: Optional[int] = None


class ScheduledTaskCommandStats(BaseModel):
    """Statistics for a specific scheduled command."""
    command: str
    total_executions: int
    success_count: int
    failure_count: int
    missed_count: int
    avg_delay_ms: float


class ScheduledTaskFailure(BaseModel):
    """Recent scheduled task failure information."""
    task_id: str
    command: str
    timestamp: str
    error_message: str


class MissedTask(BaseModel):
    """Missed scheduled task information."""
    task_id: str
    command: str
    scheduled_at: str
    delay_ms: int


class ScheduledTaskHealthStats(BaseModel):
    """Overall scheduled task health statistics."""
    total_executions: int
    success_count: int
    failure_count: int
    missed_count: int
    success_rate: float
    avg_delay_ms: float
    avg_duration_ms: float
    by_command: list[ScheduledTaskCommandStats]
    recent_failures: list[ScheduledTaskFailure]
    missed_tasks: list[MissedTask]
