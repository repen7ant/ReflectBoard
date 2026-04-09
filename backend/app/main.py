from app.api.v1.activity import router as activity_router
from fastapi import FastAPI

app = FastAPI()

app.include_router(activity_router.router, prefix="/api/v1")
