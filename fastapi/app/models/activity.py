from __future__ import annotations

import enum
from datetime import datetime
from typing import TYPE_CHECKING

from sqlalchemy import (
    JSON,
    Boolean,
    DateTime,
    Enum,
    ForeignKey,
    Integer,
    String,
    Text,
    func,
)
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.db.session import Base

if TYPE_CHECKING:
    from app.models.category import Category
    from app.models.user import User


class Status(str, enum.Enum):
    backlog = "backlog"
    today = "today"
    in_process = "in_process"
    on_reflection = "on_reflection"
    done = "done"


class Activity(Base):
    __tablename__ = "activities"

    id: Mapped[int] = mapped_column(primary_key=True, autoincrement=True, index=True)
    user_id: Mapped[int] = mapped_column(ForeignKey("users.id"), nullable=False)
    parent_id: Mapped[int | None] = mapped_column(
        ForeignKey("activities.id"), nullable=True
    )
    category_id: Mapped[int | None] = mapped_column(
        ForeignKey("categories.id"), nullable=True
    )
    category_snapshot_name = mapped_column(String, nullable=True)
    category_snapshot_color = mapped_column(String, nullable=True)

    title: Mapped[str] = mapped_column(String(255), nullable=False)
    description: Mapped[str | None] = mapped_column(Text, nullable=True)
    reflection_text: Mapped[str | None] = mapped_column(Text, nullable=True)
    time_spent_minutes: Mapped[int | None] = mapped_column(Integer, nullable=True)

    status: Mapped[Status] = mapped_column(
        Enum(Status), default=Status.backlog, nullable=False
    )

    is_project: Mapped[bool] = mapped_column(Boolean, default=False)
    is_on_board: Mapped[bool] = mapped_column(Boolean, default=False)
    is_quick_capture: Mapped[bool] = mapped_column(Boolean, default=False)

    deadline: Mapped[datetime | None] = mapped_column(DateTime, nullable=True)
    tags: Mapped[list | None] = mapped_column(JSON, nullable=True, default=list)
    completed_at: Mapped[datetime | None] = mapped_column(DateTime, nullable=True)

    created_at: Mapped[datetime] = mapped_column(DateTime, server_default=func.now())
    updated_at: Mapped[datetime] = mapped_column(
        DateTime, server_default=func.now(), onupdate=func.now()
    )

    user: Mapped["User"] = relationship(back_populates="activities")
    parent: Mapped["Activity | None"] = relationship(
        back_populates="children", remote_side="Activity.id"
    )
    children: Mapped[list["Activity"]] = relationship(back_populates="parent")
    category: Mapped["Category | None"] = relationship(back_populates="activities")
    position: Mapped[int] = mapped_column(Integer, default=0)
