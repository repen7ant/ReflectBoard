from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from bot.db.models import UserBotSettings


class BotSettingsRepository:
    def __init__(self, session: AsyncSession) -> None:
        self.session = session

    async def get(self, user_id: int) -> UserBotSettings | None:
        result = await self.session.execute(
            select(UserBotSettings).where(UserBotSettings.user_id == user_id)
        )
        return result.scalar_one_or_none()

    async def upsert(self, user_id: int, **kwargs: object) -> UserBotSettings:
        settings = await self.get(user_id)
        if settings is None:
            settings = UserBotSettings(user_id=user_id, **kwargs)
            self.session.add(settings)
        else:
            for key, value in kwargs.items():
                setattr(settings, key, value)
        await self.session.commit()
        await self.session.refresh(settings)
        return settings
