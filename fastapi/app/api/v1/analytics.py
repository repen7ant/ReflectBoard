from app.db.session import get_db
from app.dependencies.auth import get_current_user
from app.models.user import User
from app.services.analytics_service import AnalyticsService
from app.services.redis_service import get_live_stats
from sqlalchemy.ext.asyncio import AsyncSession

from fastapi import APIRouter, Depends, Query

router = APIRouter(prefix="/api/v1", tags=["Analytics"])


@router.get("/analytics")
async def get_analytics(
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_user),
    period: str = Query(default="30d", pattern="^(7d|30d|90d|all)$"),
    tz_offset: int = Query(default=0),
):
    historical = await AnalyticsService.get_analytics(
        db, current_user.id, period, tz_offset
    )
    live = await get_live_stats(current_user.id)

    return {**historical, "live": live}
