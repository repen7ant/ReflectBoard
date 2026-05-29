from aiogram import Router
from aiogram.filters import Command
from aiogram.filters.command import CommandObject
from aiogram.types import Message
from sqlalchemy.ext.asyncio import AsyncSession

from bot.db.models import User
from bot.repositories.bot_settings import BotSettingsRepository

router = Router()


def _parse_tz_offset(s: str) -> int:
    """Parse '+3', '-5:30', '+05:30' into total minutes."""
    s = s.strip()
    sign = -1 if s.startswith("-") else 1
    s = s.lstrip("+-")
    if ":" in s:
        hours, minutes = s.split(":")
        return sign * (int(hours) * 60 + int(minutes))
    return sign * int(s) * 60


def _validate_time(s: str) -> bool:
    parts = s.split(":")
    if len(parts) != 2:
        return False
    try:
        h, m = int(parts[0]), int(parts[1])
        return 0 <= h <= 23 and 0 <= m <= 59
    except ValueError:
        return False


@router.message(Command("settings"))
async def handle_settings(
    message: Message,
    command: CommandObject,
    session: AsyncSession,
    db_user: User | None,
) -> None:
    if db_user is None:
        await message.answer("Link your account first via the ReflectBoard website.")
        return

    repo = BotSettingsRepository(session)

    if not command.args:
        s = await repo.get(db_user.id)
        lead = s.deadline_lead_hours if s else 24
        reminder = s.reminder_time if s else None
        today = s.today_reminder_time if s else None
        tz = s.tz_offset_minutes if s else 0
        tz_h, tz_m = divmod(abs(tz), 60)
        tz_sign = "+" if tz >= 0 else "-"
        tz_str = f"{tz_sign}{tz_h}:{tz_m:02d}"
        await message.answer(
            f"Current settings:\n"
            f"deadline: {lead}h before\n"
            f"reminder: {reminder or 'off'}\n"
            f"today: {today or 'off'}\n"
            f"timezone: UTC{tz_str}\n"
            f"\n"
            f"Commands:\n"
            f"/settings deadline 24\n"
            f"/settings reminder 09:00\n"
            f"/settings reminder off\n"
            f"/settings today 08:00\n"
            f"/settings today off\n"
            f"/settings timezone +3"
        )
        return

    parts = command.args.split(maxsplit=1)
    key = parts[0].lower()
    value = parts[1].strip() if len(parts) > 1 else None

    if key == "deadline":
        if not value or not value.isdigit():
            await message.answer(
                "Set how many hours before a deadline to notify you.\n"
                "Example: /settings deadline 24"
            )
            return
        await repo.upsert(db_user.id, deadline_lead_hours=int(value))
        await message.answer(f"Deadline lead time set to {value}h.")

    elif key == "reminder":
        if not value:
            await message.answer(
                "Set a daily reminder to log your time.\n"
                "Example: /settings reminder 09:00\n"
                "To disable: /settings reminder off"
            )
            return
        if value.lower() == "off":
            await repo.upsert(db_user.id, reminder_time=None)
            await message.answer("Daily reminder disabled.")
        elif _validate_time(value):
            await repo.upsert(db_user.id, reminder_time=value)
            await message.answer(f"Daily reminder set to {value}.")
        else:
            await message.answer("Invalid time. Use HH:MM format, e.g. /settings reminder 09:00")

    elif key == "today":
        if not value:
            await message.answer(
                "Set a daily reminder with your active task count (Today + In Progress).\n"
                "Example: /settings today 08:00\n"
                "To disable: /settings today off"
            )
            return
        if value.lower() == "off":
            await repo.upsert(db_user.id, today_reminder_time=None)
            await message.answer("Today reminder disabled.")
        elif _validate_time(value):
            await repo.upsert(db_user.id, today_reminder_time=value)
            await message.answer(f"Today reminder set to {value}.")
        else:
            await message.answer("Invalid time. Use HH:MM format, e.g. /settings today 08:00")

    elif key == "timezone":
        if not value:
            await message.answer(
                "Set your UTC offset so reminders fire at the right local time.\n"
                "Examples: /settings timezone +3  or  /settings timezone -5:30"
            )
            return
        try:
            minutes = _parse_tz_offset(value)
        except (ValueError, IndexError):
            await message.answer("Invalid format. Examples: +3  or  -5:30")
            return
        if not (-720 <= minutes <= 840):
            await message.answer("Offset out of range (-12:00 to +14:00).")
            return
        await repo.upsert(db_user.id, tz_offset_minutes=minutes)
        await message.answer(f"Timezone set to UTC{value}.")

    else:
        await message.answer(
            "Unknown setting. Use /settings to see available commands."
        )
