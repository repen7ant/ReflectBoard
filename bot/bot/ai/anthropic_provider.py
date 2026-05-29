import httpx

from bot.ai.base import ANALYSIS_PROMPT, format_stats_for_prompt


class AnthropicProvider:
    def __init__(self, api_key: str) -> None:
        self._api_key = api_key

    async def analyze(self, stats: dict) -> str:
        prompt = ANALYSIS_PROMPT.format(stats_text=format_stats_for_prompt(stats))
        async with httpx.AsyncClient(timeout=30.0) as client:
            resp = await client.post(
                "https://api.anthropic.com/v1/messages",
                headers={
                    "x-api-key": self._api_key,
                    "anthropic-version": "2023-06-01",
                    "content-type": "application/json",
                },
                json={
                    "model": "claude-haiku-4-5-20251001",
                    "max_tokens": 512,
                    "messages": [{"role": "user", "content": prompt}],
                },
            )
            resp.raise_for_status()
            return resp.json()["content"][0]["text"]
