from datetime import date

from sqlalchemy import String, func, or_, select, update
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.orm import selectinload

from app.models.activity import Activity, Status
from app.schemas.activity import ActivityCreate


class ActivityService:
    @staticmethod
    async def _calculate_subtasks_counters(db: AsyncSession, activity: Activity) -> None:
        """Calculate and set subtasks_total and subtasks_done for a project"""
        if activity.is_project:
            total_result = await db.execute(
                select(func.count()).where(Activity.parent_id == activity.id)
            )
            activity.subtasks_total = total_result.scalar() or 0

            done_result = await db.execute(
                select(func.count()).where(
                    Activity.parent_id == activity.id,
                    Activity.status == Status.done,
                )
            )
            activity.subtasks_done = done_result.scalar() or 0
        else:
            activity.subtasks_total = 0
            activity.subtasks_done = 0

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
            .where(Activity.status != Status.done)
            .where(
                or_(
                    Activity.parent_id.is_(None),
                    Activity.is_on_board.is_(True),
                )
            )
            .order_by(Activity.position.asc())
        )

        if status:
            query = query.where(Activity.status == status)
        if category_id:
            query = query.where(Activity.category_id == category_id)

        result = await db.execute(query)
        activities = result.scalars().all()

        project_ids = [a.id for a in activities if a.is_project]
        counts: dict[int, tuple[int, int]] = {}  # {project_id: (total, done)}

        if project_ids:
            total_result = await db.execute(
                select(Activity.parent_id, func.count().label("cnt"))
                .where(Activity.parent_id.in_(project_ids))
                .group_by(Activity.parent_id)
            )
            for parent_id, cnt in total_result.all():
                counts.setdefault(parent_id, [0, 0])
                counts[parent_id][0] = cnt

            done_result = await db.execute(
                select(Activity.parent_id, func.count().label("cnt"))
                .where(
                    Activity.parent_id.in_(project_ids),
                    Activity.status == Status.done,
                )
                .group_by(Activity.parent_id)
            )
            for parent_id, cnt in done_result.all():
                counts.setdefault(parent_id, [0, 0])
                counts[parent_id][1] = cnt

        # Get parent titles for subtasks
        subtask_ids = [a.parent_id for a in activities if a.parent_id]
        parent_titles = {}
        if subtask_ids:
            parent_result = await db.execute(
                select(Activity.id, Activity.title)
                .where(Activity.id.in_(subtask_ids))
            )
            parent_titles = {pid: title for pid, title in parent_result.all()}

        for a in activities:
            if a.is_project and a.id in counts:
                a.subtasks_total = counts[a.id][0]
                a.subtasks_done = counts[a.id][1]
            else:
                a.subtasks_total = 0
                a.subtasks_done = 0

            # Set parent title for subtasks
            if a.parent_id and a.parent_id in parent_titles:
                a.parent_title = parent_titles[a.parent_id]
            else:
                a.parent_title = None

        return activities

    @staticmethod
    async def get_subtasks(
        db: AsyncSession,
        project_id: int,
        user_id: int,
    ) -> list[Activity]:
        result = await db.execute(
            select(Activity)
            .options(selectinload(Activity.category))
            .where(Activity.parent_id == project_id, Activity.user_id == user_id)
            .order_by(Activity.position.asc())
        )
        return result.scalars().all()

    @staticmethod
    async def create_activity(
        db: AsyncSession,
        data: ActivityCreate,
        user_id: int,
    ) -> tuple[Activity, Activity | None]:
        """Create activity and return (activity, parent_project_if_updated)"""
        activity_dict = data.model_dump(exclude={"user_id"}, exclude_unset=True)

        if activity_dict.get("is_quick_capture"):
            activity_dict["status"] = Status.done
            activity_dict["completed_at"] = func.now()

        activity = Activity(**activity_dict, user_id=user_id)
        db.add(activity)
        await db.commit()
        result = await db.execute(
            select(Activity)
            .options(selectinload(Activity.category))
            .where(Activity.id == activity.id)
        )
        activity = result.scalar_one()

        # Calculate subtasks counters
        await ActivityService._calculate_subtasks_counters(db, activity)

        # If this is a subtask, get updated parent project
        parent = None
        if activity.parent_id:
            parent_result = await db.execute(
                select(Activity)
                .options(selectinload(Activity.category))
                .where(Activity.id == activity.parent_id, Activity.user_id == user_id)
            )
            parent = parent_result.scalar_one_or_none()
            if parent:
                await ActivityService._calculate_subtasks_counters(db, parent)
                activity.parent_title = parent.title
        else:
            activity.parent_title = None

        return activity, parent

    @staticmethod
    async def delete_activity(db: AsyncSession, activity_id: int, user_id: int) -> tuple[bool, int | None]:
        """Delete activity and return (success, parent_id_if_exists)"""
        result = await db.execute(
            select(Activity).where(
                Activity.id == activity_id, Activity.user_id == user_id
            )
        )
        activity = result.scalar_one_or_none()

        if not activity:
            return False, None

        parent_id = activity.parent_id
        await db.delete(activity)
        await db.commit()
        return True, parent_id

    @staticmethod
    async def update_activity(
        db: AsyncSession, activity_id: int, user_id: int, update_data: dict
    ) -> tuple[Activity | None, Activity | None]:
        """Update activity and return (activity, parent_project_if_updated)"""
        result = await db.execute(
            select(Activity)
            .options(selectinload(Activity.category))
            .where(Activity.id == activity_id, Activity.user_id == user_id)
        )
        activity = result.scalar_one_or_none()

        if not activity:
            return None, None

        # If subtask is being added to board, inherit category and deadline from parent
        if activity.parent_id and update_data.get("is_on_board") is True:
            parent_result = await db.execute(
                select(Activity)
                .options(selectinload(Activity.category))
                .where(Activity.id == activity.parent_id, Activity.user_id == user_id)
            )
            parent = parent_result.scalar_one_or_none()
            if parent:
                if not activity.category_id and parent.category_id:
                    activity.category_id = parent.category_id
                if not activity.deadline and parent.deadline:
                    activity.deadline = parent.deadline

        for key, value in update_data.items():
            if hasattr(activity, key) and key != "user_id":
                setattr(activity, key, value)

        if update_data.get("status") == Status.done:
            activity.completed_at = func.now()
            if activity.category:
                activity.category_snapshot_name = activity.category.name
                activity.category_snapshot_color = activity.category.color

        await db.commit()
        await db.refresh(activity)

        # Calculate subtasks counters
        await ActivityService._calculate_subtasks_counters(db, activity)

        # If this is a subtask, get updated parent project
        parent = None
        if activity.parent_id:
            parent_result = await db.execute(
                select(Activity)
                .options(selectinload(Activity.category))
                .where(Activity.id == activity.parent_id, Activity.user_id == user_id)
            )
            parent = parent_result.scalar_one_or_none()
            if parent:
                await ActivityService._calculate_subtasks_counters(db, parent)
                activity.parent_title = parent.title
        else:
            activity.parent_title = None

        return activity, parent

    @staticmethod
    async def reorder_activities(
        db: AsyncSession,
        user_id: int,
        activity_id: int | None,
        new_status: str | None,
        ordered_ids: list[int],
    ) -> None:
        if activity_id and new_status:
            activity, _ = await ActivityService.update_activity(
                db, activity_id, user_id, {"status": new_status}
            )

        for index, act_id in enumerate(ordered_ids):
            await db.execute(
                update(Activity)
                .where(Activity.id == act_id, Activity.user_id == user_id)
                .values(position=index)
            )
        await db.commit()

    @staticmethod
    async def get_done_activities(
        db: AsyncSession,
        user_id: int,
        search: str | None = None,
        date_from: date | None = None,
        date_to: date | None = None,
        category_id: int | None = None,
    ):
        query = (
            select(Activity)
            .options(selectinload(Activity.category))
            .where(Activity.user_id == user_id, Activity.status == Status.done)
            .order_by(Activity.completed_at.desc())
        )

        if search:
            if search.startswith("#"):
                tag = search[1:].strip()
                if tag:
                    tag_pattern = f'%"{tag}"%'
                    query = query.where(Activity.tags.cast(String).ilike(tag_pattern))
            else:
                search_pattern = f"%{search}%"
                query = query.where(
                    or_(
                        Activity.title.ilike(search_pattern),
                        Activity.reflection_text.ilike(search_pattern),
                    )
                )

        if date_from:
            query = query.where(Activity.completed_at >= date_from)

        if date_to:
            from datetime import datetime

            date_to_end = datetime.combine(date_to, datetime.max.time())
            query = query.where(Activity.completed_at <= date_to_end)

        if category_id:
            query = query.where(Activity.category_id == category_id)

        result = await db.execute(query)
        return result.scalars().all()
