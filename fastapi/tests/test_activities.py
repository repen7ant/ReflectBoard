import pytest
from app.models.category import Category
from app.models.user import User
from httpx import AsyncClient
from sqlalchemy.ext.asyncio import AsyncSession

pytestmark = pytest.mark.asyncio


class TestGetActivities:
    async def test_returns_empty_list(self, client: AsyncClient):
        res = await client.get("/api/v1/activities")
        assert res.status_code == 200
        assert res.json() == []

    async def test_returns_only_own_activities(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        from app.models.activity import Activity

        activity = Activity(user_id=test_user.id, title="My Task", status="backlog")
        db.add(activity)
        await db.commit()

        res = await client.get("/api/v1/activities")
        assert res.status_code == 200
        assert len(res.json()) == 1
        assert res.json()[0]["title"] == "My Task"

    async def test_filter_by_status(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        from app.models.activity import Activity

        db.add(Activity(user_id=test_user.id, title="Backlog Task", status="backlog"))
        db.add(Activity(user_id=test_user.id, title="Today Task", status="today"))
        await db.commit()

        res = await client.get("/api/v1/activities?status=today")
        assert res.status_code == 200
        data = res.json()
        assert all(a["status"] == "today" for a in data)


class TestCreateActivity:
    async def test_creates_activity(self, client: AsyncClient):
        res = await client.post(
            "/api/v1/activities",
            json={
                "title": "New Task",
                "status": "backlog",
            },
        )
        assert res.status_code == 201
        data = res.json()
        assert data["title"] == "New Task"
        assert data["status"] == "backlog"
        assert "id" in data

    async def test_creates_with_category(
        self, client: AsyncClient, test_category: Category
    ):
        res = await client.post(
            "/api/v1/activities",
            json={
                "title": "Task with Category",
                "status": "today",
                "category_id": test_category.id,
            },
        )
        assert res.status_code == 201
        data = res.json()
        assert data["category"] is not None
        assert data["category"]["id"] == test_category.id

    async def test_title_required(self, client: AsyncClient):
        res = await client.post("/api/v1/activities", json={"status": "backlog"})
        assert res.status_code == 422


class TestUpdateActivity:
    async def test_update_status(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        from app.models.activity import Activity

        activity = Activity(user_id=test_user.id, title="Task", status="backlog")
        db.add(activity)
        await db.commit()

        res = await client.patch(
            f"/api/v1/activities/{activity.id}", json={"status": "today"}
        )
        assert res.status_code == 200
        assert res.json()["status"] == "today"

    async def test_cannot_update_other_users_activity(
        self, client: AsyncClient, db: AsyncSession
    ):
        other_user = User(email="other@example.com", password="hashed")
        db.add(other_user)
        await db.commit()

        from app.models.activity import Activity

        activity = Activity(user_id=other_user.id, title="Other Task", status="backlog")
        db.add(activity)
        await db.commit()

        res = await client.patch(
            f"/api/v1/activities/{activity.id}", json={"status": "today"}
        )
        assert res.status_code == 404

    async def test_update_nonexistent_returns_404(self, client: AsyncClient):
        res = await client.patch("/api/v1/activities/99999", json={"status": "today"})
        assert res.status_code == 404


class TestDeleteActivity:
    async def test_deletes_activity(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        from app.models.activity import Activity

        activity = Activity(user_id=test_user.id, title="To Delete", status="backlog")
        db.add(activity)
        await db.commit()
        activity_id = activity.id

        res = await client.delete(f"/api/v1/activities/{activity_id}")
        assert res.status_code == 204

        res = await client.delete(f"/api/v1/activities/{activity_id}")
        assert res.status_code == 404

    async def test_cannot_delete_other_users_activity(
        self, client: AsyncClient, db: AsyncSession
    ):
        other_user = User(email="other2@example.com", password="hashed")
        db.add(other_user)
        await db.commit()

        from app.models.activity import Activity

        activity = Activity(user_id=other_user.id, title="Other", status="backlog")
        db.add(activity)
        await db.commit()

        res = await client.delete(f"/api/v1/activities/{activity.id}")
        assert res.status_code == 404
