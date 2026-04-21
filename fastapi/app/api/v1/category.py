from app.db.session import get_db
from app.dependencies.auth import get_current_user
from app.models.user import User
from app.schemas.category import CategoryCreate, CategoryOut
from app.services.category_service import CategoryService
from sqlalchemy.ext.asyncio import AsyncSession

from fastapi import APIRouter, Depends, HTTPException

router = APIRouter(prefix="/api/v1", tags=["Categories"])


@router.get("/categories", response_model=list[CategoryOut])
async def get_categories(
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    return await CategoryService.get_categories(db, current_user.id)


@router.post("/categories", response_model=CategoryOut)
async def create_category(
    category: CategoryCreate,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    return await CategoryService.create_category(db, category, current_user.id)


@router.delete("/categories/{category_id}", status_code=204)
async def delete_category(
    category_id: int,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    deleted = await CategoryService.delete_category(db, category_id, current_user.id)
    if not deleted:
        raise HTTPException(status_code=404, detail="Category not found or not yours")
    return None
