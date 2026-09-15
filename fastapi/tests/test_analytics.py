import pytest
from app.models.activity import Activity, Status
from app.models.user import User
from httpx import AsyncClient
from sqlalchemy.ext.asyncio import AsyncSession
from datetime import datetime, timedelta, timezone

pytestmark = pytest.mark.asyncio


class TestAnalytics:
    async def test_empty_analytics(self, client: AsyncClient):
        res = await client.get("/api/v1/analytics?period=30d")
        assert res.status_code == 200
        data = res.json()
        assert data["overview"]["total_done"] == 0
        assert data["overview"]["productive_minutes"] == 0
        assert data["overview"]["unproductive_minutes"] == 0

    async def test_productive_minutes_counted_separately(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        now = datetime.now(timezone.utc)
        db.add(Activity(
            user_id=test_user.id,
            title="Productive task",
            status=Status.done,
            time_spent_minutes=60,
            is_productive=True,
            completed_at=now,
        ))
        db.add(Activity(
            user_id=test_user.id,
            title="Unproductive task",
            status=Status.done,
            time_spent_minutes=30,
            is_productive=False,
            completed_at=now,
        ))
        await db.commit()

        res = await client.get("/api/v1/analytics?period=30d")
        assert res.status_code == 200
        overview = res.json()["overview"]
        assert overview["total_minutes"] == 90
        assert overview["productive_minutes"] == 60
        assert overview["unproductive_minutes"] == 30

    async def test_is_productive_defaults_to_true(
        self, client: AsyncClient
    ):
        res = await client.post("/api/v1/activities", json={
            "title": "Default task",
            "status": "backlog",
        })
        assert res.status_code == 201
        assert res.json()["is_productive"] is True

    async def test_create_unproductive_activity(
        self, client: AsyncClient
    ):
        res = await client.post("/api/v1/activities", json={
            "title": "Wasted time",
            "status": "backlog",
            "is_productive": False,
        })
        assert res.status_code == 201
        assert res.json()["is_productive"] is False

    async def test_update_is_productive(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        activity = Activity(user_id=test_user.id, title="Task", status=Status.backlog)
        db.add(activity)
        await db.commit()
        await db.refresh(activity)

        res = await client.patch(f"/api/v1/activities/{activity.id}", json={
            "is_productive": False,
        })
        assert res.status_code == 200
        assert res.json()["is_productive"] is False

    async def test_heatmap_buckets_by_local_day(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        # 23:00 UTC + offset +120 min → 01:00 next local day → should land on May 24
        db.add(Activity(
            user_id=test_user.id,
            title="late night",
            status=Status.done,
            time_spent_minutes=10,
            completed_at=datetime(2026, 5, 23, 23, 0, 0),
        ))
        await db.commit()

        res = await client.get("/api/v1/analytics?period=all&tz_offset=120")
        assert res.status_code == 200
        heatmap = res.json()["heatmap"]
        assert heatmap.get("2026-05-24") == 1
        assert "2026-05-23" not in heatmap

    async def test_heatmap_utc_when_offset_zero(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        # 23:00 UTC with no offset → stays on May 23
        db.add(Activity(
            user_id=test_user.id,
            title="late night",
            status=Status.done,
            time_spent_minutes=10,
            completed_at=datetime(2026, 5, 23, 23, 0, 0),
        ))
        await db.commit()

        res = await client.get("/api/v1/analytics?period=all&tz_offset=0")
        assert res.json()["heatmap"].get("2026-05-23") == 1

    async def test_completion_rate_never_exceeds_100(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        now = datetime.now(timezone.utc).replace(tzinfo=None)
        long_ago = now - timedelta(days=200)
        for i in range(10):
            db.add(Activity(
                user_id=test_user.id,
                title=f"old-{i}",
                status=Status.done,
                completed_at=now,
                created_at=long_ago,
            ))
        db.add(Activity(
            user_id=test_user.id, title="new", status=Status.backlog, created_at=now
        ))
        await db.commit()

        res = await client.get("/api/v1/analytics?period=7d")

        assert res.json()["overview"]["completion_rate"] <= 100

    async def test_completion_rate_ignores_tasks_created_before_period(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        """A task created long ago says nothing about this period's follow-through."""
        now = datetime.now(timezone.utc).replace(tzinfo=None)
        db.add(Activity(
            user_id=test_user.id,
            title="started long ago",
            status=Status.done,
            completed_at=now,
            created_at=now - timedelta(days=200),
        ))
        db.add(Activity(
            user_id=test_user.id,
            title="started this week",
            status=Status.backlog,
            created_at=now,
        ))
        await db.commit()

        res = await client.get("/api/v1/analytics?period=7d")

        assert res.json()["overview"]["completion_rate"] == 0

    async def test_completion_rate_counts_tasks_created_and_done_in_period(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        now = datetime.now(timezone.utc).replace(tzinfo=None)
        db.add(Activity(
            user_id=test_user.id,
            title="done",
            status=Status.done,
            completed_at=now,
            created_at=now,
        ))
        db.add(Activity(
            user_id=test_user.id, title="open", status=Status.backlog, created_at=now
        ))
        await db.commit()

        res = await client.get("/api/v1/analytics?period=7d")

        assert res.json()["overview"]["completion_rate"] == 50
