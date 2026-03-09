"""
Seed test data for Jobs and Scheduled Tasks into ClickHouse.
Run directly: python tests/seed_job_data.py
"""

import sys
import os
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import random
from datetime import datetime, timedelta, timezone
from app.services.clickhouse import get_clickhouse_client

# Project ID from database
PROJECT_ID = "efb477b8-0ff0-4c4b-8c94-e040c23e6dfe"

# Sample job classes
JOB_CLASSES = [
    "App\\Jobs\\ProcessPayment",
    "App\\Jobs\\SendEmailNotification",
    "App\\Jobs\\GenerateReport",
    "App\\Jobs\\SyncInventory",
    "App\\Jobs\\ProcessOrder",
    "App\\Jobs\\UpdateUserStats",
    "App\\Jobs\\CleanupOldRecords",
    "App\\Jobs\\ImportData",
]

JOB_NAMES = [
    "Process Payment",
    "Send Email Notification",
    "Generate Report",
    "Sync Inventory",
    "Process Order",
    "Update User Stats",
    "Cleanup Old Records",
    "Import Data",
]

QUEUE_NAMES = ["default", "high", "low", "emails", "reports"]

JOB_STATUSES = ["completed", "completed", "completed", "completed", "completed", "failed", "retrying"]

# Sample scheduled task commands
SCHEDULED_COMMANDS = [
    "app:cleanup-logs",
    "app:send-daily-report",
    "app:sync-external-data",
    "app:process-pending-orders",
    "app:update-analytics",
    "app:backup-database",
    "app:send-reminders",
    "app:generate-sitemap",
]

SCHEDULED_DESCRIPTIONS = [
    "Cleanup old log files",
    "Send daily analytics report",
    "Sync data from external API",
    "Process pending orders",
    "Update analytics dashboard",
    "Backup database to S3",
    "Send reminder notifications",
    "Generate sitemap.xml",
]

CRON_EXPRESSIONS = [
    "0 * * * *",      # Every hour
    "0 0 * * *",      # Daily at midnight
    "*/15 * * * *",   # Every 15 minutes
    "0 8 * * *",      # Daily at 8am
    "0 0 * * 0",      # Weekly on Sunday
    "0 2 * * *",      # Daily at 2am
    "*/30 * * * *",   # Every 30 minutes
    "0 6 * * 1-5",    # Weekdays at 6am
]

TASK_STATUSES = ["completed", "completed", "completed", "completed", "failed", "skipped", "missed"]


def generate_job_logs(count=100):
    """Generate job log entries."""
    client = get_clickhouse_client()

    rows = []
    now = datetime.now(timezone.utc)

    for i in range(count):
        # Random time within last 24 hours
        started_at = now - timedelta(hours=random.randint(0, 24), minutes=random.randint(0, 59))
        duration_ms = random.randint(50, 30000)  # 50ms to 30s
        completed_at = started_at + timedelta(milliseconds=duration_ms)

        job_idx = random.randint(0, len(JOB_CLASSES) - 1)
        status = random.choice(JOB_STATUSES)

        # Failed jobs have exception info
        exception_class = ""
        exception_message = ""
        exception_trace = ""
        if status == "failed":
            exception_class = random.choice([
                "RuntimeException",
                "InvalidArgumentException",
                "TimeoutException",
                "ConnectionException",
            ])
            exception_message = f"Error processing job: {random.choice(['Connection timeout', 'Invalid data', 'Resource not found', 'Rate limit exceeded'])}"
            exception_trace = f"Stack trace for {exception_class}..."

        # Match schema: project_id, job_id, job_uuid, job_class, job_name, queue_name, connection, status,
        # started_at, completed_at, duration_ms, payload, attempt_number, max_attempts,
        # exception_class, exception_message, exception_trace, user_id, memory_usage_mb, metadata
        rows.append([
            PROJECT_ID,  # project_id
            f"job_{i}_{random.randint(1000, 9999)}",  # job_id
            f"uuid-{i}-{random.randint(100000, 999999)}",  # job_uuid
            JOB_CLASSES[job_idx],  # job_class
            JOB_NAMES[job_idx],  # job_name
            random.choice(QUEUE_NAMES),  # queue_name
            "redis",  # connection
            status,  # status
            started_at,  # started_at
            completed_at if status != "retrying" else None,  # completed_at
            duration_ms if status != "retrying" else 0,  # duration_ms
            '{"order_id": 12345}',  # payload
            random.randint(1, 3),  # attempt_number
            3,  # max_attempts
            exception_class,  # exception_class
            exception_message,  # exception_message
            exception_trace,  # exception_trace
            f"user_{random.randint(1, 100)}",  # user_id
            random.uniform(10, 100),  # memory_usage_mb
            '{}',  # metadata
        ])

    client.insert(
        "job_logs",
        rows,
        column_names=[
            "project_id", "job_id", "job_uuid", "job_class", "job_name",
            "queue_name", "connection", "status", "started_at", "completed_at",
            "duration_ms", "payload", "attempt_number", "max_attempts",
            "exception_class", "exception_message", "exception_trace",
            "user_id", "memory_usage_mb", "metadata"
        ]
    )

    print(f"Inserted {count} job log entries")


def generate_scheduled_task_logs(count=50):
    """Generate scheduled task log entries."""
    client = get_clickhouse_client()

    rows = []
    now = datetime.now(timezone.utc)

    for i in range(count):
        # Random time within last 7 days
        scheduled_at = now - timedelta(days=random.randint(0, 7), hours=random.randint(0, 23))
        delay_ms = random.randint(0, 5000)  # 0-5 second delay
        started_at = scheduled_at + timedelta(milliseconds=delay_ms)
        duration_ms = random.randint(100, 60000)  # 100ms to 60s
        completed_at = started_at + timedelta(milliseconds=duration_ms)

        cmd_idx = random.randint(0, len(SCHEDULED_COMMANDS) - 1)
        status = random.choice(TASK_STATUSES)

        # Failed/skipped tasks have error info
        error_message = ""
        error_trace = ""
        exit_code = 0
        if status == "failed":
            error_message = random.choice([
                "Command execution failed",
                "Database connection error",
                "External API unavailable",
                "Memory limit exceeded",
            ])
            error_trace = f"Error trace for {SCHEDULED_COMMANDS[cmd_idx]}..."
            exit_code = random.choice([1, 2, 255])
        elif status == "skipped":
            error_message = "Task skipped due to overlapping execution"
        elif status == "missed":
            delay_ms = random.randint(60000, 300000)  # 1-5 minutes late

        # Match schema: project_id, task_id, command, description, expression, timezone, status,
        # scheduled_at, started_at, completed_at, duration_ms, exit_code, output, error_message,
        # error_trace, without_overlapping, mutex_name, expected_run_time, delay_ms, metadata
        rows.append([
            PROJECT_ID,  # project_id
            f"task_{i}_{random.randint(1000, 9999)}",  # task_id
            SCHEDULED_COMMANDS[cmd_idx],  # command
            SCHEDULED_DESCRIPTIONS[cmd_idx],  # description
            CRON_EXPRESSIONS[cmd_idx % len(CRON_EXPRESSIONS)],  # expression
            "UTC",  # timezone
            status,  # status
            scheduled_at,  # scheduled_at
            started_at if status not in ["skipped", "missed"] else None,  # started_at
            completed_at if status == "completed" else None,  # completed_at
            duration_ms if status == "completed" else 0,  # duration_ms
            exit_code,  # exit_code
            f"Output from {SCHEDULED_COMMANDS[cmd_idx]}" if status == "completed" else "",  # output
            error_message,  # error_message
            error_trace,  # error_trace
            random.random() > 0.7,  # without_overlapping (bool)
            f"mutex:{SCHEDULED_COMMANDS[cmd_idx]}" if random.random() > 0.5 else "",  # mutex_name
            scheduled_at,  # expected_run_time
            delay_ms,  # delay_ms
            '{}',  # metadata
        ])

    client.insert(
        "scheduled_task_logs",
        rows,
        column_names=[
            "project_id", "task_id", "command", "description",
            "expression", "timezone", "status", "scheduled_at", "started_at",
            "completed_at", "duration_ms", "exit_code", "output",
            "error_message", "error_trace", "without_overlapping", "mutex_name",
            "expected_run_time", "delay_ms", "metadata"
        ]
    )

    print(f"Inserted {count} scheduled task log entries")


def main():
    print("Seeding job and scheduled task data...")
    print(f"Project ID: {PROJECT_ID}")
    print()

    try:
        generate_job_logs(100)
        generate_scheduled_task_logs(50)
        print()
        print("Done! Data seeded successfully.")
    except Exception as e:
        print(f"Error: {e}")
        import traceback
        traceback.print_exc()


if __name__ == "__main__":
    main()
