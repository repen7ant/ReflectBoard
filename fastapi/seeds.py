import asyncio

from app.db.session import AsyncSessionLocal
from app.models.activity import Activity
from app.models.category import Category
from app.models.user import User


async def seed():
    async with AsyncSessionLocal() as db:
        user = User(name="Test User", email="test@test.com", password="hash")
        db.add(user)
        await db.flush()

        study = Category(user_id=user.id, name="Учёба", color="#3b82f6")
        work = Category(user_id=user.id, name="Работа", color="#10b981")
        sport = Category(user_id=user.id, name="Спорт", color="#f59e0b")
        db.add_all([study, work, sport])
        await db.flush()

        activities = [
            Activity(
                user_id=user.id,
                category_id=study.id,
                title="Прочитать главу по SQLAlchemy",
                status="backlog",
            ),
            Activity(
                user_id=user.id,
                category_id=study.id,
                title="Разобраться с Alembic",
                status="today",
            ),
            Activity(
                user_id=user.id,
                category_id=work.id,
                title="Написать CRUD эндпоинты",
                status="today",
            ),
            Activity(
                user_id=user.id,
                category_id=work.id,
                title="Настроить JWT middleware",
                status="in_process",
            ),
            Activity(
                user_id=user.id,
                category_id=sport.id,
                title="Пробежка 5км",
                status="backlog",
            ),
            Activity(
                user_id=user.id,
                category_id=study.id,
                title="Посмотреть лекцию по FastAPI",
                status="done",
                reflection_text="Хорошо объяснили dependency injection",
                time_spent_minutes=90,
            ),
        ]
        db.add_all(activities)
        await db.commit()
        print("Seeded successfully")


asyncio.run(seed())
