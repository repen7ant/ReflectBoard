from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse

from app.api.v1.activity import router as activity_router
from app.api.v1.analytics import router as analytics_router
from app.api.v1.category import router as category_router
from app.api.v1.health import router as health_router
from app.api.v1.websocket import router as ws_router
from app.core.config import settings
from app.services.activity_service import ForeignReferenceError

from fastapi import FastAPI, Request

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.ALLOWED_ORIGINS.split(","),
    allow_methods=["*"],
    allow_headers=["*"],
)


@app.exception_handler(ForeignReferenceError)
async def foreign_reference_handler(
    request: Request, exc: ForeignReferenceError
) -> JSONResponse:
    return JSONResponse(status_code=404, content={"detail": str(exc)})


app.include_router(activity_router)
app.include_router(category_router)
app.include_router(analytics_router)
app.include_router(ws_router)
app.include_router(health_router)
