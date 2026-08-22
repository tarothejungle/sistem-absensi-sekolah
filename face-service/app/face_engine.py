import base64
import binascii
import logging
import re
from dataclasses import dataclass
from typing import Any

import cv2
import numpy as np

from app.config import Settings


DATA_URL_PATTERN = re.compile(r"^data:image/(jpeg|jpg|png|webp);base64,", re.IGNORECASE)
logger = logging.getLogger(__name__)


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


@dataclass(frozen=True)
class FaceObservation:
    embedding: np.ndarray
    yaw: float
    image_hash: np.ndarray


@dataclass(frozen=True)
class LivenessResult:
    embedding: list[float]
    audit_frame_index: int


@dataclass(frozen=True)
class LivenessVerificationResult:
    matched: bool
    distance: float
    threshold: float
    faces_detected: int
    audit_frame_index: int


class FaceEngine:
    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self.analysis: Any | None = None

    @property
    def is_ready(self) -> bool:
        return self.analysis is not None

    @property
    def is_quality_ready(self) -> bool:
        if self.analysis is None:
            return False
        models = getattr(self.analysis, "models", {})
        return self.settings.visibility_model_name in models

    def load(self) -> None:
        try:
            from insightface.app import FaceAnalysis
        except ImportError as exception:
            raise FaceEngineError(
                "model_dependency_missing",
                "Dependency InsightFace belum tersedia pada service wajah.",
            ) from exception

        analysis = FaceAnalysis(
            name=self.settings.model_name,
            root=self.settings.model_dir,
            providers=["CPUExecutionProvider"],
        )
        analysis.prepare(ctx_id=-1, det_size=(640, 640))
        if self.settings.visibility_model_name not in getattr(analysis, "models", {}):
            raise FaceEngineError(
                "quality_model_unavailable",
                "Model pemeriksaan visibilitas wajah belum tersedia.",
            )
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

    def enroll_liveness(self, frames: list[tuple[str, str]]) -> LivenessResult:
        observations = [self._extract_observation(image) for _, image in frames]
        embedding = self._validate_liveness(frames, observations)

        return LivenessResult(embedding=embedding.tolist(), audit_frame_index=len(frames) - 1)

    def verify_liveness(
        self,
        frames: list[tuple[str, str]],
        enrolled_embedding: list[float],
    ) -> LivenessVerificationResult:
        observations = [self._extract_observation(image) for _, image in frames]
        candidate = self._validate_liveness(frames, observations)
        enrolled = np.asarray(enrolled_embedding, dtype=np.float32)

        if enrolled.shape != candidate.shape:
            raise FaceEngineError("invalid_embedding", "Dimensi template wajah tidak sesuai model.")

        distance = self._embedding_distance(candidate, enrolled)

        return LivenessVerificationResult(
            matched=distance <= self.settings.match_threshold,
            distance=distance,
            threshold=self.settings.match_threshold,
            faces_detected=1,
            audit_frame_index=len(frames) - 1,
        )

    def _validate_liveness(
        self,
        frames: list[tuple[str, str]],
        observations: list[FaceObservation],
    ) -> np.ndarray:
        if len(frames) != 4 or len(observations) != 4:
            raise FaceEngineError("liveness_frame_count", "Liveness membutuhkan tepat empat frame.")

        steps = [step for step, _ in frames]
        valid_sequences = [
            ["center", "turn_left", "turn_right", "center"],
            ["center", "turn_right", "turn_left", "center"],
        ]
        if steps not in valid_sequences:
            raise FaceEngineError("liveness_action_order", "Urutan gerakan liveness tidak valid.")

        for index in range(1, len(observations)):
            hash_distance = int(np.count_nonzero(observations[index - 1].image_hash != observations[index].image_hash))
            if hash_distance < self.settings.liveness_min_hash_distance:
                raise FaceEngineError("liveness_frame_reused", "Frame liveness terdeteksi berulang.")

        first_center_embedding = observations[0].embedding
        final_center_embedding = observations[-1].embedding
        center_identity_distance = self._embedding_distance(first_center_embedding, final_center_embedding)
        turn_identity_distances = [
            min(
                self._embedding_distance(first_center_embedding, observation.embedding),
                self._embedding_distance(final_center_embedding, observation.embedding),
            )
            for observation in observations[1:-1]
        ]

        if center_identity_distance > self.settings.liveness_same_face_max_distance:
            self._log_identity_rejection(
                "liveness_center_identity_changed",
                center_identity_distance,
                turn_identity_distances,
            )
            raise FaceEngineError(
                "liveness_identity_changed",
                "Wajah pada langkah akhir tidak konsisten dengan langkah awal.",
            )

        if any(
            distance > self.settings.liveness_turn_same_face_max_distance
            for distance in turn_identity_distances
        ):
            self._log_identity_rejection(
                "liveness_turn_identity_changed",
                center_identity_distance,
                turn_identity_distances,
            )
            raise FaceEngineError(
                "liveness_identity_changed",
                "Wajah tidak konsisten selama gerakan liveness.",
            )

        yaws = [observation.yaw for observation in observations]
        center_yaws = [yaws[0], yaws[-1]]
        center_reference = float(np.mean(center_yaws))

        if any(abs(yaw) > self.settings.liveness_center_max_yaw_degrees for yaw in center_yaws):
            self._log_pose_rejection("liveness_center_pose", steps, yaws, center_reference)
            raise FaceEngineError(
                "liveness_center_pose",
                "Hadapkan wajah lurus ke kamera pada langkah awal dan akhir.",
            )

        if abs(center_yaws[0] - center_yaws[1]) > self.settings.liveness_center_max_delta_degrees:
            self._log_pose_rejection("liveness_center_inconsistent", steps, yaws, center_reference)
            raise FaceEngineError(
                "liveness_center_pose",
                "Kembalikan wajah ke posisi lurus seperti pada langkah pertama.",
            )

        for step, observation in zip(steps, observations):
            if (
                step == "turn_left"
                and observation.yaw > center_reference - self.settings.liveness_turn_min_yaw_degrees
            ):
                self._log_pose_rejection("liveness_turn_left", steps, yaws, center_reference)
                raise FaceEngineError("liveness_turn_left", "Gerakan menoleh ke kiri belum terdeteksi.")
            if (
                step == "turn_right"
                and observation.yaw < center_reference + self.settings.liveness_turn_min_yaw_degrees
            ):
                self._log_pose_rejection("liveness_turn_right", steps, yaws, center_reference)
                raise FaceEngineError("liveness_turn_right", "Gerakan menoleh ke kanan belum terdeteksi.")

        center_embeddings = np.stack([observations[0].embedding, observations[-1].embedding])
        averaged = np.mean(center_embeddings, axis=0).astype(np.float32)
        norm = float(np.linalg.norm(averaged))
        if norm == 0 or not np.isfinite(averaged).all():
            raise FaceEngineError("invalid_embedding", "Template liveness tidak valid.")

        return averaged / norm

    def _log_pose_rejection(
        self,
        code: str,
        steps: list[str],
        yaws: list[float],
        center_reference: float,
    ) -> None:
        logger.warning(
            "Liveness pose rejected: code=%s steps=%s yaws=%s center_reference=%.2f",
            code,
            steps,
            [round(yaw, 2) for yaw in yaws],
            center_reference,
        )

    def _log_identity_rejection(
        self,
        code: str,
        center_distance: float,
        turn_distances: list[float],
    ) -> None:
        logger.warning(
            "Liveness identity rejected: code=%s center_distance=%.4f turn_distances=%s "
            "center_threshold=%.2f turn_threshold=%.2f",
            code,
            center_distance,
            [round(distance, 4) for distance in turn_distances],
            self.settings.liveness_same_face_max_distance,
            self.settings.liveness_turn_same_face_max_distance,
        )

    def _extract_observation(self, image_data: str) -> FaceObservation:
        face, image, yaw = self._extract_face(image_data, require_frontal=False)
        embedding = self._normalized_embedding(face)
        image_hash = self._average_hash(image)

        return FaceObservation(embedding=embedding, yaw=yaw, image_hash=image_hash)

    def _extract_embedding(self, image_data: str) -> np.ndarray:
        face, _, _ = self._extract_face(image_data, require_frontal=True)
        return self._normalized_embedding(face)

    def _extract_face(
        self,
        image_data: str,
        require_frontal: bool,
    ) -> tuple[Any, np.ndarray, float]:
        if self.analysis is None:
            raise FaceEngineError("model_unavailable", "Model pengenal wajah belum siap.")

        image = self._decode_image(image_data)
        faces = self.analysis.get(image)

        if len(faces) == 0:
            raise FaceEngineError("face_not_found", "Wajah tidak terdeteksi.", 0)
        if len(faces) > 1:
            raise FaceEngineError("multiple_faces", "Terdeteksi lebih dari satu wajah.", len(faces))

        face = faces[0]
        yaw = self._assert_face_quality(face, image, require_frontal)

        return face, image, yaw

    def _assert_face_quality(self, face: Any, image: np.ndarray, require_frontal: bool) -> float:
        detection_score = float(getattr(face, "det_score", 0.0))
        if not np.isfinite(detection_score) or detection_score < self.settings.min_detection_score:
            raise FaceEngineError(
                "face_low_quality",
                "Wajah belum terlihat cukup jelas oleh kamera.",
                1,
            )

        embedding_norm = float(getattr(face, "embedding_norm", 0.0))
        if not np.isfinite(embedding_norm) or embedding_norm < self.settings.min_embedding_norm:
            raise FaceEngineError(
                "face_low_quality",
                "Model belum memperoleh detail identitas wajah yang cukup jelas.",
                1,
            )

        bbox = self._face_bbox(face)
        self._assert_face_size(face, image)
        self._assert_face_inside_frame(bbox, image)

        keypoints = self._face_keypoints(face, bbox)
        self._assert_landmark_geometry(keypoints, bbox, require_frontal)
        self._assert_crop_quality(self._face_crop(image, bbox))
        self._assert_face_visibility(face, image, bbox, keypoints)

        yaw = self._estimate_yaw(face, image)
        if require_frontal and abs(yaw) > self.settings.attendance_max_yaw_degrees:
            raise FaceEngineError(
                "face_pose_invalid",
                "Hadapkan wajah lurus ke kamera saat melakukan absensi.",
                1,
            )

        return yaw

    def _face_bbox(self, face: Any) -> np.ndarray:
        bbox = np.asarray(getattr(face, "bbox", None), dtype=np.float64)
        if bbox.shape != (4,) or not np.isfinite(bbox).all():
            raise FaceEngineError("face_low_quality", "Area wajah tidak dapat dianalisis.", 1)
        if bbox[2] <= bbox[0] or bbox[3] <= bbox[1]:
            raise FaceEngineError("face_low_quality", "Area wajah tidak valid.", 1)
        return bbox

    def _assert_face_inside_frame(self, bbox: np.ndarray, image: np.ndarray) -> None:
        height, width = image.shape[:2]
        margin_x = width * self.settings.face_frame_margin_ratio
        margin_y = height * self.settings.face_frame_margin_ratio
        x1, y1, x2, y2 = bbox
        if x1 < margin_x or y1 < margin_y or x2 > width - margin_x or y2 > height - margin_y:
            raise FaceEngineError(
                "face_partial",
                "Pastikan seluruh wajah terlihat dan tidak terpotong batas kamera.",
                1,
            )

    def _face_keypoints(self, face: Any, bbox: np.ndarray) -> np.ndarray:
        keypoints = np.asarray(getattr(face, "kps", None), dtype=np.float64)
        if keypoints.shape != (5, 2) or not np.isfinite(keypoints).all():
            raise FaceEngineError(
                "face_visibility_low",
                "Bagian penting wajah belum terlihat lengkap.",
                1,
            )

        x1, y1, x2, y2 = bbox
        if not (
            np.all((keypoints[:, 0] >= x1) & (keypoints[:, 0] <= x2))
            and np.all((keypoints[:, 1] >= y1) & (keypoints[:, 1] <= y2))
        ):
            raise FaceEngineError(
                "face_partial",
                "Pastikan mata, hidung, dan mulut terlihat di dalam kamera.",
                1,
            )
        return keypoints

    def _assert_landmark_geometry(
        self,
        keypoints: np.ndarray,
        bbox: np.ndarray,
        require_frontal: bool,
    ) -> None:
        left_eye, right_eye, nose, left_mouth, right_mouth = keypoints
        width = float(bbox[2] - bbox[0])
        height = float(bbox[3] - bbox[1])
        eye_distance = float(np.linalg.norm(right_eye - left_eye))
        mouth_distance = float(np.linalg.norm(right_mouth - left_mouth))
        eye_y = float(np.mean(keypoints[:2, 1]))
        mouth_y = float(np.mean(keypoints[3:, 1]))

        geometry_valid = (
            left_eye[0] < right_eye[0]
            and left_mouth[0] < right_mouth[0]
            and eye_y < nose[1] < mouth_y
            and 0.24 <= eye_distance / width <= 0.62
            and 0.16 <= mouth_distance / width <= 0.55
            and 0.24 <= (mouth_y - eye_y) / height <= 0.62
        )
        if not geometry_valid:
            raise FaceEngineError(
                "face_visibility_low",
                "Mata, hidung, dan mulut harus terlihat jelas dan lengkap.",
                1,
            )

        roll = abs(float(np.degrees(np.arctan2(
            right_eye[1] - left_eye[1],
            right_eye[0] - left_eye[0],
        ))))
        roll_limit = self.settings.attendance_max_roll_degrees if require_frontal else 18.0
        if roll > roll_limit:
            raise FaceEngineError(
                "face_pose_invalid",
                "Tegakkan kepala dan hadapkan wajah ke kamera.",
                1,
            )

        if require_frontal:
            eye_center_x = float(np.mean(keypoints[:2, 0]))
            if abs(float(nose[0]) - eye_center_x) > eye_distance * 0.34:
                raise FaceEngineError(
                    "face_pose_invalid",
                    "Hadapkan wajah lurus ke kamera saat melakukan absensi.",
                    1,
                )

    def _face_crop(self, image: np.ndarray, bbox: np.ndarray) -> np.ndarray:
        height, width = image.shape[:2]
        x1 = max(0, int(np.floor(bbox[0])))
        y1 = max(0, int(np.floor(bbox[1])))
        x2 = min(width, int(np.ceil(bbox[2])))
        y2 = min(height, int(np.ceil(bbox[3])))
        crop = image[y1:y2, x1:x2]
        if crop.size == 0:
            raise FaceEngineError("face_low_quality", "Area wajah tidak dapat dibaca.", 1)
        return crop

    def _assert_crop_quality(self, crop: np.ndarray) -> None:
        grayscale = cv2.cvtColor(crop, cv2.COLOR_BGR2GRAY)
        brightness = float(np.mean(grayscale))
        dark_ratio = float(np.mean(grayscale <= 25))
        bright_ratio = float(np.mean(grayscale >= 235))

        if brightness < self.settings.min_brightness or dark_ratio > self.settings.max_dark_pixel_ratio:
            raise FaceEngineError(
                "face_too_dark",
                "Wajah terlalu gelap. Cari pencahayaan dari arah depan.",
                1,
            )
        if brightness > self.settings.max_brightness or bright_ratio > self.settings.max_bright_pixel_ratio:
            raise FaceEngineError(
                "face_overexposed",
                "Wajah terlalu terang. Hindari cahaya kuat tepat di belakang atau depan kamera.",
                1,
            )

        if float(np.std(grayscale)) < self.settings.min_contrast:
            raise FaceEngineError(
                "face_low_quality",
                "Detail wajah belum terlihat jelas. Perbaiki pencahayaan lalu coba lagi.",
                1,
            )

        blur_variance = float(cv2.Laplacian(grayscale, cv2.CV_64F).var())
        gradient_x = cv2.Sobel(grayscale, cv2.CV_32F, 1, 0, ksize=3)
        gradient_y = cv2.Sobel(grayscale, cv2.CV_32F, 0, 1, ksize=3)
        edge_sharpness = float(cv2.magnitude(gradient_x, gradient_y).mean())
        if (
            blur_variance < self.settings.min_blur_variance
            and edge_sharpness < self.settings.min_edge_sharpness
        ):
            raise FaceEngineError(
                "face_blurry",
                "Foto wajah buram. Tahan perangkat dan wajah tetap stabil.",
                1,
            )

    def _assert_face_visibility(
        self,
        face: Any,
        image: np.ndarray,
        bbox: np.ndarray,
        keypoints: np.ndarray,
    ) -> None:
        landmarks = np.asarray(
            getattr(face, self.settings.visibility_model_name, None),
            dtype=np.float64,
        )
        if landmarks.shape != (106, 2) or not np.isfinite(landmarks).all():
            raise FaceEngineError(
                "face_visibility_low",
                "Model tidak dapat memastikan seluruh bagian wajah terlihat.",
                1,
            )

        x1, y1, x2, y2 = bbox
        width = float(x2 - x1)
        height = float(y2 - y1)
        padding_x = width * self.settings.landmark_bbox_padding_ratio
        padding_y = height * self.settings.landmark_bbox_padding_ratio
        landmark_x1 = x1 - padding_x
        landmark_y1 = y1 - padding_y
        landmark_x2 = x2 + padding_x
        landmark_y2 = y2 + padding_y
        landmark_width = landmark_x2 - landmark_x1
        landmark_height = landmark_y2 - landmark_y1
        inside = (
            (landmarks[:, 0] >= landmark_x1)
            & (landmarks[:, 0] <= landmark_x2)
            & (landmarks[:, 1] >= landmark_y1)
            & (landmarks[:, 1] <= landmark_y2)
        )
        inlier_ratio = float(np.mean(inside))
        if inlier_ratio < self.settings.min_landmark_inlier_ratio:
            raise FaceEngineError(
                "face_partial",
                "Seluruh bentuk wajah harus terlihat dan tidak terpotong.",
                1,
            )

        visible_landmarks = landmarks[inside]
        x_span = float(np.ptp(visible_landmarks[:, 0])) / landmark_width
        y_span = float(np.ptp(visible_landmarks[:, 1])) / landmark_height
        hull = cv2.convexHull(visible_landmarks.astype(np.float32))
        hull_ratio = float(cv2.contourArea(hull)) / (landmark_width * landmark_height)
        if x_span < 0.54 or y_span < 0.58 or hull_ratio < 0.28:
            raise FaceEngineError(
                "face_partial",
                "Seluruh sisi wajah, mata, hidung, dan mulut harus terlihat.",
                1,
            )

        visibility_bbox = np.asarray([landmark_x1, landmark_y1, landmark_x2, landmark_y2])
        crop = self._face_crop(image, visibility_bbox)
        grayscale = cv2.cvtColor(crop, cv2.COLOR_BGR2GRAY)
        gradient_x = cv2.Sobel(grayscale, cv2.CV_32F, 1, 0, ksize=3)
        gradient_y = cv2.Sobel(grayscale, cv2.CV_32F, 0, 1, ksize=3)
        gradient = cv2.magnitude(gradient_x, gradient_y)
        gradient_reference = max(1.0, float(np.percentile(gradient, 90)))
        supported = 0
        crop_height, crop_width = grayscale.shape
        for point in visible_landmarks:
            px = int(np.clip(round(point[0] - landmark_x1), 0, crop_width - 1))
            py = int(np.clip(round(point[1] - landmark_y1), 0, crop_height - 1))
            patch = gradient[max(0, py - 3):min(crop_height, py + 4), max(0, px - 3):min(crop_width, px + 4)]
            if patch.size and float(np.max(patch)) >= gradient_reference * 0.35:
                supported += 1
        edge_support = supported / len(visible_landmarks)
        if edge_support < self.settings.min_landmark_edge_support:
            raise FaceEngineError(
                "face_visibility_low",
                "Kontur wajah tidak terlihat cukup jelas. Jangan menutupi wajah.",
                1,
            )

        region_score = self._facial_region_visibility(image, bbox, keypoints)
        coverage_score = min(1.0, x_span / 0.62, y_span / 0.66, hull_ratio / 0.34)
        edge_score = min(1.0, edge_support / max(self.settings.min_landmark_edge_support, 0.01))
        visibility_score = (
            0.25 * min(1.0, inlier_ratio / self.settings.min_landmark_inlier_ratio)
            + 0.25 * coverage_score
            + 0.25 * edge_score
            + 0.25 * region_score
        )
        if visibility_score < self.settings.min_visibility_score:
            raise FaceEngineError(
                "face_visibility_low",
                "Wajah belum terlihat lengkap dan jelas. Lepaskan benda yang menutupi wajah.",
                1,
            )

    def _facial_region_visibility(
        self,
        image: np.ndarray,
        bbox: np.ndarray,
        keypoints: np.ndarray,
    ) -> float:
        width = float(bbox[2] - bbox[0])
        height = float(bbox[3] - bbox[1])
        mouth_center = np.mean(keypoints[3:], axis=0)
        regions = [
            (keypoints[0], 0.13, 0.10),
            (keypoints[1], 0.13, 0.10),
            (keypoints[2], 0.12, 0.12),
            (mouth_center, 0.18, 0.11),
        ]
        scores: list[float] = []
        image_height, image_width = image.shape[:2]
        for center, width_ratio, height_ratio in regions:
            half_width = max(4, int(width * width_ratio))
            half_height = max(4, int(height * height_ratio))
            center_x, center_y = int(round(center[0])), int(round(center[1]))
            x1 = max(0, center_x - half_width)
            y1 = max(0, center_y - half_height)
            x2 = min(image_width, center_x + half_width + 1)
            y2 = min(image_height, center_y + half_height + 1)
            patch = image[y1:y2, x1:x2]
            if patch.size == 0:
                raise FaceEngineError("face_occluded", "Bagian penting wajah tidak terlihat.", 1)

            grayscale = cv2.cvtColor(patch, cv2.COLOR_BGR2GRAY)
            contrast = float(np.std(grayscale))
            sharpness = float(cv2.Laplacian(grayscale, cv2.CV_64F).var())
            region_gradient_x = cv2.Sobel(grayscale, cv2.CV_32F, 1, 0, ksize=3)
            region_gradient_y = cv2.Sobel(grayscale, cv2.CV_32F, 0, 1, ksize=3)
            edge_sharpness = float(cv2.magnitude(region_gradient_x, region_gradient_y).mean())
            contrast_score = min(1.0, contrast / self.settings.min_region_contrast)
            sharpness_score = max(
                min(1.0, sharpness / self.settings.min_region_blur_variance),
                min(1.0, edge_sharpness / self.settings.min_region_edge_sharpness),
            )
            score = min(contrast_score, sharpness_score)
            if score < 1.0:
                raise FaceEngineError(
                    "face_occluded",
                    "Mata, hidung, dan mulut harus terlihat jelas tanpa tertutup benda.",
                    1,
                )
            scores.append(score)
        return float(min(scores))

    def _assert_face_size(self, face: Any, image: np.ndarray) -> None:
        x1, y1, x2, y2 = self._face_bbox(face)
        frame_area = image.shape[0] * image.shape[1]
        face_area = max(0.0, float(x2 - x1)) * max(0.0, float(y2 - y1))
        if frame_area <= 0 or face_area / frame_area < self.settings.min_face_area_ratio:
            raise FaceEngineError("face_too_small", "Wajah terlalu jauh dari kamera.", 1)

    def _normalized_embedding(self, face: Any) -> np.ndarray:
        embedding = np.asarray(face.normed_embedding, dtype=np.float32)
        if embedding.ndim != 1 or embedding.size == 0 or not np.isfinite(embedding).all():
            raise FaceEngineError("invalid_embedding", "Model menghasilkan template wajah tidak valid.")

        return embedding

    def _embedding_distance(self, first: np.ndarray, second: np.ndarray) -> float:
        norm_product = float(np.linalg.norm(first) * np.linalg.norm(second))
        if norm_product == 0:
            raise FaceEngineError("invalid_embedding", "Template wajah tidak valid.")
        similarity = float(np.dot(first, second) / norm_product)
        return max(0.0, min(2.0, 1.0 - similarity))

    def _estimate_yaw(self, face: Any, image: np.ndarray) -> float:
        keypoints = np.asarray(getattr(face, "kps", None), dtype=np.float64)
        if keypoints.shape != (5, 2) or not np.isfinite(keypoints).all():
            raise FaceEngineError("liveness_landmarks_missing", "Landmark wajah untuk liveness tidak tersedia.")

        model_points = np.asarray([
            [-30.0, 35.0, 30.0],
            [30.0, 35.0, 30.0],
            [0.0, 0.0, 0.0],
            [-25.0, -30.0, 30.0],
            [25.0, -30.0, 30.0],
        ], dtype=np.float64)
        focal_length = float(max(image.shape[0], image.shape[1]))
        camera_matrix = np.asarray([
            [focal_length, 0.0, image.shape[1] / 2.0],
            [0.0, focal_length, image.shape[0] / 2.0],
            [0.0, 0.0, 1.0],
        ], dtype=np.float64)
        success, rotation_vector, _ = cv2.solvePnP(
            model_points,
            keypoints,
            camera_matrix,
            np.zeros((4, 1), dtype=np.float64),
            flags=cv2.SOLVEPNP_EPNP,
        )
        if not success:
            raise FaceEngineError("liveness_pose_unavailable", "Pose wajah tidak dapat dihitung.")

        rotation_matrix, _ = cv2.Rodrigues(rotation_vector)
        camera_facing_rotation = rotation_matrix @ np.diag([1.0, -1.0, -1.0])
        yaw = -float(np.degrees(np.arctan2(
            camera_facing_rotation[0, 2],
            camera_facing_rotation[2, 2],
        )))
        if not np.isfinite(yaw):
            raise FaceEngineError("liveness_pose_unavailable", "Pose wajah tidak valid.")

        return yaw

    def _average_hash(self, image: np.ndarray) -> np.ndarray:
        grayscale = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
        resized = cv2.resize(grayscale, (16, 16), interpolation=cv2.INTER_AREA)
        return resized >= float(np.mean(resized))

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
