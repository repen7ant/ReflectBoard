from fastapi import APIRouter

router = APIRouter(prefix="/api/v1")


@router.get("/activities")
def get_activities():
    return "activities"
