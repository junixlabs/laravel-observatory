from pydantic import BaseModel
from typing import Optional


class StatTrend(BaseModel):
    value: float
    is_positive: bool


class DashboardStats(BaseModel):
    total_requests: int
    error_rate: float
    avg_response_time: float
    requests_per_minute: float
    total_requests_trend: Optional[StatTrend] = None
    error_rate_trend: Optional[StatTrend] = None
    avg_response_time_trend: Optional[StatTrend] = None
    requests_per_minute_trend: Optional[StatTrend] = None


class TimeSeriesData(BaseModel):
    timestamp: str
    value: float


class EndpointStats(BaseModel):
    endpoint: str
    method: str
    request_count: int
    avg_response_time: float
    error_rate: float
    error_count: int


class ServiceHealth(BaseModel):
    service_name: str
    total_requests: int
    successful: int
    failed: int
    success_rate: float
    avg_response_time: float


class TimeSeriesPoint(BaseModel):
    timestamp: str
    requests: int
    errors: int


class RequestCounts(BaseModel):
    all: int
    inbound: int
    outbound: int


class ModuleHealth(BaseModel):
    module_name: str
    total_requests: int
    success_count: int
    error_count: int
    success_rate: float
    avg_response_time: float


class ProjectStats(BaseModel):
    project_id: str
    project_name: str
    total_requests: int
    error_rate: float
    avg_response_time: float
    health_status: str  # 'healthy', 'warning', 'critical'


class GlobalDashboardStats(BaseModel):
    total_projects: int
    total_requests: int
    overall_error_rate: float
    projects: list[ProjectStats]
    most_active_projects: list[ProjectStats]


class UserStats(BaseModel):
    user_id: str
    user_name: str
    total_requests: int
    error_count: int
    error_rate: float
    avg_response_time: float


class UserActivityPoint(BaseModel):
    timestamp: str
    requests: int
    errors: int


class UserWithErrors(BaseModel):
    user_id: str
    user_name: str
    total_requests: int
    error_count: int
    error_rate: float


class PerformancePercentiles(BaseModel):
    p50: float
    p75: float
    p90: float
    p95: float
    p99: float
    max: float
    min: float
    avg: float
    total_requests: int


class SlowestEndpoint(BaseModel):
    endpoint: str
    method: str
    count: int
    avg_time: float


class SlowRequestsSummary(BaseModel):
    total_requests: int
    slow_count: int
    slow_percentage: float
    slowest_endpoints: list[SlowestEndpoint]


class PerformanceTimelinePoint(BaseModel):
    timestamp: str
    p50: float
    p95: float
    p99: float
    avg: float


class ErrorCategory(BaseModel):
    count: int
    percentage: float


class StatusCodeBreakdown(BaseModel):
    status_code: int
    count: int
    percentage: float
    description: str


class ErrorBreakdown(BaseModel):
    total_errors: int
    client_errors_4xx: ErrorCategory
    server_errors_5xx: ErrorCategory
    by_status_code: list[StatusCodeBreakdown]


class ErrorEndpointStatus(BaseModel):
    status_code: int
    count: int


class ErrorEndpoint(BaseModel):
    endpoint: str
    method: str
    total_requests: int
    error_count: int
    error_rate: float
    top_errors: list[ErrorEndpointStatus]


class ErrorTimelinePoint(BaseModel):
    timestamp: str
    total_errors: int
    errors_4xx: int
    errors_5xx: int


class TrafficByMethod(BaseModel):
    method: str
    count: int
    percentage: float
    avg_response_time: float
    error_rate: float


class PeakHourStats(BaseModel):
    hour: int
    avg_requests: float
    peak_requests: int
    avg_response_time: float


class TrafficByDay(BaseModel):
    day_of_week: int
    day_name: str
    avg_requests: float
    peak_requests: int


class ThroughputTimeline(BaseModel):
    timestamp: str
    requests_per_minute: float


class ThroughputStats(BaseModel):
    avg_requests_per_minute: float
    peak_requests_per_minute: float
    avg_requests_per_second: float
    timeline: list[ThroughputTimeline]
