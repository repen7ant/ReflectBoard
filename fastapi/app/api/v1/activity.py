from app.db.session import get_db
from app.dependencies.auth import get_current_user
from app.models.activity import Status
from app.models.user import User
from app.schemas.activity import ActivityCreate, ActivityOut, ActivityUpdate
from app.services.activity_service import ActivityService
from sqlalchemy.ext.asyncio import AsyncSession

from fastapi import APIRouter, Depends, HTTPException

router = APIRouter(prefix="/api/v1")


@router.get("/activities", response_model=list[ActivityOut])
async def get_activities(
    db: AsyncSession = Depends(get_db),
    status: Status | None = None,
    category_id: int | None = None,
    current_user: User = Depends(get_current_user),
):
    return await ActivityService.get_activities(
        db, current_user.id, status, category_id
    )


@router.post("/activities", response_model=ActivityOut, status_code=201)
async def create_activity(
    data: ActivityCreate,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    activity = await ActivityService.create_activity(db, data, current_user.id)
    return activity


@router.delete("/activities/{activity_id}", status_code=204)
async def delete_activity(
    activity_id: int,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    deleted = await ActivityService.delete_activity(db, activity_id, current_user.id)
    if not deleted:
        raise HTTPException(status_code=404, detail="Activity not found")


@router.patch("/activities/{activity_id}", response_model=ActivityOut)
async def update_activity(
    activity_id: int,
    data: ActivityUpdate,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    update_dict = data.model_dump(exclude_unset=True)
    activity = await ActivityService.update_activity(
        db, activity_id, current_user.id, update_dict
    )

    if not activity:
        raise HTTPException(status_code=404, detail="Activity not found")

    return activity
