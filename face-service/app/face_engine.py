import base64
import binascii
import re
from dataclasses import dataclass

import cv2
import numpy as np
from insightface.app import FaceAnalysis

from app.config import Settings


DATA_URL_PATTERN = re.compile(r"^data:image/(jpeg|jpg|png|webp);base64,", re.IGNORECASE)


class FaceEngineError(Exception):
    def __init__(self, code: str, message: str, faces_detected: int | None = None) -> None:
        super().__init__(message)
        self.code = code
        self.message = message
        self.faces_detected = faces_detected


@dataclass(frozen=True)
class VerificationResult:
    matched: bool
    distance: float
    threshold: float
    faces_detected: int


class FaceEngine:
    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self.analysis: FaceAnalysis | None = None

    @property
    def is_ready(self) -> bool:
        return self.analysis is not None

    def load(self) -> None:
        analysis = FaceAnalysis(
            name=self.settings.model_name,
            root=self.settings.model_dir,
            providers=["CPUExecutionProvider"],
        )
        analysis.prepare(ctx_id=-1, det_size=(640, 640))
        self.analysis = analysis

    def enroll(self, image_data: str) -> list[float]:
        return self._extract_embedding(image_data).tolist()

    def verify(self, image_data: str, enrolled_embedding: list[float]) -> VerificationResult:
        candidate = self._extract_embedding(image_data)
        enrolled = np.asarray(enrolled_embedding, dtype=np.float32)

        if enrolled.shape != candidate.shape:
            raise FaceEngineError("invalid_embedding", "Dimensi template wajah tidak sesuai model.")

        norm_product = float(np.linalg.norm(candidate) * np.linalg.norm(enrolled))
        if norm_product == 0:
            raise FaceEngineError("invalid_embedding", "Template wajah tidak valid.")

        similarity = float(np.dot(candidate, enrolled) / norm_product)
        distance = max(0.0, min(2.0, 1.0 - similarity))

        return VerificationResult(
            matched=distance <= self.settings.match_threshold,
            distance=distance,
            threshold=self.settings.match_threshold,
            faces_detected=1,
        )

    def _extract_embedding(self, image_data: str) -> np.ndarray:
        if self.analysis is None:
            raise FaceEngineError("model_unavailable", "Model pengenal wajah belum siap.")

        image = self._decode_image(image_data)
        faces = self.analysis.get(image)

        if len(faces) == 0:
            raise FaceEngineError("face_not_found", "Wajah tidak terdeteksi.", 0)
        if len(faces) > 1:
            raise FaceEngineError("multiple_faces", "Terdeteksi lebih dari satu wajah.", len(faces))

        face = faces[0]
        x1, y1, x2, y2 = face.bbox
        frame_area = image.shape[0] * image.shape[1]
        face_area = max(0.0, float(x2 - x1)) * max(0.0, float(y2 - y1))
        if frame_area <= 0 or face_area / frame_area < self.settings.min_face_area_ratio:
            raise FaceEngineError("face_too_small", "Wajah terlalu jauh dari kamera.", 1)

        embedding = np.asarray(face.normed_embedding, dtype=np.float32)
        if embedding.ndim != 1 or embedding.size == 0 or not np.isfinite(embedding).all():
            raise FaceEngineError("invalid_embedding", "Model menghasilkan template wajah tidak valid.")

        return embedding

    def _decode_image(self, image_data: str) -> np.ndarray:
        match = DATA_URL_PATTERN.match(image_data)
        if not match:
            raise FaceEngineError("invalid_image", "Format gambar tidak didukung.")

        try:
            binary = base64.b64decode(image_data[match.end():], validate=True)
        except (binascii.Error, ValueError) as error:
            raise FaceEngineError("invalid_image", "Data gambar tidak valid.") from error

        if not binary or len(binary) > self.settings.max_image_bytes:
            raise FaceEngineError("invalid_image", "Ukuran gambar tidak valid atau melebihi batas.")

        image = cv2.imdecode(np.frombuffer(binary, dtype=np.uint8), cv2.IMREAD_COLOR)
        if image is None or image.size == 0:
            raise FaceEngineError("invalid_image", "Gambar tidak dapat dibaca.")

        return image
