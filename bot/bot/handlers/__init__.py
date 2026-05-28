from aiogram import Router

from bot.handlers import start, unlink


def get_routers() -> list[Router]:
    return [start.router, unlink.router]
