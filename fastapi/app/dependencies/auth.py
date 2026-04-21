from app.db.redis import redis_client
from app.db.session import get_db
from app.models.user import User
from fastapi.security import OAuth2PasswordBearer
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from fastapi import Depends, HTTPException, status

oauth2_scheme = OAuth2PasswordBearer(tokenUrl="token")


async def get_current_user(
    token: str = Depends(oauth2_scheme), db: AsyncSession = Depends(get_db)
) -> User:
    cache_key = f"auth_token:{token}"

    user_id = await redis_client.get(cache_key)

    if user_id:
        result = await db.execute(select(User).where(User.id == int(user_id)))
        user = result.scalar_one_or_none()
    else:
        result = await db.execute(select(User).where(User.api_token == token))
        user = result.scalar_one_or_none()

        if user:
            await redis_client.setex(cache_key, 3600, user.id)

    if not user:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid or missing API Token",
            headers={"WWW-Authenticate": "Bearer"},
        )

    return user


async def get_ws_user(token: str, db: AsyncSession) -> User | None:
    cache_key = f"auth_token:{token}"
    user_id = await redis_client.get(cache_key)

    if user_id:
        result = await db.execute(select(User).where(User.id == int(user_id)))
        return result.scalar_one_or_none()

    result = await db.execute(select(User).where(User.api_token == token))
    user = result.scalar_one_or_none()

    if user:
        await redis_client.setex(cache_key, 3600, user.id)

    return user
