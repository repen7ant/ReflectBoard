import html
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

_STATUSES = ("backlog", "today", "in_process")


async def check_deadlines(
    bot: Bot,
    session_factory: async_sessionmaker[AsyncSession],
    redis: Redis,
    settings: Settings,
) -> None:
    async with session_factory() as session:
        result = await session.execute(
            select(User, UserBotSettings)
            .outerjoin(UserBotSettings, User.id == UserBotSettings.user_id)
            .where(User.telegram_id.isnot(None))
        )
        rows = result.all()

    for user, user_settings in rows:
        if not user.api_token:
            continue
        raw_leads = user_settings.deadline_lead_hours if user_settings else "24"
        lead_hours_list = [int(h.strip()) for h in raw_leads.split(",") if h.strip().isdigit()]
        if not lead_hours_list:
            lead_hours_list = [24]

        activities: list[dict] = []
        try:
            async with get_client(user.api_token, settings.fastapi.base_url) as client:
                for status in _STATUSES:
                    resp = await client.get("/api/v1/activities", params={"status": status})
                    resp.raise_for_status()
                    activities.extend(resp.json())
        except Exception:
            await logger.awarning("Failed to fetch activities for user", user_id=user.id)
            continue

        tz_offset = timedelta(minutes=user_settings.tz_offset_minutes if user_settings else 0)
        now_local = (datetime.now(timezone.utc) + tz_offset).replace(tzinfo=None)

        for activity in activities:
            deadline_str = activity.get("deadline")
            if not deadline_str:
                continue
            try:
                deadline_local = datetime.fromisoformat(deadline_str[:19])
            except ValueError:
                continue

            for lead_hours in lead_hours_list:
                cutoff_local = now_local + timedelta(hours=lead_hours)
                if not (now_local < deadline_local <= cutoff_local):
                    continue

                dedup_key = f"notified:deadline:{user.id}:{activity['id']}:{lead_hours}"
                if await redis.get(dedup_key):
                    continue

                formatted = deadline_local.strftime("%b %d at %H:%M")

                try:
                    await bot.send_message(
                        user.telegram_id,
                        f'⏰ <b>Deadline reminder</b>\n"{html.escape(activity["title"])}"\nDue: {formatted}',
                        parse_mode="HTML",
                    )
                    await redis.setex(dedup_key, (lead_hours + 1) * 3600, "1")
                except Exception:
                    await logger.awarning("Failed to send deadline notification", user_id=user.id)
