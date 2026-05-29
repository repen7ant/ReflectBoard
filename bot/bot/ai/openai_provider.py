import httpx

from bot.ai.base import ANALYSIS_PROMPT, format_stats_for_prompt


class OpenAICompatibleProvider:
    def __init__(self, api_key: str, base_url: str) -> None:
        self._api_key = api_key
        self._base_url = base_url.rstrip("/")

    async def analyze(self, stats: dict) -> str:
        prompt = ANALYSIS_PROMPT.format(stats_text=format_stats_for_prompt(stats))
        async with httpx.AsyncClient(timeout=30.0) as client:
            resp = await client.post(
                f"{self._base_url}/chat/completions",
                headers={
                    "Authorization": f"Bearer {self._api_key}",
                    "content-type": "application/json",
                },
                json={
                    "model": "gpt-4o-mini",
                    "max_tokens": 512,
                    "messages": [{"role": "user", "content": prompt}],
                },
            )
            resp.raise_for_status()
            return resp.json()["choices"][0]["message"]["content"]
