"""Application-wide constants."""

# Default project ID for legacy/unassigned logs (backward compatibility)
DEFAULT_PROJECT_ID = "00000000-0000-0000-0000-000000000000"

# ClickHouse table names
TABLE_LOGS = "logs"
TABLE_OUTBOUND_LOGS = "outbound_logs"
TABLE_JOB_LOGS = "job_logs"
TABLE_SCHEDULED_TASK_LOGS = "scheduled_task_logs"

# Column definitions for ClickHouse inserts
LOGS_COLUMNS = [
    "project_id", "request_id", "timestamp", "endpoint", "method",
    "status_code", "response_time_ms", "user_id", "user_name", "module",
    "tags", "is_outbound", "third_party_service", "request_body", "response_body",
]

OUTBOUND_LOGS_COLUMNS = [
    "project_id", "request_id", "parent_request_id", "trace_id", "span_id",
    "timestamp", "service_name", "target_host", "target_url", "method",
    "status_code", "latency_ms", "is_success", "request_size", "response_size",
    "error_message", "error_code", "retry_count", "module", "user_id",
    "request_headers", "response_headers", "request_body", "response_body",
    "tags", "metadata",
]

JOB_LOGS_COLUMNS = [
    "job_id", "job_uuid", "project_id", "timestamp", "job_class",
    "job_name", "queue_name", "connection", "status", "started_at",
    "completed_at", "duration_ms", "payload", "attempt_number",
    "max_attempts", "exception_class", "exception_message",
    "exception_trace", "user_id", "memory_usage_mb", "metadata",
]

SCHEDULED_TASK_LOGS_COLUMNS = [
    "task_id", "project_id", "timestamp", "command", "description",
    "expression", "timezone", "status", "scheduled_at", "started_at",
    "completed_at", "duration_ms", "exit_code", "output",
    "error_message", "error_trace", "without_overlapping",
    "mutex_name", "expected_run_time", "delay_ms", "metadata",
]
