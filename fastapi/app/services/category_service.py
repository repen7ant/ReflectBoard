from app.models.category import Category
from app.schemas.category import CategoryCreate, CategoryOut
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

    @classmethod
    async def create_category(
        cls, db: AsyncSession, category_in: CategoryCreate
    ) -> CategoryOut:
        category = Category(**category_in.model_dump())
        db.add(category)
        await db.commit()
        await db.refresh(category)
        return category

    @staticmethod
    async def delete_category(db: AsyncSession, category_id: int) -> bool:
        result = await db.execute(
            select(Category).where(
                Category.id == category_id,
            )
        )
        category = result.scalar_one_or_none()
        if not category:
            return False

        await db.delete(category)
        await db.commit()
        return True
