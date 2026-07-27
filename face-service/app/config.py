from functools import lru_cache

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    api_key: str
    model_name: str = "buffalo_l"
    model_version: str = "insightface-buffalo_l-v1"
    model_dir: str = "/models"
    match_threshold: float = 0.45
    max_image_bytes: int = 2_097_152
    min_face_area_ratio: float = 0.05


@lru_cache
def get_settings() -> Settings:
    return Settings()  # type: ignore[call-arg]
