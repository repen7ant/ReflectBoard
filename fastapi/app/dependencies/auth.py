import json

from app.db.redis import redis_client
from app.db.session import get_db
from app.models.user import User
from fastapi.security import OAuth2PasswordBearer
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from fastapi import Depends, HTTPException, status

oauth2_scheme = OAuth2PasswordBearer(tokenUrl="token")

# Token cache. Key format MUST stay "auth_token:{token}" — Laravel deletes this
# same key on logout / account deletion to invalidate the cache.
_CACHE_TTL_SECONDS = 3600


def _serialize_user(user: User) -> str:
    """Cache only the non-sensitive scalar fields handlers actually read.

    Never the password hash or remember_token.
    """
    return json.dumps(
        {
            "id": user.id,
            "email": user.email,
            "github_id": user.github_id,
            "api_token": user.api_token,
        }
    )


def _deserialize_user(raw: str) -> User:
    """Rebuild a transient (session-less) User from cached JSON.

    Safe because request handlers only read scalar fields (e.g. current_user.id)
    and never persist this object or touch its relationships.
    """
    return User(**json.loads(raw))


async def _resolve_user(token: str, db: AsyncSession) -> User | None:
    """Resolve a User from an API token.

    On a cache hit the user is rebuilt straight from Redis — MySQL is not
    touched at all. The DB is queried only on a miss, then the result is cached.
    """
    cache_key = f"auth_token:{token}"
    cached = await redis_client.get(cache_key)

    if cached:
        return _deserialize_user(cached)

    result = await db.execute(select(User).where(User.api_token == token))
    user = result.scalar_one_or_none()
    if user:
        await redis_client.setex(cache_key, _CACHE_TTL_SECONDS, _serialize_user(user))
    return user


async def get_current_user(
    token: str = Depends(oauth2_scheme), db: AsyncSession = Depends(get_db)
) -> User:
    user = await _resolve_user(token, db)
    if not user:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid or missing API Token",
            headers={"WWW-Authenticate": "Bearer"},
        )
    return user


async def get_ws_user(token: str, db: AsyncSession) -> User | None:
    return await _resolve_user(token, db)
