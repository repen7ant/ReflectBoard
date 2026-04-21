import pytest
from app.models.user import User
from httpx import AsyncClient
from sqlalchemy.ext.asyncio import AsyncSession

pytestmark = pytest.mark.asyncio


class TestGetCategories:
    async def test_returns_empty_list(self, client: AsyncClient):
        res = await client.get("/api/v1/categories")
        assert res.status_code == 200
        assert res.json() == []

    async def test_does_not_return_other_users_categories(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        other_user = User(email="other4@example.com", password="hashed")
        db.add(other_user)
        await db.commit()

        from app.models.category import Category

        db.add(Category(user_id=other_user.id, name="Secret", color="#000"))
        db.add(Category(user_id=test_user.id, name="Mine", color="#fff"))
        await db.commit()

        res = await client.get("/api/v1/categories")
        assert len(res.json()) == 1
        assert res.json()[0]["name"] == "Mine"


class TestCreateCategory:
    async def test_creates_category(self, client: AsyncClient):
        res = await client.post(
            "/api/v1/categories",
            json={
                "name": "Study",
                "color": "#98bb6c",
            },
        )
        assert res.status_code == 200
        data = res.json()
        assert data["name"] == "Study"
        assert data["color"] == "#98bb6c"
        assert "id" in data

    async def test_name_required(self, client: AsyncClient):
        res = await client.post("/api/v1/categories", json={"color": "#000000"})
        assert res.status_code == 422


class TestDeleteCategory:
    async def test_deletes_category(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        from app.models.category import Category

        category = Category(user_id=test_user.id, name="To Delete", color="#000000")
        db.add(category)
        await db.commit()

        res = await client.delete(f"/api/v1/categories/{category.id}")
        assert res.status_code == 204

        res = await client.delete(f"/api/v1/categories/{category.id}")
        assert res.status_code == 404

    async def test_cannot_delete_other_users_category(
        self, client: AsyncClient, db: AsyncSession
    ):
        other_user = User(email="other3@example.com", password="hashed")
        db.add(other_user)
        await db.commit()

        from app.models.category import Category

        category = Category(user_id=other_user.id, name="Other", color="#000000")
        db.add(category)
        await db.commit()

        res = await client.delete(f"/api/v1/categories/{category.id}")
        assert res.status_code == 404
