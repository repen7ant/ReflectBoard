from app.db.session import get_db
from app.schemas.activity import CategoryOut
from app.services.category_service import CategoryService
from sqlalchemy.ext.asyncio import AsyncSession

from fastapi import APIRouter, Depends

router = APIRouter(prefix="/api/v1")


@router.get("/categories", response_model=list[CategoryOut])
async def get_categories(
    db: AsyncSession = Depends(get_db),
):
    return await CategoryService.get_categories(db)
