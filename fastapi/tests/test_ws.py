import asyncio
import json
from unittest.mock import AsyncMock, MagicMock, patch

import pytest
from app.main import app
from httpx import AsyncClient
from httpx_ws import WebSocketDisconnect, aconnect_ws
from httpx_ws.transport import ASGIWebSocketTransport

pytestmark = pytest.mark.asyncio


class TestWebSocket:
    async def test_rejects_invalid_token(self, client: AsyncClient):
        with patch("app.api.v1.websocket.get_ws_user", return_value=None):
            async with AsyncClient(
                transport=ASGIWebSocketTransport(app=app), base_url="http://test"
            ) as ws_client:
                with pytest.raises(WebSocketDisconnect) as exc_info:
                    async with aconnect_ws(
                        "/api/v1/ws?token=invalid_token", ws_client
                    ) as ws:
                        pass
                assert exc_info.value.code == 1008

    async def test_accepts_valid_token(self, client: AsyncClient, test_user):
        mock_pubsub = MagicMock()
        mock_pubsub.subscribe = AsyncMock()
        mock_pubsub.unsubscribe = AsyncMock()
        mock_pubsub.close = AsyncMock()

        async def empty_listen():
            await asyncio.sleep(10)
            return
            yield

        mock_pubsub.listen = empty_listen

        with (
            patch("app.api.v1.websocket.get_ws_user", return_value=test_user),
            patch("app.api.v1.websocket.redis_client") as mock_redis,
        ):
            mock_redis.pubsub = MagicMock(return_value=mock_pubsub)

            async with AsyncClient(
                transport=ASGIWebSocketTransport(app=app), base_url="http://test"
            ) as ws_client:
                async with aconnect_ws("/api/v1/ws?token=valid_token", ws_client) as ws:
                    assert ws is not None

    async def test_receives_board_event(self, client: AsyncClient, test_user):
        event = json.dumps({"type": "card_moved", "activity_id": 1, "status": "today"})

        mock_pubsub = MagicMock()
        mock_pubsub.subscribe = AsyncMock()
        mock_pubsub.unsubscribe = AsyncMock()
        mock_pubsub.close = AsyncMock()

        async def mock_listen():
            yield {"type": "subscribe", "data": 1}
            yield {"type": "message", "data": event}
            await asyncio.sleep(10)

        mock_pubsub.listen = mock_listen

        with (
            patch("app.api.v1.websocket.get_ws_user", return_value=test_user),
            patch("app.api.v1.websocket.redis_client") as mock_redis,
        ):
            mock_redis.pubsub = MagicMock(return_value=mock_pubsub)

            async with AsyncClient(
                transport=ASGIWebSocketTransport(app=app), base_url="http://test"
            ) as ws_client:
                async with aconnect_ws("/api/v1/ws?token=valid_token", ws_client) as ws:
                    received = await asyncio.wait_for(ws.receive_text(), timeout=2.0)
                    data = json.loads(received)
                    assert data["type"] == "card_moved"
                    assert data["activity_id"] == 1
                    assert data["status"] == "today"

    async def test_subscribes_to_correct_channel(self, client: AsyncClient, test_user):
        mock_pubsub = MagicMock()
        mock_pubsub.subscribe = AsyncMock()
        mock_pubsub.unsubscribe = AsyncMock()
        mock_pubsub.close = AsyncMock()

        async def empty_listen():
            await asyncio.sleep(10)
            return
            yield

        mock_pubsub.listen = empty_listen

        with (
            patch("app.api.v1.websocket.get_ws_user", return_value=test_user),
            patch("app.api.v1.websocket.redis_client") as mock_redis,
        ):
            mock_redis.pubsub = MagicMock(return_value=mock_pubsub)

            async with AsyncClient(
                transport=ASGIWebSocketTransport(app=app), base_url="http://test"
            ) as ws_client:
                async with aconnect_ws("/api/v1/ws?token=valid_token", ws_client) as ws:
                    pass

            mock_pubsub.subscribe.assert_called_once_with(f"board:{test_user.id}")
