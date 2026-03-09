"""Frontend error logging endpoint - logs to file for debugging"""
import json
import os
from datetime import datetime
from typing import Optional

from fastapi import APIRouter
from pydantic import BaseModel

router = APIRouter()

# Log file path
LOG_DIR = os.path.join(os.path.dirname(__file__), '..', '..', 'data')
LOG_FILE = os.path.join(LOG_DIR, 'frontend-errors.log')


class FrontendLogEntry(BaseModel):
    timestamp: str
    type: str  # error, warning, navigation, api, render
    message: str
    stack: Optional[str] = None
    url: Optional[str] = None
    component: Optional[str] = None
    metadata: Optional[dict] = None


@router.post("/v1/frontend-logs")
async def log_frontend_error(entry: FrontendLogEntry):
    """Log frontend errors to file for debugging"""
    # Ensure log directory exists
    os.makedirs(LOG_DIR, exist_ok=True)

    # Format log entry
    log_line = {
        "received_at": datetime.utcnow().isoformat(),
        **entry.model_dump()
    }

    # Append to log file
    with open(LOG_FILE, 'a') as f:
        f.write(json.dumps(log_line) + '\n')

    return {"status": "logged"}


@router.get("/v1/frontend-logs")
async def get_frontend_logs(limit: int = 100):
    """Get recent frontend logs"""
    if not os.path.exists(LOG_FILE):
        return {"logs": []}

    logs = []
    with open(LOG_FILE, 'r') as f:
        for line in f:
            if line.strip():
                try:
                    logs.append(json.loads(line))
                except json.JSONDecodeError:
                    pass

    # Return last N logs
    return {"logs": logs[-limit:]}


@router.delete("/v1/frontend-logs")
async def clear_frontend_logs():
    """Clear frontend logs"""
    if os.path.exists(LOG_FILE):
        os.remove(LOG_FILE)
    return {"status": "cleared"}
