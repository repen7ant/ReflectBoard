from aiogram import Router
from aiogram.filters import CommandStart
from aiogram.filters.command import CommandObject
from aiogram.types import Message
from redis.asyncio import Redis
from sqlalchemy.ext.asyncio import AsyncSession

from bot.db.models import User
from bot.repositories.user import UserRepository

router = Router()


@router.message(CommandStart())
async def handle_start(
    message: Message,
    command: CommandObject,
    redis: Redis,
    session: AsyncSession,
    db_user: User | None,
) -> None:
    if db_user is not None:
        await message.answer("Your account is already linked to ReflectBoard.")
        return

    if not command.args:
        await message.answer("Hello! Connect your account via the ReflectBoard website.")
        return

    token = command.args
    user_id = await redis.get(f"tg_link:{token}")

    if not user_id:
        await message.answer("Token is invalid or expired. Please generate a new one on the website.")
        return

    await redis.delete(f"tg_link:{token}")

    repo = UserRepository(session)
    await repo.set_telegram_id(user_id.decode(), message.from_user.id)

    await message.answer("✅ Your Telegram account has been linked to ReflectBoard!")
