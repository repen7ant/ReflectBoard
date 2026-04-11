from datetime import datetime

from app.models.activity import Status
from pydantic import BaseModel, ConfigDict

from .category import CategoryOut


class ActivityOut(BaseModel):
    id: int
    user_id: int
    parent_id: int | None
    category_id: int | None
    title: str
    description: str | None
    status: Status
    is_project: bool
    is_on_board: bool
    is_quick_capture: bool
    deadline: datetime | None
    tags: list | None
    completed_at: datetime | None
    created_at: datetime
    updated_at: datetime

    category: CategoryOut | None

    model_config = ConfigDict(from_attributes=True)
