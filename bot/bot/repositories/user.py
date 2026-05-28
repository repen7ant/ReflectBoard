from sqlalchemy import select, update
from sqlalchemy.ext.asyncio import AsyncSession

from bot.db.models import User


class UserRepository:
    def __init__(self, session: AsyncSession) -> None:
        self.session = session

    async def get_by_telegram_id(self, telegram_id: int) -> User | None:
        result = await self.session.execute(
            select(User).where(User.telegram_id == telegram_id)
        )
        return result.scalar_one_or_none()

    async def set_telegram_id(self, user_id: str, telegram_id: int) -> None:
        await self.session.execute(
            update(User).where(User.id == user_id).values(telegram_id=telegram_id)
        )
        await self.session.commit()

    async def clear_telegram_id(self, telegram_id: int) -> None:
        await self.session.execute(
            update(User).where(User.telegram_id == telegram_id).values(telegram_id=None)
        )
        await self.session.commit()
