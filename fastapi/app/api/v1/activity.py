import json
from datetime import date

from app.db.redis import redis_client
from app.db.session import get_db
from app.dependencies.auth import get_current_user
from app.models.activity import Status
from app.models.user import User
from app.schemas.activity import ActivityCreate, ActivityOut, ActivityUpdate
from app.services.activity_service import ActivityService
from fastapi.encoders import jsonable_encoder
from sqlalchemy.ext.asyncio import AsyncSession

from fastapi import APIRouter, Depends, HTTPException, Query

router = APIRouter(prefix="/api/v1", tags=["Activities"])


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


@router.get("/activities/done", response_model=list[ActivityOut])
async def get_done_activities(
    db: AsyncSession = Depends(get_db),
    search: str | None = Query(None),
    date_from: date | None = Query(None),
    date_to: date | None = Query(None),
    category_id: int | None = Query(None),
    current_user: User = Depends(get_current_user),
):
    return await ActivityService.get_done_activities(
        db, current_user.id, search, date_from, date_to, category_id
    )


@router.get("/activities/{activity_id}/subtasks", response_model=list[ActivityOut])
async def get_subtasks(
    activity_id: int,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    return await ActivityService.get_subtasks(db, activity_id, current_user.id)


@router.post("/activities", response_model=ActivityOut, status_code=201)
async def create_activity(
    data: ActivityCreate,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    activity, parent = await ActivityService.create_activity(db, data, current_user.id)

    activity_out = ActivityOut.model_validate(activity)
    payload = json.dumps({"action": "create", "data": jsonable_encoder(activity_out)})
    await redis_client.publish(f"board:{current_user.id}", payload)

    if parent:
        parent_out = ActivityOut.model_validate(parent)
        parent_payload = json.dumps(
            {"action": "update", "data": jsonable_encoder(parent_out)}
        )
        await redis_client.publish(f"board:{current_user.id}", parent_payload)

    return activity


@router.delete("/activities/{activity_id}", status_code=204)
async def delete_activity(
    activity_id: int,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    deleted, parent_id = await ActivityService.delete_activity(
        db, activity_id, current_user.id
    )
    if not deleted:
        raise HTTPException(status_code=404, detail="Activity not found")

    payload = json.dumps({"action": "delete", "data": {"id": activity_id}})
    await redis_client.publish(f"board:{current_user.id}", payload)

    if parent_id:
        parent = await ActivityService.get_activity_by_id(
            db, parent_id, current_user.id
        )
        if parent:
            parent_out = ActivityOut.model_validate(parent)
            parent_payload = json.dumps(
                {"action": "update", "data": jsonable_encoder(parent_out)}
            )
            await redis_client.publish(f"board:{current_user.id}", parent_payload)


@router.patch("/activities/{activity_id}", response_model=ActivityOut)
async def update_activity(
    activity_id: int,
    data: ActivityUpdate,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    update_dict = data.model_dump(exclude_unset=True)
    activity, parent = await ActivityService.update_activity(
        db, activity_id, current_user.id, update_dict
    )

    if not activity:
        raise HTTPException(status_code=404, detail="Activity not found")

    activity_out = ActivityOut.model_validate(activity)
    payload = json.dumps({"action": "update", "data": jsonable_encoder(activity_out)})
    await redis_client.publish(f"board:{current_user.id}", payload)

    if parent:
        parent_out = ActivityOut.model_validate(parent)
        parent_payload = json.dumps(
            {"action": "update", "data": jsonable_encoder(parent_out)}
        )
        await redis_client.publish(f"board:{current_user.id}", parent_payload)

    return activity


@router.post("/activities/reorder")
async def reorder_activities(
    data: dict,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    activity_id = data.get("activity_id")
    new_status = data.get("new_status")
    ordered_ids = data.get("ordered_ids", [])

    await ActivityService.reorder_activities(
        db=db,
        user_id=current_user.id,
        activity_id=activity_id,
        new_status=new_status,
        ordered_ids=ordered_ids,
    )

    payload = json.dumps({"action": "reorder", "data": {}})
    await redis_client.publish(f"board:{current_user.id}", payload)

    return {"success": True}
