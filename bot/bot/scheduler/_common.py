from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession, async_sessionmaker

from bot.db.models import User, UserBotSettings


async def fetch_users_with_settings(
    session_factory: async_sessionmaker[AsyncSession],
) -> list[tuple[User, UserBotSettings | None]]:
    """All Telegram-linked users joined with their bot settings (settings may be None)."""
    async with session_factory() as session:
        result = await session.execute(
            select(User, UserBotSettings)
            .outerjoin(UserBotSettings, User.id == UserBotSettings.user_id)
            .where(User.telegram_id.isnot(None))
        )
        return list(result.all())
