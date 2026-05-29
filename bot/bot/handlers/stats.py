from aiogram import Router
from aiogram.filters import Command
from aiogram.filters.command import CommandObject
from aiogram.types import Message
from sqlalchemy.ext.asyncio import AsyncSession

from bot.config import Settings
from bot.db.models import User
from bot.fastapi_client import get_client
from bot.repositories.bot_settings import BotSettingsRepository

router = Router()

_VALID_PERIODS = {"7d", "30d", "90d", "all"}
_PERIODS_HINT = "Available periods:\n/stats 7d\n/stats 30d\n/stats 90d\n/stats all"
_PERIOD_LABELS = {"7d": "last 7 days", "30d": "last 30 days", "90d": "last 90 days", "all": "all time"}


def _fmt(minutes: int) -> str:
    h, m = divmod(minutes, 60)
    return f"{h}h {m}min" if h else f"{m}min"


@router.message(Command("stats"))
async def handle_stats(
    message: Message,
    command: CommandObject,
    session: AsyncSession,
    db_user: User | None,
    settings: Settings,
) -> None:
    if db_user is None:
        await message.answer("Link your account first via the ReflectBoard website.")
        return

    if not db_user.api_token:
        await message.answer("Something went wrong. Please try relinking your account.")
        return

    arg = command.args.strip() if command.args else ""
    if not arg:
        await message.answer(_PERIODS_HINT)
        return
    if arg not in _VALID_PERIODS:
        await message.answer(f"Unknown period: {arg}\n{_PERIODS_HINT}")
        return
    period = arg

    repo = BotSettingsRepository(session)
    user_settings = await repo.get(db_user.id)
    tz_offset = user_settings.tz_offset_minutes if user_settings else 0

    try:
        async with get_client(db_user.api_token, settings.fastapi.base_url) as client:
            resp = await client.get(
                "/api/v1/analytics",
                params={"period": period, "tz_offset": tz_offset},
            )
            resp.raise_for_status()
            data = resp.json()
    except Exception:
        await message.answer("Failed to fetch stats. Please try again.")
        return

    overview = data["overview"]
    categories = data.get("categories", [])[:3]

    lines = [
        f"📊 Stats for {_PERIOD_LABELS[period]}",
        f"Done: {overview['total_done']} tasks ({overview['productive_done']} productive / {overview['unproductive_done']} unproductive)",
        f"Time: {_fmt(overview['total_minutes'])}",
        f"Streak: {overview['streak']} day(s)",
        f"Completion rate: {overview['completion_rate']}%",
    ]

    if categories:
        cat_lines = [f"  {c['name']}: {_fmt(c['minutes'])} ({c['count']} tasks)" for c in categories]
        lines.append("Top categories:\n" + "\n".join(cat_lines))

    await message.answer("\n".join(lines))
