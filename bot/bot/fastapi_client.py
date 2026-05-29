import httpx


def get_client(api_token: str, base_url: str) -> httpx.AsyncClient:
    return httpx.AsyncClient(
        base_url=base_url,
        headers={"Authorization": f"Bearer {api_token}"},
        timeout=10.0,
    )
