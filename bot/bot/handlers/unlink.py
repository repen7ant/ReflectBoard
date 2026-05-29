from aiogram import Router
from aiogram.filters import Command
from aiogram.types import Message
from sqlalchemy.ext.asyncio import AsyncSession

from bot.db.models import User
from bot.repositories.user import UserRepository

router = Router()


@router.message(Command("unlink"))
async def handle_unlink(message: Message, session: AsyncSession, db_user: User | None) -> None:
    if db_user is None:
        await message.answer("Your Telegram account is not linked to any ReflectBoard account.")
        return

    repo = UserRepository(session)
    await repo.clear_telegram_id(message.from_user.id)
    await message.answer("✅ Your Telegram account has been unlinked from ReflectBoard.")
