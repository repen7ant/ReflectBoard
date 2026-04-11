from app.db.session import get_db
from app.models.activity import Status
from app.schemas.activity import ActivityCreate, ActivityOut, StatusUpdate
from app.services.activity_service import ActivityService
from sqlalchemy.ext.asyncio import AsyncSession

from fastapi import APIRouter, Depends, HTTPException

router = APIRouter(prefix="/api/v1")


@router.get("/activities", response_model=list[ActivityOut])
async def get_activities(
    db: AsyncSession = Depends(get_db),
    status: Status | None = None,
    category_id: int | None = None,
):
    return await ActivityService.get_activities(db, status, category_id)


@router.post("/activities", response_model=ActivityOut, status_code=201)
async def create_activity(
    data: ActivityCreate,
    db: AsyncSession = Depends(get_db),
):
    activity = await ActivityService.create_activity(db, data)
    return activity


@router.delete("/activities/{activity_id}", status_code=204)
async def delete_activity(
    activity_id: int,
    db: AsyncSession = Depends(get_db),
):
    deleted = await ActivityService.delete_activity(db, activity_id)
    if not deleted:
        raise HTTPException(status_code=404, detail="Activity not found")


@router.patch("/activities/{activity_id}/status", response_model=ActivityOut)
async def update_activity_status(
    activity_id: int,
    data: StatusUpdate,
    db: AsyncSession = Depends(get_db),
):
    activity = await ActivityService.update_status(db, activity_id, data.status)
    if not activity:
        raise HTTPException(status_code=404, detail="Activity not found")
    return activity
