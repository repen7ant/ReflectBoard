from fastapi import APIRouter

router = APIRouter()


@router.get("/activities")
def get_activities():
    return "activities"
