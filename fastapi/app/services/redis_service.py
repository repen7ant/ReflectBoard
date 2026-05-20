from datetime import date, datetime, timedelta, timezone

from app.db.redis import redis_client


def _daily_key(user_id: int, day: date, category_id: int | None) -> str:
    cat = category_id if category_id else "none"
    return f"stats:user:{user_id}:daily:{day.isoformat()}:category:{cat}"


async def record_completion(
    user_id: int,
    time_spent_minutes: int | None,
    category_id: int | None,
    completed_at: datetime | None = None,
) -> None:
    """Dual-Write: increment counters in Redis when a task is completed."""
    if not time_spent_minutes:
        return

    if completed_at and not isinstance(completed_at, datetime):
        completed_at = None  # fallback on current time

    day = (completed_at or datetime.now(timezone.utc)).date()
    key = _daily_key(user_id, day, category_id)

    await redis_client.incrby(key, time_spent_minutes)
    # TTL 100 days — data older than this is not needed for the live block
    await redis_client.expire(key, 60 * 60 * 24 * 100)


async def get_live_stats(user_id: int) -> dict:
    """
    Live data for the last 24 hours from Redis.
    Returns: { total_minutes: int, by_category: [{category_id, minutes}] }
    """
    now = datetime.now(timezone.utc)
    today = now.date()
    yesterday = today - timedelta(days=1)

    # Fetch keys for today and yesterday (covering a 24-hour sliding window)
    pattern_today = f"stats:user:{user_id}:daily:{today.isoformat()}:category:*"
    pattern_yesterday = f"stats:user:{user_id}:daily:{yesterday.isoformat()}:category:*"

    keys: list[str] = []
    async for key in redis_client.scan_iter(pattern_today):
        keys.append(key)
    async for key in redis_client.scan_iter(pattern_yesterday):
        keys.append(key)

    if not keys:
        return {"total_minutes": 0, "by_category": []}

    values = await redis_client.mget(*keys)

    total = 0
    by_category: dict[str, int] = {}

    for key, val in zip(keys, values):
        if not val:
            continue
        minutes = int(val)
        # Parse category_id from the key
        # format: stats:user:{id}:daily:{date}:category:{cat}
        cat_part = key.split(":category:")[-1]

        total += minutes
        by_category[cat_part] = by_category.get(cat_part, 0) + minutes

    by_category_list = [
        {"category_id": k if k != "none" else None, "minutes": v}
        for k, v in sorted(by_category.items(), key=lambda x: -x[1])
    ]

    return {"total_minutes": total, "by_category": by_category_list}
