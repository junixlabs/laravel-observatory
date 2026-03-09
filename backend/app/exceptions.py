"""Custom exception classes and global exception handlers."""

from fastapi import Request
from fastapi.responses import JSONResponse


class AppException(Exception):
    """Base application exception."""

    def __init__(self, message: str, status_code: int = 500):
        self.message = message
        self.status_code = status_code
        super().__init__(self.message)


class NotFoundException(AppException):
    """Resource not found."""

    def __init__(self, message: str = "Resource not found"):
        super().__init__(message, status_code=404)


class ValidationException(AppException):
    """Validation error."""

    def __init__(self, message: str = "Validation error"):
        super().__init__(message, status_code=422)


class AuthenticationException(AppException):
    """Authentication failed."""

    def __init__(self, message: str = "Not authenticated"):
        super().__init__(message, status_code=401)


class ClickHouseException(AppException):
    """ClickHouse query/connection error."""

    def __init__(self, message: str = "Database query error"):
        super().__init__(message, status_code=500)


class IngestException(AppException):
    """Data ingestion error."""

    def __init__(self, message: str = "Ingestion error"):
        super().__init__(message, status_code=500)


async def app_exception_handler(request: Request, exc: AppException) -> JSONResponse:
    """Global handler for AppException and subclasses."""
    return JSONResponse(
        status_code=exc.status_code,
        content={"detail": exc.message},
    )
