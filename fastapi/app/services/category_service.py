from app.models.category import Category
from app.schemas.category import CategoryCreate, CategoryOut
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession


class CategoryService:
    @staticmethod
    async def get_categories(db: AsyncSession, user_id: int):
        query = select(Category).where(Category.user_id == user_id)
        result = await db.execute(query)
        return result.scalars().all()

    @classmethod
    async def create_category(
        cls, db: AsyncSession, category_in: CategoryCreate, user_id: int
    ) -> CategoryOut:
        category_dict = category_in.model_dump(exclude={"user_id"}, exclude_unset=True)
        category = Category(**category_dict, user_id=user_id)
        db.add(category)
        await db.commit()
        await db.refresh(category)
        return category

    @staticmethod
    async def delete_category(db: AsyncSession, category_id: int, user_id: int) -> bool:
        result = await db.execute(
            select(Category).where(
                Category.id == category_id, Category.user_id == user_id
            )
        )
        category = result.scalar_one_or_none()
        if not category:
            return False

        await db.delete(category)
        await db.commit()
        return True
