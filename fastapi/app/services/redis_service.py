from datetime import date, datetime, timedelta, timezone

from app.db.redis import redis_client


def _daily_key(user_id: int, day: date, category_id: int | None, productive: bool) -> str:
    cat = category_id if category_id else "none"
    suffix = "productive" if productive else "unproductive"
    return f"stats:user:{user_id}:daily:{day.isoformat()}:category:{cat}:{suffix}"


async def record_completion(
    user_id: int,
    time_spent_minutes: int | None,
    category_id: int | None,
    is_productive: bool = True,
    completed_at: datetime | None = None,
) -> None:
    """Dual-Write: increment counters in Redis when a task is completed."""
    if not time_spent_minutes:
        return

    if completed_at and not isinstance(completed_at, datetime):
        completed_at = None

    day = (completed_at or datetime.now(timezone.utc)).date()
    key = _daily_key(user_id, day, category_id, is_productive)

    await redis_client.incrby(key, time_spent_minutes)
    await redis_client.expire(key, 60 * 60 * 24 * 100)


async def get_live_stats(user_id: int) -> dict:
    """
    Live data for the last 24 hours from Redis.
    Returns: { productive_minutes, unproductive_minutes, by_category: [{category_id, productive, unproductive}] }
    """
    now = datetime.now(timezone.utc)
    today = now.date()
    yesterday = today - timedelta(days=1)

    keys: list[str] = []
    for day in (today, yesterday):
        pattern = f"stats:user:{user_id}:daily:{day.isoformat()}:category:*"
        async for key in redis_client.scan_iter(pattern):
            keys.append(key)

    if not keys:
        return {"productive_minutes": 0, "unproductive_minutes": 0, "by_category": []}

    values = await redis_client.mget(*keys)

    productive_total = 0
    unproductive_total = 0
    by_category: dict[str, dict[str, int]] = {}

    for key, val in zip(keys, values):
        if not val:
            continue
        minutes = int(val)
        # format: stats:user:{id}:daily:{date}:category:{cat}:productive|unproductive
        parts = key.split(":category:")
        cat_suffix = parts[-1]  # e.g. "1:productive" or "none:unproductive"
        cat_suffix_parts = cat_suffix.rsplit(":", 1)
        cat_part = cat_suffix_parts[0]
        kind = cat_suffix_parts[1] if len(cat_suffix_parts) == 2 else "productive"

        if cat_part not in by_category:
            by_category[cat_part] = {"productive": 0, "unproductive": 0}

        if kind == "productive":
            by_category[cat_part]["productive"] += minutes
            productive_total += minutes
        else:
            by_category[cat_part]["unproductive"] += minutes
            unproductive_total += minutes

    by_category_list = [
        {
            "category_id": int(k) if k != "none" else None,
            "productive": v["productive"],
            "unproductive": v["unproductive"],
        }
        for k, v in sorted(
            by_category.items(),
            key=lambda x: -(x[1]["productive"] + x[1]["unproductive"]),
        )
    ]

    return {
        "productive_minutes": productive_total,
        "unproductive_minutes": unproductive_total,
        "by_category": by_category_list,
    }
