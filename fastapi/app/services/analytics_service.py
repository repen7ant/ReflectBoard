from collections import defaultdict
from datetime import datetime, time, timedelta, timezone

from sqlalchemy import func, select
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.orm import selectinload

from app.models.activity import Activity, Status


def _date_from_period(period: str, tz_offset: int) -> datetime | None:
    days = {"7d": 7, "30d": 30, "90d": 90}.get(period)
    if days is None:
        return None  # "all"
    shift = timedelta(minutes=tz_offset)
    local_today = (datetime.now(timezone.utc) + shift).date()
    local_midnight = datetime.combine(local_today - timedelta(days=days), time.min)
    return local_midnight - shift  # back to UTC for the completed_at filter


class AnalyticsService:
    @staticmethod
    async def get_analytics(
        db: AsyncSession,
        user_id: int,
        period: str,  # "7d" | "30d" | "90d" | "all"
        tz_offset: int = 0,
    ) -> dict:
        shift = timedelta(minutes=tz_offset)
        date_from = _date_from_period(period, tz_offset)

        # ── Base query for completed tasks ─────────────────────
        query = (
            select(Activity)
            .options(selectinload(Activity.category))
            .where(
                Activity.user_id == user_id,
                Activity.status == Status.done,
                Activity.completed_at.isnot(None),
            )
        )
        if date_from:
            query = query.where(Activity.completed_at >= date_from)

        result = await db.execute(query)
        activities = result.scalars().all()

        # ── Base query for created tasks (for completion rate) ──
        created_query = select(func.count()).where(
            Activity.user_id == user_id,
        )
        if date_from:
            created_query = created_query.where(Activity.created_at >= date_from)
        created_result = await db.execute(created_query)
        total_created = created_result.scalar() or 0

        # ── Overview metrics ──────────────────────────────────
        total_done = len(activities)
        productive_done = sum(1 for a in activities if a.is_productive)
        unproductive_done = total_done - productive_done
        total_minutes = sum(a.time_spent_minutes or 0 for a in activities)
        productive_minutes = sum(a.time_spent_minutes or 0 for a in activities if a.is_productive)
        unproductive_minutes = sum(a.time_spent_minutes or 0 for a in activities if not a.is_productive)
        completion_rate = (
            round(total_done / total_created * 100) if total_created > 0 else 0
        )
        streak = await AnalyticsService._calculate_streak(db, user_id, tz_offset)

        # ── Heatmap ───────────────────────────────────────────
        heatmap: dict[str, int] = defaultdict(int)
        for a in activities:
            if a.completed_at is None:
                continue
            day = (a.completed_at + shift).date().isoformat()
            heatmap[day] += 1

        # ── Categories ────────────────────────────────────────
        category_stats: dict[str, dict] = {}
        for a in activities:
            # Use category or snapshot
            if a.category:
                cat_key = str(a.category_id)
                cat_name = a.category.name
                cat_color = a.category.color
            elif a.category_snapshot_name:
                cat_key = f"snapshot_{a.category_snapshot_name}"
                cat_name = a.category_snapshot_name
                cat_color = a.category_snapshot_color or "#717c7c"
            else:
                continue

            if cat_key not in category_stats:
                category_stats[cat_key] = {
                    "name": cat_name,
                    "color": cat_color,
                    "minutes": 0,
                    "count": 0,
                }
            category_stats[cat_key]["minutes"] += a.time_spent_minutes or 0
            category_stats[cat_key]["count"] += 1

        categories = sorted(
            category_stats.values(),
            key=lambda x: x["minutes"],
            reverse=True,
        )

        # ── Tag cloud ─────────────────────────────────────────
        tag_counts: dict[str, int] = defaultdict(int)
        for a in activities:
            for tag in a.tags or []:
                tag_counts[tag.lower()] += 1

        # Minimum threshold — 3 occurrences
        tags = [
            {"tag": tag, "count": count}
            for tag, count in sorted(tag_counts.items(), key=lambda x: -x[1])
            if count >= 3
        ]

        return {
            "overview": {
                "total_done": total_done,
                "productive_done": productive_done,
                "unproductive_done": unproductive_done,
                "total_minutes": total_minutes,
                "productive_minutes": productive_minutes,
                "unproductive_minutes": unproductive_minutes,
                "streak": streak,
                "completion_rate": completion_rate,
            },
            "heatmap": dict(heatmap),  # {"2025-05-01": 3, ...}
            "categories": categories,  # [{name, color, minutes, count}]
            "tags": tags,  # [{tag, count}]
        }

    @staticmethod
    async def _calculate_streak(
        db: AsyncSession, user_id: int, tz_offset: int = 0
    ) -> int:
        """Current streak — consecutive local days with >= 1 completed task."""
        shift = timedelta(minutes=tz_offset)
        cutoff = datetime.now(timezone.utc) - timedelta(days=370)
        result = await db.execute(
            select(Activity.completed_at).where(
                Activity.user_id == user_id,
                Activity.status == Status.done,
                Activity.completed_at.isnot(None),
                Activity.completed_at >= cutoff,
            )
        )
        local_days = sorted(
            {(c + shift).date() for (c,) in result.all()}, reverse=True
        )
        if not local_days:
            return 0

        today = (datetime.now(timezone.utc) + shift).date()
        streak = 0
        expected = today

        for day in local_days:
            if day == expected:
                streak += 1
                expected -= timedelta(days=1)
            elif day == today - timedelta(days=1) and streak == 0:
                streak += 1
                expected = day - timedelta(days=1)
            else:
                break

        return streak
