from app.api.v1.activity import router as activity_router
from app.api.v1.category import router as category_router
from app.api.v1.health import router as health_router
from app.api.v1.websocket import router as ws_router
from app.core.config import settings
from fastapi.middleware.cors import CORSMiddleware

from fastapi import FastAPI

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.ALLOWED_ORIGINS.split(","),
    allow_methods=["*"],
    allow_headers=["*"],
)

app.include_router(activity_router)
app.include_router(category_router)
app.include_router(ws_router)
app.include_router(health_router)
