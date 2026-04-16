from app.db.session import get_db
from app.schemas.category import CategoryCreate, CategoryOut
from app.services.category_service import CategoryService
from sqlalchemy.ext.asyncio import AsyncSession

from fastapi import APIRouter, Depends

router = APIRouter(prefix="/api/v1")


@router.get("/categories", response_model=list[CategoryOut])
async def get_categories(
    db: AsyncSession = Depends(get_db),
):
    return await CategoryService.get_categories(db)


@router.post("/categories", response_model=CategoryOut)
async def create_category(
    category: CategoryCreate,
    db: AsyncSession = Depends(get_db),
):
    return await CategoryService.create_category(db, category)
