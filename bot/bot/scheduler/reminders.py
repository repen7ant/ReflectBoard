from collections.abc import AsyncIterator
from datetime import datetime, timedelta, timezone

import structlog
from aiogram import Bot
from redis.asyncio import Redis
from sqlalchemy.ext.asyncio import AsyncSession, async_sessionmaker

from bot.config import Settings
from bot.db.models import User
from bot.fastapi_client import get_client
from bot.scheduler._common import fetch_users_with_settings

logger = structlog.get_logger()


async def _due_users(
    session_factory: async_sessionmaker[AsyncSession],
    redis: Redis,
    *,
    time_field: str,
    dedup_prefix: str,
) -> AsyncIterator[tuple[User, str]]:
    """Yield (user, dedup_key) for users whose `time_field` setting matches the current
    local minute and who haven't been notified yet today."""
    now_utc = datetime.now(timezone.utc)
    for user, user_settings in await fetch_users_with_settings(session_factory):
        if not user_settings:
            continue
        target = getattr(user_settings, time_field)
        if not target:
            continue

        local_now = now_utc + timedelta(minutes=user_settings.tz_offset_minutes)
        if local_now.strftime("%H:%M") != target:
            continue

        local_date = local_now.date().isoformat()
        dedup_key = f"notified:{dedup_prefix}:{user.id}:{local_date}"
        if await redis.get(dedup_key):
            continue

        yield user, dedup_key


async def check_time_log_reminders(
    bot: Bot,
    session_factory: async_sessionmaker[AsyncSession],
    redis: Redis,
    settings: Settings,
) -> None:
    async for user, dedup_key in _due_users(
        session_factory, redis, time_field="reminder_time", dedup_prefix="reminder"
    ):
        try:
            await bot.send_message(
                user.telegram_id,
                f"Don't forget to log your activities today.\n{settings.web.board_url}",
            )
            await redis.setex(dedup_key, 25 * 3600, "1")
        except Exception:
            await logger.awarning("Failed to send time-log reminder", user_id=user.id)


async def check_today_reminders(
    bot: Bot,
    session_factory: async_sessionmaker[AsyncSession],
    redis: Redis,
    settings: Settings,
) -> None:
    async for user, dedup_key in _due_users(
        session_factory, redis, time_field="today_reminder_time", dedup_prefix="today"
    ):
        if not user.api_token:
            continue

        try:
            async with get_client(user.api_token, settings.fastapi.base_url) as client:
                today_resp = await client.get("/api/v1/activities", params={"status": "today"})
                today_resp.raise_for_status()
                in_process_resp = await client.get("/api/v1/activities", params={"status": "in_process"})
                in_process_resp.raise_for_status()
                count = len(today_resp.json()) + len(in_process_resp.json())
        except Exception:
            await logger.awarning("Failed to fetch active tasks", user_id=user.id)
            continue

        try:
            await bot.send_message(
                user.telegram_id,
                f"📋 You have {count} active task(s).\n{settings.web.board_url}",
            )
            await redis.setex(dedup_key, 25 * 3600, "1")
        except Exception:
            await logger.awarning("Failed to send today reminder", user_id=user.id)
