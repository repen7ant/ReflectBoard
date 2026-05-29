from typing import Protocol


class AIProvider(Protocol):
    async def analyze(self, stats: dict) -> str: ...


def format_stats_for_prompt(data: dict) -> str:
    overview = data.get("overview", {})
    categories = data.get("categories", [])[:5]
    tags = data.get("tags", [])[:10]

    lines = [
        f"Tasks completed: {overview.get('total_done', 0)}",
        f"Productive: {overview.get('productive_done', 0)}, Unproductive: {overview.get('unproductive_done', 0)}",
        f"Total time: {overview.get('total_minutes', 0)} minutes",
        f"Productive time: {overview.get('productive_minutes', 0)} min, Unproductive: {overview.get('unproductive_minutes', 0)} min",
        f"Streak: {overview.get('streak', 0)} days",
        f"Completion rate: {overview.get('completion_rate', 0)}%",
    ]
    if categories:
        cat_str = ", ".join(f"{c['name']} ({c['minutes']}min, {c['count']} tasks)" for c in categories)
        lines.append(f"Top categories: {cat_str}")
    if tags:
        tag_str = ", ".join(f"{t['tag']}({t['count']})" for t in tags)
        lines.append(f"Frequent tags: {tag_str}")
    return "\n".join(lines)


ANALYSIS_PROMPT = """\
You are a productivity coach. Analyze the user's activity stats below and give brief, actionable insight in 3-5 sentences. Focus on patterns, what's going well, and one specific improvement suggestion.

{stats_text}"""
