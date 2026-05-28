from typing import Any, Awaitable, Callable

from aiogram import BaseMiddleware
from aiogram.types import TelegramObject
from sqlalchemy.ext.asyncio import AsyncSession

from bot.repositories.user import UserRepository


class UserMiddleware(BaseMiddleware):
    async def __call__(
        self,
        handler: Callable[[TelegramObject, dict[str, Any]], Awaitable[Any]],
        event: TelegramObject,
        data: dict[str, Any],
    ) -> Any:
        session: AsyncSession = data["session"]
        telegram_user = data.get("event_from_user")
        if telegram_user:
            repo = UserRepository(session)
            data["db_user"] = await repo.get_by_telegram_id(telegram_user.id)
        return await handler(event, data)
