import asyncio

import redis.asyncio as aioredis
import structlog
from aiogram import Bot, Dispatcher
from structlog.typing import FilteringBoundLogger

from bot.config import Settings
from bot.db.session import AsyncSessionLocal
from bot.handlers import get_routers
from bot.logging_config import get_structlog_config
from bot.middlewares.db import DbSessionMiddleware
from bot.middlewares.user import UserMiddleware

logger: FilteringBoundLogger = structlog.get_logger()


async def main() -> None:
    settings = Settings()
    structlog.configure(**get_structlog_config(settings.logs))

    redis_client = aioredis.from_url(settings.redis.url)

    bot = Bot(token=settings.bot.token.get_secret_value())
    dp = Dispatcher()
    dp.update.middleware(DbSessionMiddleware(AsyncSessionLocal))
    dp.update.middleware(UserMiddleware())
    dp.include_routers(*get_routers())

    await logger.ainfo("Starting polling...")
    try:
        await dp.start_polling(bot, redis=redis_client)
    finally:
        await redis_client.aclose()
        await logger.ainfo("Bot stopped")


asyncio.run(main())
