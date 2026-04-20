import asyncio

from app.db.redis import redis_client
from app.db.session import get_db
from app.dependencies.auth import get_ws_user
from sqlalchemy.ext.asyncio import AsyncSession

from fastapi import APIRouter, Depends, Query, WebSocket, WebSocketDisconnect

router = APIRouter(prefix="/api/v1")


@router.websocket("/ws")
async def websocket_endpoint(
    websocket: WebSocket, token: str = Query(...), db: AsyncSession = Depends(get_db)
):
    user = await get_ws_user(token, db)
    print(f"WS CONNECTION ATTEMPT: Token={token}, Found User={user}")
    if not user:
        # 1008 - Policy Violation (невалидный токен)
        await websocket.close(code=1008)
        return

    await websocket.accept()

    pubsub = redis_client.pubsub()
    channel_name = f"board:{user.id}"
    await pubsub.subscribe(channel_name)

    async def reader():
        try:
            async for message in pubsub.listen():
                if message["type"] == "message":
                    await websocket.send_text(message["data"])
        except Exception:
            pass

    task = asyncio.create_task(reader())

    try:
        while True:
            await websocket.receive_text()
    except WebSocketDisconnect:
        pass
    finally:
        task.cancel()
        await pubsub.unsubscribe(channel_name)
        await pubsub.close()
