"""Ownership checks for cross-user references (category_id / parent_id)."""

import pytest_asyncio
from app.models.activity import Activity
from app.models.category import Category
from app.models.user import User
from app.services.activity_service import ActivityService
from sqlalchemy.ext.asyncio import AsyncSession


@pytest_asyncio.fixture()
async def other_user(db: AsyncSession) -> User:
    user = User(email="other@example.com", password="hashed", api_token="othertoken")
    db.add(user)
    await db.commit()
    await db.refresh(user)
    return user


@pytest_asyncio.fixture()
async def other_category(db: AsyncSession, other_user: User) -> Category:
    category = Category(user_id=other_user.id, name="Other's Secret", color="#ff0000")
    db.add(category)
    await db.commit()
    await db.refresh(category)
    return category


@pytest_asyncio.fixture()
async def other_project(db: AsyncSession, other_user: User) -> Activity:
    project = Activity(
        user_id=other_user.id, title="Other's Secret Project", is_project=True
    )
    db.add(project)
    await db.commit()
    await db.refresh(project)
    return project


async def test_create_rejects_foreign_category(client, other_category: Category):
    response = await client.post(
        "/api/v1/activities",
        json={"title": "mine", "category_id": other_category.id},
    )

    assert response.status_code == 404


async def test_create_rejects_foreign_parent(client, other_project: Activity):
    response = await client.post(
        "/api/v1/activities",
        json={"title": "mine", "parent_id": other_project.id},
    )

    assert response.status_code == 404


async def test_update_rejects_foreign_category(client, other_category: Category):
    created = await client.post("/api/v1/activities", json={"title": "mine"})

    response = await client.patch(
        f"/api/v1/activities/{created.json()['id']}",
        json={"category_id": other_category.id},
    )

    assert response.status_code == 404


async def test_create_accepts_own_category(client, test_category: Category):
    response = await client.post(
        "/api/v1/activities",
        json={"title": "mine", "category_id": test_category.id},
    )

    assert response.status_code == 201
    assert response.json()["category"]["id"] == test_category.id


async def test_create_accepts_own_parent(client):
    project = await client.post(
        "/api/v1/activities", json={"title": "proj", "is_project": True}
    )

    response = await client.post(
        "/api/v1/activities",
        json={"title": "sub", "parent_id": project.json()["id"]},
    )

    assert response.status_code == 201
    assert response.json()["parent_id"] == project.json()["id"]


async def test_subtask_counters_ignore_foreign_rows(
    db: AsyncSession, session_maker, test_user: User, other_user: User
):
    project = Activity(user_id=test_user.id, title="My Project", is_project=True)
    db.add(project)
    await db.commit()
    await db.refresh(project)
    db.add(
        Activity(
            user_id=other_user.id,
            title="injected",
            parent_id=project.id,
            time_spent_minutes=999,
        )
    )
    await db.commit()

    async with session_maker() as fresh:
        activities = await ActivityService.get_activities(fresh, test_user.id)

    board = {a.id: a for a in activities}
    assert board[project.id].subtasks_total == 0
    assert board[project.id].time_spent_minutes != 999


async def test_parent_title_not_leaked_from_foreign_activity(
    db: AsyncSession, session_maker, test_user: User, other_project: Activity
):
    db.add(
        Activity(
            user_id=test_user.id,
            title="mine",
            parent_id=other_project.id,
            is_on_board=True,
        )
    )
    await db.commit()

    async with session_maker() as fresh:
        activities = await ActivityService.get_activities(fresh, test_user.id)

    assert [a.parent_title for a in activities] == [None]


async def test_single_activity_counters_ignore_foreign_rows(
    db: AsyncSession, session_maker, test_user: User, other_user: User
):
    project = Activity(user_id=test_user.id, title="My Project", is_project=True)
    db.add(project)
    await db.commit()
    await db.refresh(project)
    db.add(Activity(user_id=other_user.id, title="injected", parent_id=project.id))
    await db.commit()

    async with session_maker() as fresh:
        fetched = await ActivityService.get_activity_by_id(
            fresh, project.id, test_user.id
        )

    assert fetched is not None
    assert fetched.subtasks_total == 0


async def test_delete_category_leaves_foreign_activities_untouched(
    client, db: AsyncSession, session_maker, test_category: Category, other_user: User
):
    """Legacy rows may still point at another user's category — don't rewrite them."""
    stale = Activity(user_id=other_user.id, title="stale", category_id=test_category.id)
    db.add(stale)
    await db.commit()
    await db.refresh(stale)

    response = await client.delete(f"/api/v1/categories/{test_category.id}")
    assert response.status_code == 204

    async with session_maker() as fresh:
        untouched = await fresh.get(Activity, stale.id)
        assert untouched.category_snapshot_name is None
