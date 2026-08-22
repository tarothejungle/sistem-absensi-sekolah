from functools import lru_cache

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    api_key: str
    model_name: str = "buffalo_l"
    model_version: str = "insightface-buffalo_l-v1"
    model_dir: str = "/models"
    match_threshold: float = 0.40
    max_image_bytes: int = 2_097_152
    # Laptop-camera baseline: tolerate lower resolution while retaining structural checks.
    min_face_area_ratio: float = 0.10
    min_detection_score: float = 0.75
    min_embedding_norm: float = 9.0
    face_frame_margin_ratio: float = 0.025
    landmark_bbox_padding_ratio: float = 0.06
    attendance_max_yaw_degrees: float = 18.0
    attendance_max_roll_degrees: float = 12.0
    min_blur_variance: float = 35.0
    min_edge_sharpness: float = 8.0
    min_brightness: float = 45.0
    max_brightness: float = 215.0
    max_dark_pixel_ratio: float = 0.40
    max_bright_pixel_ratio: float = 0.40
    min_contrast: float = 20.0
    min_region_contrast: float = 8.0
    min_region_blur_variance: float = 12.0
    min_region_edge_sharpness: float = 3.0
    visibility_model_name: str = "landmark_2d_106"
    min_visibility_score: float = 0.78
    min_landmark_inlier_ratio: float = 0.98
    min_landmark_edge_support: float = 0.20
    max_request_bytes: int = 6_000_000
    rate_limit_requests: int = 30
    rate_limit_window_seconds: int = 60
    enable_docs: bool = False
    liveness_method: str = "active_head_pose_v1"
    liveness_center_max_yaw_degrees: float = 25.0
    liveness_center_max_delta_degrees: float = 12.0
    liveness_turn_min_yaw_degrees: float = 18.0
    liveness_same_face_max_distance: float = 0.45
    liveness_turn_same_face_max_distance: float = 0.55
    liveness_min_hash_distance: int = 4
    legacy_face_endpoints_enabled: bool = False


@lru_cache
def get_settings() -> Settings:
    return Settings()  # type: ignore[call-arg]
