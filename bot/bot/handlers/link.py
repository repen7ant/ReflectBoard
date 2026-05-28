from aiogram import Router
from aiogram.filters import Command
from aiogram.types import Message
from redis.asyncio import Redis
from sqlalchemy.ext.asyncio import AsyncSession

from bot.repositories.user import UserRepository

router = Router()


@router.message(Command("link"))
async def handle_link(message: Message, redis: Redis, session: AsyncSession) -> None:
    parts = (message.text or "").split(maxsplit=1)
    if len(parts) < 2 or not parts[1].strip():
        await message.answer("Usage: /link <token>")
        return

    token = parts[1].strip()
    user_id = await redis.get(f"tg_link:{token}")

    if not user_id:
        await message.answer("Token is invalid or expired. Generate a new one on the website.")
        return

    await redis.delete(f"tg_link:{token}")

    repo = UserRepository(session)
    await repo.set_telegram_id(user_id.decode(), message.from_user.id)

    await message.answer("Telegram account successfully linked to your ReflectBoard account!")
