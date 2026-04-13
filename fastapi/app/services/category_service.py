from app.models.category import Category
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession


class CategoryService:
    @staticmethod
    async def get_categories(
        db: AsyncSession,
    ):
        query = select(Category)
        result = await db.execute(query)
        return result.scalars().all()
