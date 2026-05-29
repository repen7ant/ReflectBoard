from sqlalchemy import BigInteger, SmallInteger, String
from sqlalchemy.orm import Mapped, mapped_column

from bot.db.base import Base


class User(Base):
    __tablename__ = "users"

    id: Mapped[int] = mapped_column(BigInteger, primary_key=True)
    telegram_id: Mapped[int | None] = mapped_column(BigInteger, unique=True, nullable=True)
    api_token: Mapped[str | None] = mapped_column(String(80), nullable=True)


class UserBotSettings(Base):
    __tablename__ = "user_bot_settings"

    user_id: Mapped[int] = mapped_column(BigInteger, primary_key=True)
    deadline_lead_hours: Mapped[str] = mapped_column(String(50), default="24")
    reminder_time: Mapped[str | None] = mapped_column(String(5), nullable=True)
    today_reminder_time: Mapped[str | None] = mapped_column(String(5), nullable=True)
    tz_offset_minutes: Mapped[int] = mapped_column(SmallInteger, default=0)
