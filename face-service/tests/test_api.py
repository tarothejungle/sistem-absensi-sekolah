from fastapi.testclient import TestClient

from app.config import Settings
from app.face_engine import (
    FaceEngineError,
    LivenessResult,
    LivenessVerificationResult,
    VerificationResult,
)
from app.main import create_app


VALID_EMBEDDING = [0.1] * 512
CHALLENGE_ID = "11111111-1111-4111-8111-111111111111"


class FakeEngine:
    is_ready = True
    is_quality_ready = True

    def load(self) -> None:
        self.is_ready = True

    def enroll(self, image_data: str) -> list[float]:
        return VALID_EMBEDDING

    def verify(self, image_data: str, enrolled_embedding: list[float]) -> VerificationResult:
        if image_data.endswith("covered"):
            raise FaceEngineError(
                "face_occluded",
                "Mata, hidung, dan mulut harus terlihat jelas tanpa tertutup benda.",
                1,
            )
        return VerificationResult(matched=True, distance=0.2, threshold=0.45, faces_detected=1)

    def enroll_liveness(self, frames: list[tuple[str, str]]) -> LivenessResult:
        if frames[1][1].endswith("invalid"):
            raise FaceEngineError("liveness_turn_left", "Gerakan menoleh ke kiri belum terdeteksi.", 1)
        return LivenessResult(embedding=VALID_EMBEDDING, audit_frame_index=3)

    def verify_liveness(
        self,
        frames: list[tuple[str, str]],
        enrolled_embedding: list[float],
    ) -> LivenessVerificationResult:
        matched = frames[-1][1].endswith("match")
        return LivenessVerificationResult(
            matched=matched,
            distance=0.2 if matched else 0.8,
            threshold=0.45,
            faces_detected=1,
            audit_frame_index=3,
        )


settings = Settings(api_key="test-secret", model_version="test-model")
client = TestClient(create_app(settings=settings, engine=FakeEngine()))  # type: ignore[arg-type]


def liveness_frames(last_suffix: str = "x") -> list[dict[str, str]]:
    return [
        {"step": "center", "image": "data:image/jpeg;base64," + "a" * 32},
        {"step": "turn_left", "image": "data:image/jpeg;base64," + "b" * 32},
        {"step": "turn_right", "image": "data:image/jpeg;base64," + "c" * 32},
        {"step": "center", "image": "data:image/jpeg;base64," + "d" * 31 + last_suffix},
    ]


def liveness_payload(last_suffix: str = "x") -> dict[str, object]:
    return {
        "challenge_id": CHALLENGE_ID,
        "method": "active_head_pose_v1",
        "frames": liveness_frames(last_suffix),
    }


def test_health_reports_loaded_model_and_liveness() -> None:
    response = client.get("/health")

    assert response.status_code == 200
    assert response.json() == {
        "status": "ok",
        "model_loaded": True,
        "model_version": "test-model",
        "liveness_ready": True,
        "liveness_method": "active_head_pose_v1",
        "quality_ready": True,
        "quality_method": "landmark_2d_106",
    }


def test_processing_endpoint_requires_api_key() -> None:
    response = client.post("/api/v1/faces/liveness/enroll", json=liveness_payload())

    assert response.status_code == 401


def test_legacy_enrollment_endpoint_is_disabled_but_face_verification_is_available() -> None:
    headers = {"X-API-Key": "test-secret"}
    enroll = client.post(
        "/api/v1/faces/enroll",
        headers=headers,
        json={"image": "data:image/jpeg;base64," + "x" * 32},
    )
    verify = client.post(
        "/api/v1/faces/verify",
        headers=headers,
        json={"image": "data:image/jpeg;base64," + "x" * 32, "embedding": VALID_EMBEDDING, "model_version": "test-model"},
    )

    assert enroll.status_code == 410
    assert verify.status_code == 200
    assert verify.json()["matched"] is True
    assert verify.json()["model_version"] == "test-model"


def test_face_verification_returns_structured_quality_rejection() -> None:
    response = client.post(
        "/api/v1/faces/verify",
        headers={"X-API-Key": "test-secret"},
        json={
            "image": "data:image/jpeg;base64," + "x" * 32 + "covered",
            "embedding": VALID_EMBEDDING,
            "model_version": "test-model",
        },
    )

    assert response.status_code == 422
    assert response.json()["detail"] == {
        "code": "face_occluded",
        "message": "Mata, hidung, dan mulut harus terlihat jelas tanpa tertutup benda.",
        "faces_detected": 1,
    }


def test_liveness_enroll_returns_embedding() -> None:
    response = client.post(
        "/api/v1/faces/liveness/enroll",
        headers={"X-API-Key": "test-secret"},
        json=liveness_payload(),
    )

    assert response.status_code == 200
    assert response.json() == {
        "embedding": VALID_EMBEDDING,
        "dimensions": 512,
        "model_version": "test-model",
        "audit_frame_index": 3,
        "liveness": {"passed": True, "method": "active_head_pose_v1", "reason": None},
    }


def test_liveness_rejects_invalid_action_sequence() -> None:
    payload = liveness_payload()
    payload["frames"][1]["step"] = "center"  # type: ignore[index]

    response = client.post(
        "/api/v1/faces/liveness/enroll",
        headers={"X-API-Key": "test-secret"},
        json=payload,
    )

    assert response.status_code == 422


def test_liveness_verify_rejects_different_model() -> None:
    payload = liveness_payload("match")
    payload.update({"embedding": VALID_EMBEDDING, "model_version": "old-model"})

    response = client.post(
        "/api/v1/faces/liveness/verify",
        headers={"X-API-Key": "test-secret"},
        json=payload,
    )

    assert response.status_code == 422
    assert response.json()["detail"]["code"] == "model_mismatch"


def test_liveness_verify_returns_server_side_decision() -> None:
    payload = liveness_payload("match")
    payload.update({"embedding": VALID_EMBEDDING, "model_version": "test-model"})

    response = client.post(
        "/api/v1/faces/liveness/verify",
        headers={"X-API-Key": "test-secret"},
        json=payload,
    )

    assert response.status_code == 200
    assert response.json()["matched"] is True
    assert response.json()["distance"] == 0.2
    assert response.json()["liveness"]["passed"] is True


def test_liveness_verify_rejects_wrong_embedding_dimensions() -> None:
    payload = liveness_payload()
    payload.update({"embedding": [0.1] * 513, "model_version": "test-model"})

    response = client.post(
        "/api/v1/faces/liveness/verify",
        headers={"X-API-Key": "test-secret"},
        json=payload,
    )

    assert response.status_code == 422


def test_liveness_endpoint_is_rate_limited() -> None:
    limited_settings = Settings(
        api_key="test-secret",
        model_version="test-model",
        rate_limit_requests=2,
        rate_limit_window_seconds=60,
    )
    limited_client = TestClient(create_app(settings=limited_settings, engine=FakeEngine()))  # type: ignore[arg-type]
    payload = liveness_payload()
    headers = {"X-API-Key": "test-secret"}

    assert limited_client.post("/api/v1/faces/liveness/enroll", headers=headers, json=payload).status_code == 200
    assert limited_client.post("/api/v1/faces/liveness/enroll", headers=headers, json=payload).status_code == 200
    assert limited_client.post("/api/v1/faces/liveness/enroll", headers=headers, json=payload).status_code == 429
