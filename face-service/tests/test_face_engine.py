from types import SimpleNamespace

import cv2
import numpy as np
import pytest

from app.config import Settings
from app.face_engine import FaceEngine, FaceEngineError, FaceObservation


def quality_fixture(with_glasses: bool = False) -> tuple[np.ndarray, SimpleNamespace]:
    rng = np.random.default_rng(20260818)
    grayscale_noise = rng.integers(55, 205, size=(480, 640), dtype=np.uint8)
    image = cv2.cvtColor(grayscale_noise, cv2.COLOR_GRAY2BGR)
    bbox = np.asarray([220.0, 100.0, 420.0, 400.0], dtype=np.float32)

    model_points = np.asarray([
        [-30.0, 35.0, 30.0],
        [30.0, 35.0, 30.0],
        [0.0, 0.0, 0.0],
        [-25.0, -30.0, 30.0],
        [25.0, -30.0, 30.0],
    ], dtype=np.float64)
    rotation_vector, _ = cv2.Rodrigues(np.diag([1.0, -1.0, -1.0]))
    camera = np.asarray([[640.0, 0.0, 320.0], [0.0, 640.0, 240.0], [0.0, 0.0, 1.0]])
    keypoints, _ = cv2.projectPoints(
        model_points,
        rotation_vector,
        np.asarray([[0.0], [0.0], [500.0]]),
        camera,
        np.zeros((4, 1)),
    )
    keypoints = keypoints.reshape(5, 2).astype(np.float32)

    angles = np.linspace(0.0, 2.0 * np.pi, 106, endpoint=False)
    landmarks = np.column_stack((
        320.0 + 90.0 * np.cos(angles),
        250.0 + 130.0 * np.sin(angles),
    )).astype(np.float32)

    cv2.ellipse(image, (320, 250), (90, 130), 0, 0, 360, (115, 145, 170), 3)
    cv2.circle(image, tuple(np.rint(keypoints[0]).astype(int)), 10, (35, 35, 35), 2)
    cv2.circle(image, tuple(np.rint(keypoints[1]).astype(int)), 10, (35, 35, 35), 2)
    cv2.line(
        image,
        tuple(np.rint(keypoints[3]).astype(int)),
        tuple(np.rint(keypoints[4]).astype(int)),
        (35, 35, 35),
        3,
    )
    if with_glasses:
        left_eye = tuple(np.rint(keypoints[0]).astype(int))
        right_eye = tuple(np.rint(keypoints[1]).astype(int))
        cv2.rectangle(image, (left_eye[0] - 18, left_eye[1] - 13), (left_eye[0] + 18, left_eye[1] + 13), (20, 20, 20), 2)
        cv2.rectangle(image, (right_eye[0] - 18, right_eye[1] - 13), (right_eye[0] + 18, right_eye[1] + 13), (20, 20, 20), 2)
        cv2.line(image, (left_eye[0] + 18, left_eye[1]), (right_eye[0] - 18, right_eye[1]), (20, 20, 20), 2)

    face = SimpleNamespace(
        bbox=bbox,
        det_score=0.98,
        kps=keypoints,
        landmark_2d_106=landmarks,
        embedding_norm=22.0,
        normed_embedding=np.ones(512, dtype=np.float32) / np.sqrt(512.0),
    )
    return image, face


def observation(yaw: float, hash_seed: int, embedding_distance: float = 0.0) -> FaceObservation:
    image_hash = np.zeros(256, dtype=bool)
    image_hash[hash_seed : hash_seed + 8] = True
    embedding = np.zeros(512, dtype=np.float32)
    similarity = 1.0 - embedding_distance
    embedding[0] = similarity
    embedding[1] = np.sqrt(max(0.0, 1.0 - similarity**2))
    return FaceObservation(embedding=embedding, yaw=yaw, image_hash=image_hash)


def test_liveness_accepts_left_and_right_yaw_sequence() -> None:
    engine = FaceEngine(Settings(api_key="test-secret"))
    frames = [(step, "image") for step in ["center", "turn_left", "turn_right", "center"]]
    observations = [
        observation(0.0, 0),
        observation(-25.0, 16),
        observation(25.0, 32),
        observation(0.0, 48),
    ]

    embedding = engine._validate_liveness(frames, observations)

    assert embedding.shape == (512,)
    assert embedding[0] == pytest.approx(1.0)


@pytest.mark.parametrize(
    ("steps", "turn_yaws"),
    [
        (["center", "turn_left", "turn_right", "center"], [-8.0, 42.0]),
        (["center", "turn_right", "turn_left", "center"], [42.0, -8.0]),
    ],
)
def test_liveness_accepts_consistent_center_pose_with_camera_bias(
    steps: list[str],
    turn_yaws: list[float],
) -> None:
    engine = FaceEngine(Settings(api_key="test-secret"))
    frames = [(step, "image") for step in steps]
    observations = [
        observation(17.0, 0),
        observation(turn_yaws[0], 16),
        observation(turn_yaws[1], 32),
        observation(15.0, 48),
    ]

    embedding = engine._validate_liveness(frames, observations)

    assert embedding.shape == (512,)


def test_liveness_rejects_center_frames_that_do_not_return_to_the_same_pose() -> None:
    engine = FaceEngine(Settings(api_key="test-secret"))
    frames = [(step, "image") for step in ["center", "turn_left", "turn_right", "center"]]
    observations = [
        observation(12.0, 0),
        observation(-15.0, 16),
        observation(35.0, 32),
        observation(-4.0, 48),
    ]

    with pytest.raises(FaceEngineError) as error:
        engine._validate_liveness(frames, observations)

    assert error.value.code == "liveness_center_pose"
    assert "langkah pertama" in error.value.message


def test_liveness_rejects_center_pose_outside_the_hard_limit() -> None:
    engine = FaceEngine(Settings(api_key="test-secret"))
    frames = [(step, "image") for step in ["center", "turn_left", "turn_right", "center"]]
    observations = [
        observation(28.0, 0),
        observation(0.0, 16),
        observation(50.0, 32),
        observation(27.0, 48),
    ]

    with pytest.raises(FaceEngineError) as error:
        engine._validate_liveness(frames, observations)

    assert error.value.code == "liveness_center_pose"


def test_liveness_requires_turns_relative_to_the_center_reference() -> None:
    engine = FaceEngine(Settings(api_key="test-secret"))
    frames = [(step, "image") for step in ["center", "turn_left", "turn_right", "center"]]
    observations = [
        observation(17.0, 0),
        observation(1.0, 16),
        observation(40.0, 32),
        observation(16.0, 48),
    ]

    with pytest.raises(FaceEngineError) as error:
        engine._validate_liveness(frames, observations)

    assert error.value.code == "liveness_turn_left"


def test_liveness_accepts_pose_variation_for_the_same_identity() -> None:
    engine = FaceEngine(Settings(api_key="test-secret"))
    frames = [(step, "image") for step in ["center", "turn_left", "turn_right", "center"]]
    observations = [
        observation(0.0, 0),
        observation(-25.0, 16, embedding_distance=0.52),
        observation(25.0, 32, embedding_distance=0.50),
        observation(1.0, 48, embedding_distance=0.42),
    ]

    embedding = engine._validate_liveness(frames, observations)

    assert embedding.shape == (512,)


def test_liveness_rejects_different_identity_between_center_frames() -> None:
    engine = FaceEngine(Settings(api_key="test-secret"))
    frames = [(step, "image") for step in ["center", "turn_left", "turn_right", "center"]]
    observations = [
        observation(0.0, 0),
        observation(-25.0, 16),
        observation(25.0, 32),
        observation(1.0, 48, embedding_distance=0.50),
    ]

    with pytest.raises(FaceEngineError) as error:
        engine._validate_liveness(frames, observations)

    assert error.value.code == "liveness_identity_changed"
    assert "langkah akhir" in error.value.message


def test_liveness_rejects_different_identity_during_a_turn() -> None:
    engine = FaceEngine(Settings(api_key="test-secret"))
    frames = [(step, "image") for step in ["center", "turn_left", "turn_right", "center"]]
    observations = [
        observation(0.0, 0),
        observation(-25.0, 16, embedding_distance=0.60),
        observation(25.0, 32),
        observation(1.0, 48),
    ]

    with pytest.raises(FaceEngineError) as error:
        engine._validate_liveness(frames, observations)

    assert error.value.code == "liveness_identity_changed"
    assert "gerakan liveness" in error.value.message


def test_liveness_rejects_reused_frames() -> None:
    engine = FaceEngine(Settings(api_key="test-secret"))
    frames = [(step, "image") for step in ["center", "turn_left", "turn_right", "center"]]
    repeated = observation(0.0, 0)

    with pytest.raises(FaceEngineError, match="Frame liveness terdeteksi berulang") as error:
        engine._validate_liveness(frames, [repeated, repeated, observation(25.0, 32), observation(0.0, 48)])

    assert error.value.code == "liveness_frame_reused"


def test_liveness_rejects_wrong_turn_direction() -> None:
    engine = FaceEngine(Settings(api_key="test-secret"))
    frames = [(step, "image") for step in ["center", "turn_left", "turn_right", "center"]]
    observations = [
        observation(0.0, 0),
        observation(25.0, 16),
        observation(-25.0, 32),
        observation(0.0, 48),
    ]

    with pytest.raises(FaceEngineError) as error:
        engine._validate_liveness(frames, observations)

    assert error.value.code == "liveness_turn_left"


@pytest.mark.parametrize("expected_yaw_degrees", [-25.0, 0.0, 25.0])
def test_solvepnp_yaw_is_stable_for_an_upright_face(expected_yaw_degrees: float) -> None:
    engine = FaceEngine(Settings(api_key="test-secret"))
    model_points = np.asarray([
        [-30.0, 35.0, 30.0],
        [30.0, 35.0, 30.0],
        [0.0, 0.0, 0.0],
        [-25.0, -30.0, 30.0],
        [25.0, -30.0, 30.0],
    ], dtype=np.float64)
    camera_yaw = np.radians(-expected_yaw_degrees)
    yaw_rotation = np.asarray([
        [np.cos(camera_yaw), 0.0, np.sin(camera_yaw)],
        [0.0, 1.0, 0.0],
        [-np.sin(camera_yaw), 0.0, np.cos(camera_yaw)],
    ], dtype=np.float64)
    rotation = yaw_rotation @ np.diag([1.0, -1.0, -1.0])
    rotation_vector, _ = cv2.Rodrigues(rotation)
    camera = np.asarray([[640.0, 0.0, 320.0], [0.0, 640.0, 240.0], [0.0, 0.0, 1.0]])
    keypoints, _ = cv2.projectPoints(
        model_points,
        rotation_vector,
        np.asarray([[0.0], [0.0], [500.0]]),
        camera,
        np.zeros((4, 1)),
    )
    keypoints = keypoints.reshape(5, 2)

    estimated = engine._estimate_yaw(SimpleNamespace(kps=keypoints), np.zeros((480, 640, 3)))

    assert np.mean(keypoints[:2, 1]) < np.mean(keypoints[3:, 1])
    assert estimated == pytest.approx(expected_yaw_degrees, abs=1.0)


def test_strict_quality_accepts_clear_frontal_face_with_transparent_glasses() -> None:
    engine = FaceEngine(Settings(api_key="test-secret"))
    image, face = quality_fixture(with_glasses=True)

    yaw = engine._assert_face_quality(face, image, require_frontal=True)

    assert yaw == pytest.approx(0.0, abs=1.0)


def test_strict_quality_rejects_face_touching_camera_boundary() -> None:
    engine = FaceEngine(Settings(api_key="test-secret"))
    image, face = quality_fixture()
    face.bbox = np.asarray([0.0, 100.0, 200.0, 400.0], dtype=np.float32)

    with pytest.raises(FaceEngineError) as error:
        engine._assert_face_inside_frame(face.bbox, image)

    assert error.value.code == "face_partial"


def test_visibility_allows_landmarks_just_outside_detector_bbox() -> None:
    engine = FaceEngine(Settings(api_key="test-secret"))
    image, face = quality_fixture()
    face.landmark_2d_106 = face.landmark_2d_106.copy()
    face.landmark_2d_106[0, 0] = face.bbox[0] - (face.bbox[2] - face.bbox[0]) * 0.04

    engine._assert_face_visibility(face, image, face.bbox, face.kps)


def test_visibility_still_rejects_landmarks_that_only_cover_half_the_face() -> None:
    engine = FaceEngine(Settings(api_key="test-secret"))
    image, face = quality_fixture()
    landmarks = face.landmark_2d_106.copy()
    center_x = float(np.mean(landmarks[:, 0]))
    landmarks[:, 0] = center_x + (landmarks[:, 0] - center_x) * 0.35
    face.landmark_2d_106 = landmarks

    with pytest.raises(FaceEngineError) as error:
        engine._assert_face_visibility(face, image, face.bbox, face.kps)

    assert error.value.code == "face_partial"


def test_strict_quality_rejects_low_detection_confidence() -> None:
    engine = FaceEngine(Settings(api_key="test-secret"))
    image, face = quality_fixture()
    face.det_score = 0.60

    with pytest.raises(FaceEngineError) as error:
        engine._assert_face_quality(face, image, require_frontal=True)

    assert error.value.code == "face_low_quality"


def test_strict_quality_rejects_weak_recognition_signal() -> None:
    engine = FaceEngine(Settings(api_key="test-secret"))
    image, face = quality_fixture()
    face.embedding_norm = 8.0

    with pytest.raises(FaceEngineError) as error:
        engine._assert_face_quality(face, image, require_frontal=True)

    assert error.value.code == "face_low_quality"


def test_strict_quality_rejects_missing_visibility_landmarks() -> None:
    engine = FaceEngine(Settings(api_key="test-secret"))
    image, face = quality_fixture()
    face.landmark_2d_106 = None

    with pytest.raises(FaceEngineError) as error:
        engine._assert_face_visibility(face, image, face.bbox, face.kps)

    assert error.value.code == "face_visibility_low"


def test_strict_quality_rejects_occluded_mouth_region() -> None:
    engine = FaceEngine(Settings(api_key="test-secret"))
    image, face = quality_fixture()
    mouth_center = np.mean(face.kps[3:], axis=0).astype(int)
    image[mouth_center[1] - 35:mouth_center[1] + 36, mouth_center[0] - 45:mouth_center[0] + 46] = 128

    with pytest.raises(FaceEngineError) as error:
        engine._assert_face_visibility(face, image, face.bbox, face.kps)

    assert error.value.code == "face_occluded"


def test_strict_quality_rejects_dark_face_crop() -> None:
    engine = FaceEngine(Settings(api_key="test-secret"))
    crop = np.full((240, 180, 3), 20, dtype=np.uint8)

    with pytest.raises(FaceEngineError) as error:
        engine._assert_crop_quality(crop)

    assert error.value.code == "face_too_dark"


def test_strict_quality_rejects_overexposed_face_crop() -> None:
    engine = FaceEngine(Settings(api_key="test-secret"))
    crop = np.full((240, 180, 3), 245, dtype=np.uint8)

    with pytest.raises(FaceEngineError) as error:
        engine._assert_crop_quality(crop)

    assert error.value.code == "face_overexposed"


def test_strict_quality_rejects_blurry_face_crop() -> None:
    engine = FaceEngine(Settings(api_key="test-secret", min_contrast=1.0))
    x_axis = np.linspace(70, 180, 180, dtype=np.uint8)
    grayscale = np.tile(x_axis, (240, 1))
    crop = cv2.cvtColor(grayscale, cv2.COLOR_GRAY2BGR)

    with pytest.raises(FaceEngineError) as error:
        engine._assert_crop_quality(crop)

    assert error.value.code == "face_blurry"


def test_attendance_rejects_non_frontal_pose(monkeypatch: pytest.MonkeyPatch) -> None:
    engine = FaceEngine(Settings(api_key="test-secret"))
    image, face = quality_fixture()
    monkeypatch.setattr(engine, "_estimate_yaw", lambda _face, _image: 30.0)

    with pytest.raises(FaceEngineError) as error:
        engine._assert_face_quality(face, image, require_frontal=True)

    assert error.value.code == "face_pose_invalid"
