from pydantic import BaseModel, Field
from typing import Optional
from datetime import datetime


class OutboundLogEntry(BaseModel):
    """Log entry for outbound HTTP requests to third-party services."""
    # Required identifiers
    request_id: str = Field(..., description="Unique request identifier")
    timestamp: datetime = Field(default_factory=datetime.utcnow)

    # Service info
    service_name: str = Field(..., description="Name of the third-party service")
    target_host: str = Field(..., description="Target host/domain")
    target_url: str = Field(..., description="Full target URL")

    # Request details
    method: str = Field(..., description="HTTP method (GET, POST, etc.)")

    # Response
    status_code: int = Field(..., ge=100, le=599, description="HTTP status code")
    latency_ms: float = Field(..., ge=0, description="Response time in milliseconds")

    # Optional identifiers for distributed tracing
    parent_request_id: Optional[str] = Field(None, description="Parent request ID")
    trace_id: Optional[str] = Field(None, description="Distributed trace ID")
    span_id: Optional[str] = Field(None, description="Span ID")

    # Sizes
    request_size: Optional[int] = Field(0, ge=0, description="Request size in bytes")
    response_size: Optional[int] = Field(0, ge=0, description="Response size in bytes")

    # Error info
    error_message: Optional[str] = Field(None, description="Error message if failed")
    error_code: Optional[str] = Field(None, description="Error code")
    retry_count: Optional[int] = Field(0, ge=0, description="Number of retries")

    # Context
    module: Optional[str] = Field(None, description="Application module")
    user_id: Optional[str] = Field(None, description="User ID")

    # Optional headers & body (JSON strings)
    request_headers: Optional[str] = Field(None, description="Request headers JSON")
    response_headers: Optional[str] = Field(None, description="Response headers JSON")
    request_body: Optional[str] = Field(None, description="Request body (truncated)")
    response_body: Optional[str] = Field(None, description="Response body (truncated)")

    # Custom
    tags: Optional[list[str]] = Field(default_factory=list, description="Custom tags")
    metadata: Optional[str] = Field(None, description="Additional metadata JSON")


class BatchOutboundIngestRequest(BaseModel):
    """Batch request for ingesting multiple outbound log entries."""
    logs: list[OutboundLogEntry] = Field(..., description="Outbound log entries")


class OutboundIngestResponse(BaseModel):
    """Response for outbound ingest operations."""
    success: bool
    message: str
    ingested_count: int = 0


# Query response models

class OutboundLogResponse(BaseModel):
    """Outbound log entry for query responses."""
    id: str
    project_id: str
    request_id: str
    parent_request_id: str
    trace_id: str
    span_id: str
    timestamp: str
    service_name: str
    target_host: str
    target_url: str
    method: str
    status_code: int
    latency_ms: float
    is_success: bool
    request_size: int
    response_size: int
    error_message: str
    error_code: str
    retry_count: int
    module: str
    user_id: str
    tags: list[str]


class OutboundLogDetail(OutboundLogResponse):
    """Detailed outbound log entry including headers and body."""
    request_headers: str
    response_headers: str
    request_body: str
    response_body: str
    metadata: str


class OutboundPaginatedResponse(BaseModel):
    """Paginated response for outbound logs."""
    data: list[OutboundLogResponse]
    total: int
    page: int
    page_size: int
    total_pages: int


class OutboundServiceStats(BaseModel):
    """Statistics for a specific service."""
    service_name: str
    total_requests: int
    success_count: int
    failure_count: int
    success_rate: float
    avg_latency_ms: float
    p95_latency_ms: float
    error_rate: float


class OutboundHostStats(BaseModel):
    """Statistics for a specific host."""
    target_host: str
    total_requests: int
    success_count: int
    failure_count: int
    success_rate: float
    avg_latency_ms: float


class OutboundOverallStats(BaseModel):
    """Overall outbound statistics."""
    total_requests: int
    success_count: int
    failure_count: int
    success_rate: float
    avg_latency_ms: float
    p95_latency_ms: float
    services_count: int
    timeout_count: int
    total_retries: int


class OutboundEndpointStats(BaseModel):
    """Statistics for a specific endpoint of a service."""
    endpoint_pattern: str  # URL path pattern (grouped)
    method: str  # HTTP method
    total_requests: int
    success_count: int
    failure_count: int
    success_rate: float
    error_rate: float
    avg_latency_ms: float
    p95_latency_ms: float
    p99_latency_ms: float
    avg_request_size: float
    avg_response_size: float
