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
        raw_lead = s.deadline_lead_hours if s else "24"
        lead = ", ".join(f"{h}h" for h in raw_lead.split(","))
        reminder = s.reminder_time if s else None
        today = s.today_reminder_time if s else None
        tz = s.tz_offset_minutes if s else 0
        tz_h, tz_m = divmod(abs(tz), 60)
        tz_sign = "+" if tz >= 0 else "-"
        tz_str = f"{tz_sign}{tz_h}:{tz_m:02d}"
        await message.answer(
            f"<b>Current settings:</b>\n"
            f"• deadline: <b>{lead}</b> before\n"
            f"• log activities reminder: <b>{reminder or 'off'}</b>\n"
            f"• active tasks reminder: <b>{today or 'off'}</b>\n"
            f"• timezone: <b>UTC{tz_str}</b>\n"
            f"\n"
            f"<b>Commands:</b>\n"
            f"<code>/settings deadline 24 72 168</code>\n"
            f"<code>/settings reminder 09:00</code> — log activities\n"
            f"<code>/settings reminder off</code>\n"
            f"<code>/settings today 08:00</code> — active tasks count\n"
            f"<code>/settings today off</code>\n"
            f"<code>/settings timezone +3</code>",
            parse_mode="HTML",
        )
        return

    parts = command.args.split(maxsplit=1)
    key = parts[0].lower()
    value = parts[1].strip() if len(parts) > 1 else None

    if key == "deadline":
        if not value:
            await message.answer(
                "Set how many hours before a deadline to notify you.\n"
                "One value or several space-separated:\n"
                "<code>/settings deadline 24</code>\n"
                "<code>/settings deadline 24 72 168</code>",
                parse_mode="HTML",
            )
            return
        hours = [v for v in value.split() if v.isdigit()]
        if not hours:
            await message.answer("Invalid value. Use hours, e.g. <code>/settings deadline 24 72 168</code>", parse_mode="HTML")
            return
        stored = ",".join(sorted(hours, key=int, reverse=True))
        await repo.upsert(db_user.id, deadline_lead_hours=stored)
        labels = ", ".join(f"<b>{h}h</b>" for h in hours)
        await message.answer(f"✅ Deadline reminders set: {labels}.", parse_mode="HTML")

    elif key == "reminder":
        if not value:
            await message.answer(
                "Set a daily reminder to log your activities.\n"
                "Example: <code>/settings reminder 09:00</code>\n"
                "To disable: <code>/settings reminder off</code>",
                parse_mode="HTML",
            )
            return
        if value.lower() == "off":
            await repo.upsert(db_user.id, reminder_time=None)
            await message.answer("✅ Daily reminder disabled.")
        elif _validate_time(value):
            await repo.upsert(db_user.id, reminder_time=value)
            await message.answer(f"✅ Daily reminder set to <b>{value}</b>.", parse_mode="HTML")
        else:
            await message.answer("Invalid time. Use HH:MM format, e.g. <code>/settings reminder 09:00</code>", parse_mode="HTML")

    elif key == "today":
        if not value:
            await message.answer(
                "Set a daily reminder with your active task count.\n"
                "Example: <code>/settings today 08:00</code>\n"
                "To disable: <code>/settings today off</code>",
                parse_mode="HTML",
            )
            return
        if value.lower() == "off":
            await repo.upsert(db_user.id, today_reminder_time=None)
            await message.answer("✅ Today reminder disabled.")
        elif _validate_time(value):
            await repo.upsert(db_user.id, today_reminder_time=value)
            await message.answer(f"✅ Today reminder set to <b>{value}</b>.", parse_mode="HTML")
        else:
            await message.answer("Invalid time. Use HH:MM format, e.g. <code>/settings today 08:00</code>", parse_mode="HTML")

    elif key == "timezone":
        if not value:
            await message.answer(
                "Set your UTC offset so reminders fire at the right local time.\n"
                "Examples: <code>/settings timezone +3</code>  or  <code>/settings timezone -5:30</code>",
                parse_mode="HTML",
            )
            return
        try:
            minutes = _parse_tz_offset(value)
        except (ValueError, IndexError):
            await message.answer("Invalid format. Examples: <code>+3</code>  or  <code>-5:30</code>", parse_mode="HTML")
            return
        if not (-720 <= minutes <= 840):
            await message.answer("Offset out of range (-12:00 to +14:00).")
            return
        await repo.upsert(db_user.id, tz_offset_minutes=minutes)
        await message.answer(f"✅ Timezone set to <b>UTC{value}</b>.", parse_mode="HTML")

    else:
        await message.answer("Unknown setting. Use <code>/settings</code> to see available commands.", parse_mode="HTML")
