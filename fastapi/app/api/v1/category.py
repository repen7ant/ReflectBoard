import json

from app.db.redis import redis_client
from app.db.session import get_db
from app.dependencies.auth import get_current_user
from app.models.user import User
from app.schemas.category import CategoryCreate, CategoryOut
from app.services.category_service import CategoryService
from fastapi.encoders import jsonable_encoder
from sqlalchemy.ext.asyncio import AsyncSession

from fastapi import APIRouter, Depends, HTTPException

router = APIRouter(prefix="/api/v1", tags=["Categories"])


async def _publish(user_id: int, action: str, data: dict) -> None:
    payload = json.dumps({"action": action, "data": data})
    await redis_client.publish(f"board:{user_id}", payload)


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
    created = await CategoryService.create_category(db, category, current_user.id)
    out = CategoryOut.model_validate(created)
    await _publish(current_user.id, "category_create", jsonable_encoder(out))
    return created


@router.delete("/categories/{category_id}", status_code=204)
async def delete_category(
    category_id: int,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_user),
):
    deleted = await CategoryService.delete_category(db, category_id, current_user.id)
    if not deleted:
        raise HTTPException(status_code=404, detail="Category not found or not yours")
    await _publish(current_user.id, "category_delete", {"id": category_id})
    return None
