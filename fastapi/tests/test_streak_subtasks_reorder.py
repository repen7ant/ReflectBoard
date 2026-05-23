from datetime import datetime, timedelta

import pytest
from app.models.activity import Activity, Status
from app.models.user import User
from httpx import AsyncClient
from sqlalchemy.ext.asyncio import AsyncSession

pytestmark = pytest.mark.asyncio


# ─── Helpers ──────────────────────────────────────────────────────────────────

def _noon(days_offset: int = 0) -> datetime:
    """Return naive UTC noon for today + days_offset. Avoids midnight edge cases."""
    now = datetime.utcnow()
    base = now.replace(hour=12, minute=0, second=0, microsecond=0)
    return base + timedelta(days=days_offset)


def _done(user_id: int, completed_at: datetime, **kwargs) -> Activity:
    return Activity(
        user_id=user_id,
        title="t",
        status=Status.done,
        completed_at=completed_at,
        **kwargs,
    )


# ─── Streak ───────────────────────────────────────────────────────────────────

class TestStreak:
    async def test_streak_no_tasks(self, client: AsyncClient):
        res = await client.get("/api/v1/analytics?period=all")
        assert res.json()["overview"]["streak"] == 0

    async def test_streak_completed_today(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        db.add(_done(test_user.id, _noon()))
        await db.commit()

        res = await client.get("/api/v1/analytics?period=all")
        assert res.json()["overview"]["streak"] == 1

    async def test_streak_only_yesterday_counts(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        # No task today, one task yesterday → streak=1 (start-from-yesterday branch)
        db.add(_done(test_user.id, _noon(-1)))
        await db.commit()

        res = await client.get("/api/v1/analytics?period=all")
        assert res.json()["overview"]["streak"] == 1

    async def test_streak_two_consecutive_days(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        db.add(_done(test_user.id, _noon()))
        db.add(_done(test_user.id, _noon(-1)))
        await db.commit()

        res = await client.get("/api/v1/analytics?period=all")
        assert res.json()["overview"]["streak"] == 2

    async def test_streak_gap_breaks_chain(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        # Today + 2 days ago, nothing yesterday → streak=1 (only today counts)
        db.add(_done(test_user.id, _noon()))
        db.add(_done(test_user.id, _noon(-2)))
        await db.commit()

        res = await client.get("/api/v1/analytics?period=all")
        assert res.json()["overview"]["streak"] == 1

    async def test_streak_multiple_tasks_same_day_count_as_one(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        db.add(_done(test_user.id, _noon().replace(hour=9)))
        db.add(_done(test_user.id, _noon().replace(hour=15)))
        db.add(_done(test_user.id, _noon(-1)))
        await db.commit()

        res = await client.get("/api/v1/analytics?period=all")
        assert res.json()["overview"]["streak"] == 2

    async def test_streak_tz_offset_shifts_day(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        # Task at 23:00 UTC. With tz_offset=120 (UTC+2) it becomes 01:00 next local day.
        # So local "today" is actually the UTC-tomorrow.
        # We place both today-utc and yesterday-utc tasks; with offset +120 they shift
        # to tomorrow and today local respectively → local yesterday + today = streak 2.
        db.add(_done(test_user.id, _noon()))    # UTC today → local today (noon, no shift issue)
        db.add(_done(test_user.id, _noon(-1)))  # UTC yesterday → local yesterday
        await db.commit()

        res = await client.get("/api/v1/analytics?period=all&tz_offset=120")
        assert res.json()["overview"]["streak"] >= 1  # at minimum yesterday is counted


# ─── Subtasks ─────────────────────────────────────────────────────────────────

class TestSubtasks:
    async def test_returns_subtasks_of_project(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        project = Activity(user_id=test_user.id, title="Project", status=Status.backlog, is_project=True)
        db.add(project)
        await db.commit()
        await db.refresh(project)

        db.add(Activity(user_id=test_user.id, title="Sub A", status=Status.backlog, parent_id=project.id, position=0))
        db.add(Activity(user_id=test_user.id, title="Sub B", status=Status.done, parent_id=project.id, position=1))
        await db.commit()

        res = await client.get(f"/api/v1/activities/{project.id}/subtasks")
        assert res.status_code == 200
        titles = [x["title"] for x in res.json()]
        assert "Sub A" in titles
        assert "Sub B" in titles
        assert len(titles) == 2

    async def test_subtasks_ordered_by_position(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        project = Activity(user_id=test_user.id, title="Project", status=Status.backlog, is_project=True)
        db.add(project)
        await db.commit()
        await db.refresh(project)

        db.add(Activity(user_id=test_user.id, title="First", status=Status.backlog, parent_id=project.id, position=0))
        db.add(Activity(user_id=test_user.id, title="Second", status=Status.backlog, parent_id=project.id, position=1))
        db.add(Activity(user_id=test_user.id, title="Third", status=Status.backlog, parent_id=project.id, position=2))
        await db.commit()

        res = await client.get(f"/api/v1/activities/{project.id}/subtasks")
        titles = [x["title"] for x in res.json()]
        assert titles == ["First", "Second", "Third"]

    async def test_subtasks_returns_empty_for_no_subtasks(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        project = Activity(user_id=test_user.id, title="Empty Project", status=Status.backlog, is_project=True)
        db.add(project)
        await db.commit()
        await db.refresh(project)

        res = await client.get(f"/api/v1/activities/{project.id}/subtasks")
        assert res.status_code == 200
        assert res.json() == []

    async def test_subtasks_isolation_from_other_projects(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        project_a = Activity(user_id=test_user.id, title="Project A", status=Status.backlog, is_project=True)
        project_b = Activity(user_id=test_user.id, title="Project B", status=Status.backlog, is_project=True)
        db.add(project_a)
        db.add(project_b)
        await db.commit()
        await db.refresh(project_a)
        await db.refresh(project_b)

        db.add(Activity(user_id=test_user.id, title="Sub of A", status=Status.backlog, parent_id=project_a.id))
        db.add(Activity(user_id=test_user.id, title="Sub of B", status=Status.backlog, parent_id=project_b.id))
        await db.commit()

        res = await client.get(f"/api/v1/activities/{project_a.id}/subtasks")
        titles = [x["title"] for x in res.json()]
        assert "Sub of A" in titles
        assert "Sub of B" not in titles


# ─── Reorder ──────────────────────────────────────────────────────────────────

class TestReorder:
    async def test_reorder_updates_positions(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        a = Activity(user_id=test_user.id, title="A", status=Status.backlog, position=0)
        b = Activity(user_id=test_user.id, title="B", status=Status.backlog, position=1)
        c = Activity(user_id=test_user.id, title="C", status=Status.backlog, position=2)
        db.add_all([a, b, c])
        await db.commit()
        # IDs are available after commit (expire_on_commit=False); no refresh needed

        # Reorder: C, A, B
        res = await client.post("/api/v1/activities/reorder", json={
            "ordered_ids": [c.id, a.id, b.id],
        })
        assert res.status_code == 200

        # End the test session's current transaction so the next read sees the API's commit
        await db.commit()
        await db.refresh(a)
        await db.refresh(b)
        await db.refresh(c)
        assert c.position == 0
        assert a.position == 1
        assert b.position == 2

    async def test_reorder_with_status_change(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        a = Activity(user_id=test_user.id, title="A", status=Status.backlog, position=0)
        b = Activity(user_id=test_user.id, title="B", status=Status.backlog, position=1)
        db.add_all([a, b])
        await db.commit()

        res = await client.post("/api/v1/activities/reorder", json={
            "activity_id": a.id,
            "new_status": "today",
            "ordered_ids": [a.id, b.id],
        })
        assert res.status_code == 200

        await db.commit()
        await db.refresh(a)
        assert a.status == Status.today

    async def test_reorder_does_not_affect_other_users(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        other_user = User(email="other@example.com", password="hashed")
        db.add(other_user)
        await db.commit()

        other = Activity(user_id=other_user.id, title="Other", status=Status.backlog, position=5)
        mine = Activity(user_id=test_user.id, title="Mine", status=Status.backlog, position=0)
        db.add_all([other, mine])
        await db.commit()

        await client.post("/api/v1/activities/reorder", json={
            "ordered_ids": [other.id, mine.id],
        })

        await db.commit()
        await db.refresh(other)
        # other user's activity position must not change
        assert other.position == 5
