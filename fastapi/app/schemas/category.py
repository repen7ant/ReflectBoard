from pydantic import BaseModel, ConfigDict


class CategoryOut(BaseModel):
    id: int
    name: str
    color: str

    model_config = ConfigDict(from_attributes=True)


class CategoryCreate(BaseModel):
    user_id: int
    name: str
    color: str
