import structlog
from aiogram import Router
from aiogram.filters import Command
from aiogram.filters.command import CommandObject
from aiogram.types import Message
from redis.asyncio import Redis
from sqlalchemy.ext.asyncio import AsyncSession

from bot.ai.anthropic_provider import AnthropicProvider
from bot.ai.openai_provider import OpenAICompatibleProvider
from bot.config import Settings
from bot.db.models import User
from bot.fastapi_client import get_client
from bot.repositories.bot_settings import BotSettingsRepository

router = Router()
logger = structlog.get_logger()

_VALID_PERIODS = {"7d", "30d", "90d", "all"}
_PERIODS_HINT = "Available periods:\n/analyze 7d\n/analyze 30d\n/analyze 90d\n/analyze all"


@router.message(Command("analyze"))
async def handle_analyze(
    message: Message,
    command: CommandObject,
    session: AsyncSession,
    db_user: User | None,
    settings: Settings,
    redis: Redis,
) -> None:
    if db_user is None:
        await message.answer("Link your account first via the ReflectBoard website.")
        return

    if not db_user.api_token:
        await message.answer("Something went wrong. Please try relinking your account.")
        return

    if settings.ai is None:
        await message.answer("AI analysis is not configured.")
        return

    rate_key = f"analyze:cooldown:{db_user.id}"
    if await redis.get(rate_key):
        ttl = await redis.ttl(rate_key)
        minutes = ttl // 60
        seconds = ttl % 60
        await message.answer(f"You can use /analyze once per hour. Try again in {minutes}m {seconds}s.")
        return

    arg = command.args.strip() if command.args else ""
    if not arg:
        await message.answer(_PERIODS_HINT)
        return
    if arg not in _VALID_PERIODS:
        await message.answer(f"Unknown period: {arg}\n{_PERIODS_HINT}")
        return

    repo = BotSettingsRepository(session)
    user_settings = await repo.get(db_user.id)
    tz_offset = user_settings.tz_offset_minutes if user_settings else 0

    try:
        async with get_client(db_user.api_token, settings.fastapi.base_url) as client:
            resp = await client.get(
                "/api/v1/analytics",
                params={"period": arg, "tz_offset": tz_offset},
            )
            resp.raise_for_status()
            data = resp.json()
    except Exception:
        await message.answer("Failed to fetch stats. Please try again.")
        return

    await message.answer("Analyzing your stats, please wait...")

    api_key = settings.ai.api_key.get_secret_value()
    if settings.ai.provider == "anthropic":
        provider = AnthropicProvider(api_key)
    elif settings.ai.provider == "openai_compatible":
        if not settings.ai.base_url:
            await message.answer("AI base_url not configured for openai_compatible provider.")
            return
        provider = OpenAICompatibleProvider(api_key, settings.ai.base_url)
    else:
        await message.answer(f"Unknown AI provider: {settings.ai.provider}")
        return

    try:
        analysis = await provider.analyze(data)
    except Exception:
        await logger.awarning("AI analysis failed", user_id=db_user.id)
        await message.answer("AI analysis failed. Please try again.")
        return

    await redis.setex(rate_key, 3600, "1")
    await message.answer(analysis, parse_mode="HTML")
