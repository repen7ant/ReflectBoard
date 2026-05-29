from aiogram import Router

from bot.handlers import settings, start, unlink


def get_routers() -> list[Router]:
    return [start.router, unlink.router, settings.router]
