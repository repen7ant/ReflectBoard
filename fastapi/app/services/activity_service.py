from app.models.activity import Activity, Status
from app.schemas.activity import ActivityCreate
from sqlalchemy import func, select, update
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.orm import selectinload


class ActivityService:
    @staticmethod
    async def get_activities(
        db: AsyncSession,
        user_id: int,
        status: Status | None = None,
        category_id: int | None = None,
    ):
        query = (
            select(Activity)
            .options(selectinload(Activity.category))
            .where(Activity.user_id == user_id)
            .order_by(Activity.position.asc())
        )

        if status:
            query = query.where(Activity.status == status)
        if category_id:
            query = query.where(Activity.category_id == category_id)

        result = await db.execute(query)
        return result.scalars().all()

    @staticmethod
    async def create_activity(
        db: AsyncSession,
        data: ActivityCreate,
        user_id: int,
    ) -> Activity:
        activity_dict = data.model_dump(exclude={"user_id"}, exclude_unset=True)
        activity = Activity(**activity_dict, user_id=user_id)

        db.add(activity)
        await db.commit()
        result = await db.execute(
            select(Activity)
            .options(selectinload(Activity.category))
            .where(Activity.id == activity.id)
        )
        return result.scalar_one()

    @staticmethod
    async def delete_activity(db: AsyncSession, activity_id: int, user_id: int) -> bool:
        result = await db.execute(
            select(Activity).where(
                Activity.id == activity_id, Activity.user_id == user_id
            )
        )
        activity = result.scalar_one_or_none()

        if not activity:
            return False
        await db.delete(activity)
        await db.commit()
        return True

    @staticmethod
    async def update_activity(
        db: AsyncSession, activity_id: int, user_id: int, update_data: dict
    ) -> Activity | None:
        result = await db.execute(
            select(Activity)
            .options(selectinload(Activity.category))
            .where(Activity.id == activity_id, Activity.user_id == user_id)
        )
        activity = result.scalar_one_or_none()

        if not activity:
            return None

        for key, value in update_data.items():
            if hasattr(activity, key) and key != "user_id":
                setattr(activity, key, value)

        if update_data.get("status") == Status.done:
            activity.completed_at = func.now()

        await db.commit()
        await db.refresh(activity)
        return activity

    @staticmethod
    async def reorder_activities(
        db: AsyncSession,
        user_id: int,
        activity_id: int | None,
        new_status: str | None,
        ordered_ids: list[int],
    ) -> None:

        if activity_id and new_status:
            await ActivityService.update_activity(
                db, activity_id, user_id, {"status": new_status}
            )

        for index, act_id in enumerate(ordered_ids):
            await db.execute(
                update(Activity)
                .where(Activity.id == act_id, Activity.user_id == user_id)
                .values(position=index)
            )
        await db.commit()
