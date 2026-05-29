from aiogram import Bot
from apscheduler.schedulers.asyncio import AsyncIOScheduler
from redis.asyncio import Redis
from sqlalchemy.ext.asyncio import AsyncSession, async_sessionmaker

from bot.config import Settings
from bot.scheduler.deadlines import check_deadlines
from bot.scheduler.reminders import check_time_log_reminders, check_today_reminders


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
    scheduler.add_job(check_time_log_reminders, "cron", minute="*", kwargs=kwargs)
    scheduler.add_job(check_today_reminders, "cron", minute="*", kwargs=kwargs)
    return scheduler
