from datetime import datetime

import pytest
from app.models.activity import Activity, Status
from app.models.user import User
from httpx import AsyncClient
from sqlalchemy.ext.asyncio import AsyncSession

pytestmark = pytest.mark.asyncio


class TestUtcSerialization:
    async def test_completed_at_has_utc_offset(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        db.add(Activity(
            user_id=test_user.id,
            title="done task",
            status=Status.done,
            completed_at=datetime(2026, 5, 23, 14, 30, 0),
        ))
        await db.commit()

        res = await client.get("/api/v1/activities/done")
        assert res.status_code == 200
        item = res.json()[0]
        assert item["completed_at"] == "2026-05-23T14:30:00+00:00"
        assert item["created_at"].endswith("+00:00")
        assert item["updated_at"].endswith("+00:00")

    async def test_deadline_has_no_offset(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ):
        db.add(Activity(
            user_id=test_user.id,
            title="with deadline",
            status=Status.backlog,
            deadline=datetime(2026, 5, 23, 14, 0, 0),
        ))
        await db.commit()

        res = await client.get("/api/v1/activities")
        item = next(x for x in res.json() if x["title"] == "with deadline")
        assert item["deadline"] == "2026-05-23T14:00:00"
