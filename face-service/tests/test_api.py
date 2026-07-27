from fastapi.testclient import TestClient

from app.config import Settings
from app.face_engine import FaceEngineError, VerificationResult
from app.main import create_app


class FakeEngine:
    is_ready = True

    def load(self) -> None:
        self.is_ready = True

    def enroll(self, image_data: str) -> list[float]:
        if image_data.endswith("invalid"):
            raise FaceEngineError("face_not_found", "Wajah tidak terdeteksi.", 0)
        return [0.1, 0.2, 0.3]

    def verify(self, image_data: str, enrolled_embedding: list[float]) -> VerificationResult:
        return VerificationResult(
            matched=image_data.endswith("match"),
            distance=0.2 if image_data.endswith("match") else 0.8,
            threshold=0.45,
            faces_detected=1,
        )


settings = Settings(api_key="test-secret", model_version="test-model")
client = TestClient(create_app(settings=settings, engine=FakeEngine()))  # type: ignore[arg-type]


def test_health_reports_loaded_model() -> None:
    response = client.get("/health")

    assert response.status_code == 200
    assert response.json() == {"status": "ok", "model_loaded": True, "model_version": "test-model"}


def test_processing_endpoint_requires_api_key() -> None:
    response = client.post("/api/v1/faces/enroll", json={"image": "data:image/jpeg;base64," + "x" * 32})

    assert response.status_code == 401


def test_enroll_returns_embedding() -> None:
    response = client.post(
        "/api/v1/faces/enroll",
        headers={"X-API-Key": "test-secret"},
        json={"image": "data:image/jpeg;base64," + "x" * 32},
    )

    assert response.status_code == 200
    assert response.json() == {"embedding": [0.1, 0.2, 0.3], "dimensions": 3, "model_version": "test-model"}


def test_verify_rejects_different_model() -> None:
    response = client.post(
        "/api/v1/faces/verify",
        headers={"X-API-Key": "test-secret"},
        json={"image": "data:image/jpeg;base64," + "x" * 32, "embedding": [0.1, 0.2, 0.3], "model_version": "old-model"},
    )

    assert response.status_code == 422
    assert response.json()["detail"]["code"] == "model_mismatch"


def test_verify_returns_server_side_decision() -> None:
    response = client.post(
        "/api/v1/faces/verify",
        headers={"X-API-Key": "test-secret"},
        json={"image": "data:image/jpeg;base64," + "x" * 27 + "match", "embedding": [0.1, 0.2, 0.3], "model_version": "test-model"},
    )

    assert response.status_code == 200
    assert response.json()["matched"] is True
    assert response.json()["distance"] == 0.2
