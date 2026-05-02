from datetime import datetime

from pydantic import BaseModel, ConfigDict, Field

from app.models.activity import Status

from .category import CategoryOut


class ActivityOut(BaseModel):
    id: int
    user_id: int
    parent_id: int | None
    category_id: int | None
    category_snapshot_name: str | None = None
    category_snapshot_color: str | None = None
    title: str
    description: str | None
    reflection_text: str | None
    time_spent_minutes: int | None
    status: Status
    is_project: bool
    is_on_board: bool
    is_quick_capture: bool
    deadline: datetime | None
    tags: list[str] | None = None
    completed_at: datetime | None
    created_at: datetime
    updated_at: datetime

    category: CategoryOut | None
    model_config = ConfigDict(from_attributes=True)


class ActivityUpdate(BaseModel):
    title: str | None = None
    description: str | None = None
    category_id: int | None = None
    status: Status | None = None
    reflection_text: str | None = None
    time_spent_minutes: int | None = None
    deadline: datetime | None = None
    tags: list[str] | None = None


class ActivityCreate(BaseModel):
    category_id: int | None = None
    parent_id: int | None = None
    title: str
    description: str | None = None
    status: Status = Status.backlog
    is_project: bool = False
    is_quick_capture: bool = False
    deadline: datetime | None = None
    tags: list[str] = Field(default_factory=list)
