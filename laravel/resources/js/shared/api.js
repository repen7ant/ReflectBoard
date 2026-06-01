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
const WS_BACKOFF_BASE_MS  = 1000;
const WS_BACKOFF_MAX_MS   = 30000;

export function initWebSocket(apiBase, token, onmessage, _attempt = 0) {
    const url = new URL(apiBase);
    const wsProtocol = url.protocol === 'https:' ? 'wss:' : 'ws:';
    const wsUrl = `${wsProtocol}//${url.host}/api/v1/ws`;

    const ws = new WebSocket(wsUrl);
    ws.onopen = () => {
        ws.send(JSON.stringify({ token }));
        _attempt = 0;
    };
    ws.onmessage = (event) => onmessage(JSON.parse(event.data));
    ws.onclose = () => {
        const delay = Math.min(WS_BACKOFF_BASE_MS * 2 ** _attempt, WS_BACKOFF_MAX_MS);
        const jitter = Math.random() * 0.3 * delay;
        setTimeout(() => initWebSocket(apiBase, token, onmessage, _attempt + 1), delay + jitter);
    };
    return ws;
}
