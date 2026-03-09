from functools import lru_cache

from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    # Server
    host: str = "0.0.0.0"
    port: int = 8000
    debug: bool = False

    # PostgreSQL Database
    database_url: str = "postgresql+asyncpg://sidmonitor:password@localhost:5432/sidmonitor"

    # ClickHouse
    clickhouse_host: str = "localhost"
    clickhouse_port: int = 8123
    clickhouse_database: str = "sid_monitoring"
    clickhouse_user: str = "default"
    clickhouse_password: str = ""

    # JWT Authentication
    jwt_secret_key: str = "your-secret-key-change-this-in-production"
    jwt_algorithm: str = "HS256"
    jwt_access_token_expire_minutes: int = 30

    # Auth (legacy basic auth)
    auth_username: str = "admin"
    auth_password: str = "changeme"

    # CORS
    cors_origins: str = "http://localhost:5173"

    # Ingest API Keys (comma-separated)
    ingest_api_keys: str = ""

    @property
    def cors_origins_list(self) -> list[str]:
        return [origin.strip() for origin in self.cors_origins.split(",")]

    @property
    def ingest_api_keys_list(self) -> list[str]:
        if not self.ingest_api_keys:
            return []
        return [key.strip() for key in self.ingest_api_keys.split(",") if key.strip()]

    class Config:
        env_file = ".env"
        env_file_encoding = "utf-8"


@lru_cache()
def get_settings() -> Settings:
    return Settings()
