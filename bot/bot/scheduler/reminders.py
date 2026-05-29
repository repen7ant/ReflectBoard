from datetime import datetime, timedelta, timezone

import structlog
from aiogram import Bot
from redis.asyncio import Redis
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession, async_sessionmaker

from bot.config import Settings
from bot.db.models import User, UserBotSettings
from bot.fastapi_client import get_client

logger = structlog.get_logger()


async def check_time_log_reminders(
    bot: Bot,
    session_factory: async_sessionmaker[AsyncSession],
    redis: Redis,
    settings: Settings,
) -> None:
    now_utc = datetime.now(timezone.utc)

    async with session_factory() as session:
        result = await session.execute(
            select(User, UserBotSettings)
            .outerjoin(UserBotSettings, User.id == UserBotSettings.user_id)
            .where(User.telegram_id.isnot(None))
        )
        rows = result.all()

    for user, user_settings in rows:
        if not user_settings or not user_settings.reminder_time:
            continue

        tz_offset = timedelta(minutes=user_settings.tz_offset_minutes)
        local_now = now_utc + tz_offset
        if local_now.strftime("%H:%M") != user_settings.reminder_time:
            continue

        local_date = local_now.date().isoformat()
        dedup_key = f"notified:reminder:{user.id}:{local_date}"
        if await redis.get(dedup_key):
            continue

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
    now_utc = datetime.now(timezone.utc)

    async with session_factory() as session:
        result = await session.execute(
            select(User, UserBotSettings)
            .outerjoin(UserBotSettings, User.id == UserBotSettings.user_id)
            .where(User.telegram_id.isnot(None))
        )
        rows = result.all()

    for user, user_settings in rows:
        if not user_settings or not user_settings.today_reminder_time:
            continue

        tz_offset = timedelta(minutes=user_settings.tz_offset_minutes)
        local_now = now_utc + tz_offset
        if local_now.strftime("%H:%M") != user_settings.today_reminder_time:
            continue

        local_date = local_now.date().isoformat()
        dedup_key = f"notified:today:{user.id}:{local_date}"
        if await redis.get(dedup_key):
            continue

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
