from aiogram import Router

from bot.handlers import link, start


def get_routers() -> list[Router]:
    return [start.router, link.router]
