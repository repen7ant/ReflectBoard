from app.models.activity import Activity, Status
from app.schemas.activity import ActivityCreate
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.orm import selectinload


class ActivityService:
    @staticmethod
    async def get_activities(
        db: AsyncSession,
        status: Status | None = None,
        category_id: int | None = None,
    ):
        query = select(Activity).options(selectinload(Activity.category))

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
    ) -> Activity:
        activity = Activity(**data.model_dump())
        db.add(activity)
        await db.commit()
        await db.refresh(activity, ["category"])
        return activity

    @staticmethod
    async def delete_activity(
        db: AsyncSession,
        activity_id: int,
    ) -> bool:
        activity = await db.get(Activity, activity_id)
        if not activity:
            return False
        await db.delete(activity)
        await db.commit()
        return True

    @staticmethod
    async def update_status(
        db: AsyncSession, activity_id: int, status: Status
    ) -> Activity | None:
        result = await db.execute(
            select(Activity)
            .options(selectinload(Activity.category))
            .where(Activity.id == activity_id)
        )
        activity = result.scalar_one_or_none()
        if not activity:
            return None
        activity.status = status
        await db.commit()
        await db.refresh(activity)
        return activity
