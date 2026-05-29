from aiogram import Router

from bot.handlers import add, settings, start, stats, unlink


def get_routers() -> list[Router]:
    return [start.router, unlink.router, settings.router, add.router, stats.router]
