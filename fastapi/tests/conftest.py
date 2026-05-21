import os
from typing import AsyncGenerator
from unittest.mock import AsyncMock, patch

import pytest_asyncio
from app.db.session import Base, get_db
from app.dependencies.auth import get_current_user
from app.main import app
from app.models.category import Category
from app.models.user import User
from httpx import ASGITransport, AsyncClient
from sqlalchemy.ext.asyncio import AsyncSession, async_sessionmaker, create_async_engine
from sqlalchemy.pool import NullPool

TEST_DATABASE_URL = os.getenv(
    "TEST_DATABASE_URL", "mysql+aiomysql://root:secure_password@db/reflectboard_test"
)


@pytest_asyncio.fixture(autouse=True)
def mock_redis():
    with (
        patch("app.db.redis.redis_client.publish", new_callable=AsyncMock),
        patch(
            "app.db.redis.redis_client.get", new_callable=AsyncMock, return_value=None
        ),
        patch("app.db.redis.redis_client.setex", new_callable=AsyncMock),
        patch("app.db.redis.redis_client.incrby", new_callable=AsyncMock),
        patch("app.db.redis.redis_client.expire", new_callable=AsyncMock),
    ):
        yield


@pytest_asyncio.fixture()
async def engine():
    eng = create_async_engine(TEST_DATABASE_URL, echo=False, poolclass=NullPool)
    yield eng
    await eng.dispose()


@pytest_asyncio.fixture()
def session_maker(engine):
    return async_sessionmaker(
        bind=engine,
        class_=AsyncSession,
        expire_on_commit=False,
    )


@pytest_asyncio.fixture(autouse=True)
async def clean_db(engine):
    async with engine.begin() as conn:
        await conn.run_sync(Base.metadata.drop_all)
        await conn.run_sync(Base.metadata.create_all)
    yield
    async with engine.begin() as conn:
        await conn.run_sync(Base.metadata.drop_all)


@pytest_asyncio.fixture()
async def db(session_maker):
    async with session_maker() as session:
        yield session


@pytest_asyncio.fixture()
async def test_user(db: AsyncSession) -> User:
    user = User(email="test@example.com", password="hashed")
    db.add(user)
    await db.commit()
    await db.refresh(user)
    return user


@pytest_asyncio.fixture()
async def test_category(db: AsyncSession, test_user: User) -> Category:
    category = Category(user_id=test_user.id, name="Test Category", color="#7e9cd8")
    db.add(category)
    await db.commit()
    await db.refresh(category)
    return category


@pytest_asyncio.fixture()
async def client(session_maker, test_user: User) -> AsyncGenerator[AsyncClient, None]:
    async def override_get_db():
        async with session_maker() as session:
            yield session

    app.dependency_overrides[get_db] = override_get_db
    app.dependency_overrides[get_current_user] = lambda: test_user

    async with AsyncClient(
        transport=ASGITransport(app=app), base_url="http://test"
    ) as ac:
        yield ac

    app.dependency_overrides.clear()
