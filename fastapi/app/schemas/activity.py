from datetime import datetime, timezone

from pydantic import BaseModel, ConfigDict, Field, field_serializer

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
    is_productive: bool
    deadline: datetime | None
    tags: list[str] | None = None
    completed_at: datetime | None
    created_at: datetime
    updated_at: datetime
    subtasks_total: int = 0
    subtasks_done: int = 0
    parent_title: str | None = None

    category: CategoryOut | None
    model_config = ConfigDict(from_attributes=True)

    @field_serializer("completed_at", "created_at", "updated_at", when_used="json")
    def _utc_marker(self, dt: datetime | None) -> str | None:
        if dt is None:
            return None
        if dt.tzinfo is None:
            dt = dt.replace(tzinfo=timezone.utc)
        return dt.isoformat()


class ActivityUpdate(BaseModel):
    title: str | None = None
    description: str | None = None
    category_id: int | None = None
    status: Status | None = None
    reflection_text: str | None = None
    time_spent_minutes: int | None = None
    deadline: datetime | None = None
    tags: list[str] | None = None
    is_on_board: bool | None = None
    is_productive: bool | None = None


class ActivityCreate(BaseModel):
    category_id: int | None = None
    parent_id: int | None = None
    title: str
    description: str | None = None
    reflection_text: str | None = None
    time_spent_minutes: int | None = None
    status: Status = Status.backlog
    is_project: bool = False
    is_quick_capture: bool = False
    is_productive: bool = True
    deadline: datetime | None = None
    tags: list[str] = Field(default_factory=list)
