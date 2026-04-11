import redis.asyncio as aioredis
from app.core.config import settings
from app.db.session import get_db
from sqlalchemy import text
from sqlalchemy.ext.asyncio import AsyncSession

from fastapi import APIRouter, Depends

router = APIRouter(prefix="/api/v1/health")


@router.get("/db")
async def check_db(db: AsyncSession = Depends(get_db)):
    result = await db.execute(text("SELECT 1"))
    return {"status": "ok", "result": result.scalar()}


@router.get("/redis")
async def check_redis():
    r = await aioredis.from_url(settings.REDIS_URL)
    await r.set("fastapi_ping", "pong", ex=10)
    val = await r.get("fastapi_ping")
    await r.aclose()
    return {"status": "ok", "value": val.decode()}
