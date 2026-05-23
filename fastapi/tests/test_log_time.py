from datetime import datetime, timezone
from unittest.mock import AsyncMock, patch

import pytest
from app.models.activity import Activity, Status
from app.models.user import User
from httpx import AsyncClient
from sqlalchemy.ext.asyncio import AsyncSession

pytestmark = pytest.mark.asyncio


class TestLogTime:
    async def test_adds_minutes_to_activity(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ) -> None:
        a = Activity(user_id=test_user.id, title="Task", status=Status.backlog)
        db.add(a)
        await db.commit()

        res = await client.post(
            f"/api/v1/activities/{a.id}/log-time", json={"minutes": 45}
        )
        assert res.status_code == 200
        data = res.json()
        assert data["time_spent_minutes"] == 45
        assert data["time_logged_minutes"] == 45

    async def test_accumulates_multiple_logs(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ) -> None:
        a = Activity(user_id=test_user.id, title="Task", status=Status.today)
        db.add(a)
        await db.commit()

        res1 = await client.post(f"/api/v1/activities/{a.id}/log-time", json={"minutes": 30})
        assert res1.status_code == 200
        res = await client.post(
            f"/api/v1/activities/{a.id}/log-time", json={"minutes": 45}
        )
        assert res.status_code == 200
        data = res.json()
        assert data["time_spent_minutes"] == 75
        assert data["time_logged_minutes"] == 75

    async def test_returns_404_for_other_user(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ) -> None:
        other = User(email="other@example.com", password="hashed")
        db.add(other)
        await db.commit()
        a = Activity(user_id=other.id, title="Other", status=Status.backlog)
        db.add(a)
        await db.commit()

        res = await client.post(
            f"/api/v1/activities/{a.id}/log-time", json={"minutes": 30}
        )
        assert res.status_code == 404

    async def test_returns_400_for_done_activity(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ) -> None:
        a = Activity(
            user_id=test_user.id,
            title="Done",
            status=Status.done,
            completed_at=datetime.now(timezone.utc),
        )
        db.add(a)
        await db.commit()

        res = await client.post(
            f"/api/v1/activities/{a.id}/log-time", json={"minutes": 30}
        )
        assert res.status_code == 400

    async def test_rejects_zero_minutes(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ) -> None:
        a = Activity(user_id=test_user.id, title="Task", status=Status.backlog)
        db.add(a)
        await db.commit()

        res = await client.post(
            f"/api/v1/activities/{a.id}/log-time", json={"minutes": 0}
        )
        assert res.status_code == 422

    async def test_completion_uses_delta_not_full_amount(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ) -> None:
        a = Activity(user_id=test_user.id, title="Task", status=Status.backlog)
        db.add(a)
        await db.commit()

        await client.post(f"/api/v1/activities/{a.id}/log-time", json={"minutes": 60})

        with patch(
            "app.api.v1.activity.record_completion", new_callable=AsyncMock
        ) as mock_rc:
            res = await client.patch(
                f"/api/v1/activities/{a.id}",
                json={"status": "done", "time_spent_minutes": 90},
            )
            assert res.status_code == 200
            mock_rc.assert_called_once()
            assert mock_rc.call_args.kwargs["time_spent_minutes"] == 30  # delta: 90 - 60

    async def test_completion_skips_redis_when_no_delta(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ) -> None:
        a = Activity(user_id=test_user.id, title="Task", status=Status.backlog)
        db.add(a)
        await db.commit()

        await client.post(f"/api/v1/activities/{a.id}/log-time", json={"minutes": 60})

        with patch(
            "app.api.v1.activity.record_completion", new_callable=AsyncMock
        ) as mock_rc:
            res = await client.patch(
                f"/api/v1/activities/{a.id}",
                json={"status": "done", "time_spent_minutes": 60},
            )
            assert res.status_code == 200
            mock_rc.assert_not_called()

    async def test_rejects_negative_minutes(
        self, client: AsyncClient, db: AsyncSession, test_user: User
    ) -> None:
        a = Activity(user_id=test_user.id, title="Task", status=Status.backlog)
        db.add(a)
        await db.commit()

        res = await client.post(
            f"/api/v1/activities/{a.id}/log-time", json={"minutes": -1}
        )
        assert res.status_code == 422

    async def test_returns_404_for_nonexistent_activity(
        self, client: AsyncClient
    ) -> None:
        res = await client.post(
            "/api/v1/activities/99999/log-time", json={"minutes": 30}
        )
        assert res.status_code == 404
