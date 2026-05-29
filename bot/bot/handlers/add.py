import html

from aiogram import Router
from aiogram.filters import Command
from aiogram.filters.command import CommandObject
from aiogram.types import Message

from bot.config import Settings
from bot.db.models import User
from bot.fastapi_client import get_client

router = Router()


@router.message(Command("add"))
async def handle_add(
    message: Message,
    command: CommandObject,
    db_user: User | None,
    settings: Settings,
) -> None:
    if db_user is None:
        await message.answer("Link your account first via the ReflectBoard website.")
        return

    title = command.args
    if not title or not title.strip():
        await message.answer(
            "Usage: <code>/add &lt;task title&gt;</code>\nExample: <code>/add Buy groceries</code>",
            parse_mode="HTML",
        )
        return

    if not db_user.api_token:
        await message.answer("Something went wrong. Please try relinking your account.")
        return

    title = title.strip()

    try:
        async with get_client(db_user.api_token, settings.fastapi.base_url) as client:
            resp = await client.post(
                "/api/v1/activities",
                json={"title": title, "status": "backlog"},
            )
            resp.raise_for_status()
    except Exception:
        await message.answer("Failed to create task. Please try again.")
        return

    await message.answer(f'✅ Added to backlog: "<b>{html.escape(title)}</b>"', parse_mode="HTML")
