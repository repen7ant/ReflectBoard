from app.api.v1.activity import router as activity_router
from app.api.v1.health import router as health_router

from fastapi import FastAPI

app = FastAPI()

app.include_router(activity_router)
app.include_router(health_router)
