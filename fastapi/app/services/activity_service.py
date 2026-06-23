from collections.abc import Sequence
from datetime import date, datetime, time, timedelta

from sqlalchemy import String, func, or_, select, update
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.orm import selectinload

from app.models.activity import Activity, Status
from app.schemas.activity import ActivityCreate


class ActivityService:
    @staticmethod
    async def get_activity_by_id(
        db: AsyncSession, activity_id: int, user_id: int
    ) -> Activity | None:
        result = await db.execute(
            select(Activity)
            .options(selectinload(Activity.category))
            .where(Activity.id == activity_id, Activity.user_id == user_id)
        )
        activity = result.scalar_one_or_none()
        if activity:
            await ActivityService._calculate_subtasks_counters(db, activity)
        return activity

    @staticmethod
    async def _calculate_subtasks_counters(
        db: AsyncSession, activity: Activity
    ) -> None:
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
    async def _attach_subtask_meta(
        db: AsyncSession, activities: Sequence[Activity]
    ) -> None:
        """Set subtasks_total/done for projects and parent_title for subtasks (batched)."""
        project_ids = [a.id for a in activities if a.is_project]
        counts: dict[int, list[int]] = {}  # {project_id: [total, done]}
        subtask_time: dict[int, int] = {}  # {project_id: summed subtask time}

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

            time_result = await db.execute(
                select(
                    Activity.parent_id,
                    func.coalesce(func.sum(Activity.time_spent_minutes), 0),
                )
                .where(Activity.parent_id.in_(project_ids))
                .group_by(Activity.parent_id)
            )
            subtask_time = {pid: total for pid, total in time_result.all()}

        subtask_ids = [a.parent_id for a in activities if a.parent_id]
        parent_titles: dict[int, str] = {}
        if subtask_ids:
            parent_result = await db.execute(
                select(Activity.id, Activity.title).where(Activity.id.in_(subtask_ids))
            )
            parent_titles = {pid: title for pid, title in parent_result.all()}

        for a in activities:
            if a.is_project and a.id in counts:
                a.subtasks_total = counts[a.id][0]
                a.subtasks_done = counts[a.id][1]
                # Display-only: a project's time is the sum of its subtasks'.
                # Safe because this is the terminal DB op before serialization,
                # so the dirtied attribute is never flushed/committed.
                a.time_spent_minutes = subtask_time.get(a.id, 0)
            else:
                a.subtasks_total = 0
                a.subtasks_done = 0

            a.parent_title = parent_titles.get(a.parent_id) if a.parent_id else None

    @staticmethod
    async def _recalc_and_get_parent(
        db: AsyncSession, activity: Activity, user_id: int
    ) -> Activity | None:
        """Recalc counters for the activity and its parent project (if a subtask).

        Sets activity.parent_title and returns the parent project, or None.
        """
        await ActivityService._calculate_subtasks_counters(db, activity)

        if not activity.parent_id:
            activity.parent_title = None
            return None

        parent_result = await db.execute(
            select(Activity)
            .options(selectinload(Activity.category))
            .where(Activity.id == activity.parent_id, Activity.user_id == user_id)
        )
        parent = parent_result.scalar_one_or_none()
        if parent:
            await ActivityService._calculate_subtasks_counters(db, parent)
            activity.parent_title = parent.title
        return parent

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
            .order_by(Activity.position.asc(), Activity.id.desc())
        )

        if status:
            query = query.where(Activity.status == status)
        if category_id:
            query = query.where(Activity.category_id == category_id)

        result = await db.execute(query)
        activities = result.scalars().all()

        await ActivityService._attach_subtask_meta(db, activities)

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
            .order_by(Activity.position.asc(), Activity.id.desc())
        )
        return list(result.scalars().all())

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

        parent = await ActivityService._recalc_and_get_parent(db, activity, user_id)

        return activity, parent

    @staticmethod
    async def delete_activity(
        db: AsyncSession, activity_id: int, user_id: int
    ) -> tuple[bool, int | None]:
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

        old_status = activity.status

        for key, value in update_data.items():
            if hasattr(activity, key) and key != "user_id":
                setattr(activity, key, value)

        if activity.status == Status.done and old_status != Status.done:
            activity.completed_at = func.now()
            if activity.category:
                activity.category_snapshot_name = activity.category.name
                activity.category_snapshot_color = activity.category.color
        elif old_status == Status.done and activity.status != Status.done:
            activity.completed_at = None
            activity.time_logged_minutes = activity.time_spent_minutes or 0

        await db.commit()
        await db.refresh(activity)

        parent = await ActivityService._recalc_and_get_parent(db, activity, user_id)

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
        tz_offset: int = 0,
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
                        Activity.description.ilike(search_pattern),
                        Activity.reflection_text.ilike(search_pattern),
                    )
                )

        shift = timedelta(minutes=tz_offset)

        if date_from:
            df_utc = datetime.combine(date_from, time.min) - shift
            query = query.where(Activity.completed_at >= df_utc)

        if date_to:
            dt_utc = datetime.combine(date_to, time.max) - shift
            query = query.where(Activity.completed_at <= dt_utc)

        if category_id:
            query = query.where(Activity.category_id == category_id)

        result = await db.execute(query)
        activities = result.scalars().all()

        await ActivityService._attach_subtask_meta(db, activities)

        return activities
