from aiogram import Bot
from apscheduler.schedulers.asyncio import AsyncIOScheduler
from redis.asyncio import Redis
from sqlalchemy.ext.asyncio import AsyncSession, async_sessionmaker

from bot.config import Settings
from bot.scheduler.deadlines import check_deadlines


def create_scheduler(
    bot: Bot,
    session_factory: async_sessionmaker[AsyncSession],
    redis: Redis,
    settings: Settings,
) -> AsyncIOScheduler:
    scheduler = AsyncIOScheduler(timezone="UTC")
    kwargs = {
        "bot": bot,
        "session_factory": session_factory,
        "redis": redis,
        "settings": settings,
    }
    scheduler.add_job(check_deadlines, "cron", minute="0,30", kwargs=kwargs)
    return scheduler
