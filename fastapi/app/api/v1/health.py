import redis.asyncio as aioredis
from app.core.config import settings
from app.db.session import get_db
from sqlalchemy import text
from sqlalchemy.ext.asyncio import AsyncSession

from fastapi import APIRouter, Depends, HTTPException, status

router = APIRouter(prefix="/api/v1")


@router.get("/health", tags=["System"])
async def health_check(db: AsyncSession = Depends(get_db)):
    """Checks the health of the API and all dependent services."""
    health_status = {"api": "ok", "db": "unknown", "redis": "unknown"}

    try:
        await db.execute(text("SELECT 1"))
        health_status["db"] = "ok"

        r = await aioredis.from_url(settings.REDIS_URL)
        await r.ping()
        await r.aclose()
        health_status["redis"] = "ok"

        return health_status

    except Exception as e:
        health_status["error"] = str(e)
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE, detail=health_status
        )
