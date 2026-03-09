.PHONY: help up down dev dev-db dev-backend dev-frontend logs clean install build

help: ## Show this help
	@echo "SidMonitor - Commands"
	@echo ""
	@echo "Docker (full stack):"
	@echo "  make up              Start all services (postgres, clickhouse, backend, frontend)"
	@echo "  make down            Stop all services"
	@echo "  make logs            Show service logs"
	@echo ""
	@echo "Local development:"
	@echo "  make dev-db          Start databases only (postgres + clickhouse)"
	@echo "  make dev-backend     Start backend (port 8030)"
	@echo "  make dev-frontend    Start frontend (port 3030)"
	@echo "  make dev             Start databases + instructions"
	@echo ""
	@echo "Utilities:"
	@echo "  make install         Install all dependencies"
	@echo "  make build           Build frontend for production"
	@echo "  make clean           Remove build artifacts"

# ---- Docker (full stack) ----

up: ## Start full stack with Docker
	docker compose up -d --build
	@echo ""
	@echo "SidMonitor is running:"
	@echo "  Frontend: http://localhost:$${FRONTEND_PORT:-3000}"
	@echo "  Backend:  http://localhost:$${BACKEND_PORT:-8000}"
	@echo "  API Docs: http://localhost:$${BACKEND_PORT:-8000}/docs"

down: ## Stop all Docker services
	docker compose down

logs: ## Show Docker logs
	docker compose logs -f

# ---- Local development ----

dev-db: ## Start databases only (for local dev)
	docker compose -f docker-compose.dev.yml up -d
	@echo "Waiting for services..."
	@sleep 3
	@echo "PostgreSQL: localhost:5432"
	@echo "ClickHouse: localhost:8123"

dev-backend: ## Start backend locally
	cd backend && python -m uvicorn app.main:app --host 0.0.0.0 --port 8030 --reload

dev-frontend: ## Start frontend locally
	cd frontend && npm run dev

dev: dev-db ## Start databases + show instructions
	@echo ""
	@echo "Databases ready. Start in separate terminals:"
	@echo "  make dev-backend     (port 8030)"
	@echo "  make dev-frontend    (port 3030)"

# ---- Utilities ----

install: ## Install all dependencies
	cd frontend && npm install
	cd backend && pip install -r requirements.txt

build: ## Build frontend
	cd frontend && npm run build

clean: ## Remove build artifacts
	rm -rf frontend/node_modules frontend/dist
	find . -type d -name __pycache__ -exec rm -rf {} + 2>/dev/null || true
	find . -type f -name "*.pyc" -delete 2>/dev/null || true
