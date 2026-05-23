import pytest
from datetime import date, datetime, timedelta
from app.models.activity import Activity
from app.models.category import Category
from app.models.user import User
from httpx import AsyncClient
from sqlalchemy.ext.asyncio import AsyncSession

pytestmark = pytest.mark.asyncio


class TestGetDoneActivities:
    async def test_returns_only_done_activities(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        db.add(Activity(user_id=test_user.id, title="Backlog", status="backlog"))
        db.add(
            Activity(
                user_id=test_user.id,
                title="Done 1",
                status="done",
                completed_at=datetime.now(),
            )
        )
        db.add(
            Activity(
                user_id=test_user.id,
                title="Done 2",
                status="done",
                completed_at=datetime.now(),
            )
        )
        await db.commit()

        res = await client.get("/api/v1/activities/done")
        assert res.status_code == 200
        data = res.json()
        assert len(data) == 2
        assert all(a["status"] == "done" for a in data)

    async def test_does_not_return_other_users_done_activities(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        other_user = User(email="other@example.com", password="hashed")
        db.add(other_user)
        await db.commit()

        db.add(
            Activity(
                user_id=other_user.id,
                title="Other Done",
                status="done",
                completed_at=datetime.now(),
            )
        )
        db.add(
            Activity(
                user_id=test_user.id,
                title="My Done",
                status="done",
                completed_at=datetime.now(),
            )
        )
        await db.commit()

        res = await client.get("/api/v1/activities/done")
        assert res.status_code == 200
        data = res.json()
        assert len(data) == 1
        assert data[0]["title"] == "My Done"

    async def test_search_by_title(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        db.add(
            Activity(
                user_id=test_user.id,
                title="Fix bug in login",
                status="done",
                completed_at=datetime.now(),
            )
        )
        db.add(
            Activity(
                user_id=test_user.id,
                title="Add new feature",
                status="done",
                completed_at=datetime.now(),
            )
        )
        await db.commit()

        res = await client.get("/api/v1/activities/done?search=bug")
        assert res.status_code == 200
        data = res.json()
        assert len(data) == 1
        assert data[0]["title"] == "Fix bug in login"

    async def test_search_by_reflection_text(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        db.add(
            Activity(
                user_id=test_user.id,
                title="Task 1",
                reflection_text="This was challenging",
                status="done",
                completed_at=datetime.now(),
            )
        )
        db.add(
            Activity(
                user_id=test_user.id,
                title="Task 2",
                reflection_text="Easy work",
                status="done",
                completed_at=datetime.now(),
            )
        )
        await db.commit()

        res = await client.get("/api/v1/activities/done?search=challenging")
        assert res.status_code == 200
        data = res.json()
        assert len(data) == 1
        assert data[0]["title"] == "Task 1"

    async def test_search_by_tag(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        db.add(
            Activity(
                user_id=test_user.id,
                title="Frontend work",
                tags=["frontend", "react"],
                status="done",
                completed_at=datetime.now(),
            )
        )
        db.add(
            Activity(
                user_id=test_user.id,
                title="Backend work",
                tags=["backend", "python"],
                status="done",
                completed_at=datetime.now(),
            )
        )
        await db.commit()

        res = await client.get("/api/v1/activities/done?search=%23frontend")
        assert res.status_code == 200
        data = res.json()
        assert len(data) == 1
        assert data[0]["title"] == "Frontend work"

    async def test_search_by_tag_case_insensitive(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        db.add(
            Activity(
                user_id=test_user.id,
                title="Task",
                tags=["Python", "FastAPI"],
                status="done",
                completed_at=datetime.now(),
            )
        )
        await db.commit()

        res = await client.get("/api/v1/activities/done?search=%23python")
        assert res.status_code == 200
        data = res.json()
        assert len(data) == 1

    async def test_filter_by_date_from(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        today = date.today()
        yesterday = today - timedelta(days=1)
        week_ago = today - timedelta(days=7)

        db.add(
            Activity(
                user_id=test_user.id,
                title="Old task",
                status="done",
                completed_at=datetime.combine(week_ago, datetime.min.time()),
            )
        )
        db.add(
            Activity(
                user_id=test_user.id,
                title="Recent task",
                status="done",
                completed_at=datetime.combine(yesterday, datetime.min.time()),
            )
        )
        await db.commit()

        res = await client.get(
            f"/api/v1/activities/done?date_from={yesterday.isoformat()}"
        )
        assert res.status_code == 200
        data = res.json()
        assert len(data) == 1
        assert data[0]["title"] == "Recent task"

    async def test_filter_by_date_to(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        today = date.today()
        yesterday = today - timedelta(days=1)
        week_ago = today - timedelta(days=7)

        db.add(
            Activity(
                user_id=test_user.id,
                title="Old task",
                status="done",
                completed_at=datetime.combine(week_ago, datetime.min.time()),
            )
        )
        db.add(
            Activity(
                user_id=test_user.id,
                title="Recent task",
                status="done",
                completed_at=datetime.combine(today, datetime.min.time()),
            )
        )
        await db.commit()

        res = await client.get(
            f"/api/v1/activities/done?date_to={yesterday.isoformat()}"
        )
        assert res.status_code == 200
        data = res.json()
        assert len(data) == 1
        assert data[0]["title"] == "Old task"

    async def test_filter_by_date_range(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        today = date.today()
        three_days_ago = today - timedelta(days=3)
        week_ago = today - timedelta(days=7)

        db.add(
            Activity(
                user_id=test_user.id,
                title="Too old",
                status="done",
                completed_at=datetime.combine(week_ago, datetime.min.time()),
            )
        )
        db.add(
            Activity(
                user_id=test_user.id,
                title="In range",
                status="done",
                completed_at=datetime.combine(three_days_ago, datetime.min.time()),
            )
        )
        db.add(
            Activity(
                user_id=test_user.id,
                title="Today",
                status="done",
                completed_at=datetime.combine(today, datetime.min.time()),
            )
        )
        await db.commit()

        res = await client.get(
            f"/api/v1/activities/done?date_from={three_days_ago.isoformat()}&date_to={today.isoformat()}"
        )
        assert res.status_code == 200
        data = res.json()
        assert len(data) == 2
        titles = [a["title"] for a in data]
        assert "In range" in titles
        assert "Today" in titles
        assert "Too old" not in titles

    async def test_filter_by_category(
        self, client: AsyncClient, db: AsyncSession, test_user: User, test_category: Category
    ):
        other_category = Category(user_id=test_user.id, name="Other", color="#ff0000")
        db.add(other_category)
        await db.commit()

        db.add(
            Activity(
                user_id=test_user.id,
                title="With test category",
                status="done",
                completed_at=datetime.now(),
                category_id=test_category.id,
            )
        )
        db.add(
            Activity(
                user_id=test_user.id,
                title="With other category",
                status="done",
                completed_at=datetime.now(),
                category_id=other_category.id,
            )
        )
        await db.commit()

        res = await client.get(f"/api/v1/activities/done?category_id={test_category.id}")
        assert res.status_code == 200
        data = res.json()
        assert len(data) == 1
        assert data[0]["title"] == "With test category"

    async def test_combined_filters(
        self, client: AsyncClient, db: AsyncSession, test_user: User, test_category: Category
    ):
        today = date.today()
        yesterday = today - timedelta(days=1)

        db.add(
            Activity(
                user_id=test_user.id,
                title="Match all filters",
                tags=["urgent"],
                status="done",
                completed_at=datetime.combine(today, datetime.min.time()),
                category_id=test_category.id,
            )
        )
        db.add(
            Activity(
                user_id=test_user.id,
                title="Wrong date",
                tags=["urgent"],
                status="done",
                completed_at=datetime.combine(yesterday, datetime.min.time()),
                category_id=test_category.id,
            )
        )
        db.add(
            Activity(
                user_id=test_user.id,
                title="Wrong tag",
                tags=["normal"],
                status="done",
                completed_at=datetime.combine(today, datetime.min.time()),
                category_id=test_category.id,
            )
        )
        await db.commit()

        res = await client.get(
            f"/api/v1/activities/done?search=%23urgent&date_from={today.isoformat()}&category_id={test_category.id}"
        )
        assert res.status_code == 200
        data = res.json()
        assert len(data) == 1
        assert data[0]["title"] == "Match all filters"

    async def test_ordered_by_completed_at_desc(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        now = datetime.now()
        db.add(
            Activity(
                user_id=test_user.id,
                title="First",
                status="done",
                completed_at=now - timedelta(hours=2),
            )
        )
        db.add(
            Activity(
                user_id=test_user.id,
                title="Second",
                status="done",
                completed_at=now - timedelta(hours=1),
            )
        )
        db.add(
            Activity(
                user_id=test_user.id,
                title="Third",
                status="done",
                completed_at=now,
            )
        )
        await db.commit()

        res = await client.get("/api/v1/activities/done")
        assert res.status_code == 200
        data = res.json()
        assert len(data) == 3
        assert data[0]["title"] == "Third"
        assert data[1]["title"] == "Second"
        assert data[2]["title"] == "First"

    async def test_done_filter_respects_tz_offset_include(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        # 23:00 UTC May 23 == 01:00 May 24 at offset +120 → should appear in May 24 filter
        db.add(Activity(
            user_id=test_user.id,
            title="late",
            status="done",
            completed_at=datetime(2026, 5, 23, 23, 0, 0),
        ))
        await db.commit()

        res = await client.get(
            "/api/v1/activities/done?date_from=2026-05-24&date_to=2026-05-24&tz_offset=120"
        )
        assert res.status_code == 200
        assert any(x["title"] == "late" for x in res.json())

    async def test_done_filter_respects_tz_offset_exclude(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        # Same activity should NOT appear in the May 23 local-day filter at offset +120
        db.add(Activity(
            user_id=test_user.id,
            title="late",
            status="done",
            completed_at=datetime(2026, 5, 23, 23, 0, 0),
        ))
        await db.commit()

        res = await client.get(
            "/api/v1/activities/done?date_from=2026-05-23&date_to=2026-05-23&tz_offset=120"
        )
        assert res.status_code == 200
        assert not any(x["title"] == "late" for x in res.json())


class TestCategorySnapshot:
    async def test_snapshot_fields_in_response(
        self, client: AsyncClient, db: AsyncSession, test_user: User, test_category: Category
    ):
        activity = Activity(
            user_id=test_user.id,
            title="Task",
            status="done",
            completed_at=datetime.now(),
            category_id=test_category.id,
            category_snapshot_name="Snapshot Name",
            category_snapshot_color="#123456",
        )
        db.add(activity)
        await db.commit()

        res = await client.get("/api/v1/activities/done")
        assert res.status_code == 200
        data = res.json()
        assert len(data) == 1
        assert data[0]["category_snapshot_name"] == "Snapshot Name"
        assert data[0]["category_snapshot_color"] == "#123456"

    async def test_completing_activity_saves_category_snapshot(
        self, client: AsyncClient, db: AsyncSession, test_user: User, test_category: Category
    ):
        activity = Activity(
            user_id=test_user.id,
            title="Task",
            status="in_process",
            category_id=test_category.id,
        )
        db.add(activity)
        await db.commit()

        res = await client.patch(
            f"/api/v1/activities/{activity.id}",
            json={"status": "done"},
        )
        assert res.status_code == 200
        data = res.json()
        assert data["category_snapshot_name"] == test_category.name
        assert data["category_snapshot_color"] == test_category.color
