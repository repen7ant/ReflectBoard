// ─── Auth ─────────────────────────────────────────────
export function getAuthToken() {
    return document.querySelector('meta[name="api-token"]')?.content;
}

export function getAuthConfig() {
    return { headers: { Authorization: `Bearer ${getAuthToken()}` } };
}

export function requireAuthOrRedirect() {
    const token = getAuthToken();
    if (!token) {
        window.location.href = '/login';
        return null;
    }
    return token;
}

// ─── WebSocket ────────────────────────────────────────
const WS_RECONNECT_MS = 3000;

export function initWebSocket(apiBase, token, onmessage) {
    const url = new URL(apiBase);
    const wsProtocol = url.protocol === 'https:' ? 'wss:' : 'ws:';
    const wsUrl = `${wsProtocol}//${url.host}/api/v1/ws?token=${token}`;

    const ws = new WebSocket(wsUrl);
    ws.onmessage = (event) => onmessage(JSON.parse(event.data));
    ws.onclose = () => {
        setTimeout(() => initWebSocket(apiBase, token, onmessage), WS_RECONNECT_MS);
    };
    return ws;
}
