import math
from typing import Literal
from uuid import UUID

from pydantic import BaseModel, Field, field_validator, model_validator


EMBEDDING_DIMENSIONS = 512


class EnrollRequest(BaseModel):
    image: str = Field(min_length=32, max_length=4_000_000)


class EnrollResponse(BaseModel):
    embedding: list[float]
    dimensions: int
    model_version: str


class VerifyRequest(BaseModel):
    image: str = Field(min_length=32, max_length=4_000_000)
    embedding: list[float] = Field(min_length=EMBEDDING_DIMENSIONS, max_length=EMBEDDING_DIMENSIONS)
    model_version: str

    @field_validator("embedding")
    @classmethod
    def embedding_must_be_finite(cls, value: list[float]) -> list[float]:
        if any(not math.isfinite(item) for item in value):
            raise ValueError("Embedding contains an invalid number")
        return value


class VerifyResponse(BaseModel):
    matched: bool
    distance: float
    threshold: float
    faces_detected: int
    model_version: str


class HealthResponse(BaseModel):
    status: str
    model_loaded: bool
    model_version: str
    liveness_ready: bool
    liveness_method: str
    quality_ready: bool
    quality_method: str


LivenessStep = Literal["center", "turn_left", "turn_right"]


class LivenessFrame(BaseModel):
    step: LivenessStep
    image: str = Field(min_length=32, max_length=1_000_000)


class LivenessRequest(BaseModel):
    challenge_id: UUID
    method: Literal["active_head_pose_v1"]
    frames: list[LivenessFrame] = Field(min_length=4, max_length=4)

    @model_validator(mode="after")
    def action_sequence_is_valid(self) -> "LivenessRequest":
        steps = [frame.step for frame in self.frames]
        valid_sequences = [
            ["center", "turn_left", "turn_right", "center"],
            ["center", "turn_right", "turn_left", "center"],
        ]
        if steps not in valid_sequences:
            raise ValueError("Invalid liveness action sequence")
        return self


class LivenessVerifyRequest(LivenessRequest):
    embedding: list[float] = Field(min_length=EMBEDDING_DIMENSIONS, max_length=EMBEDDING_DIMENSIONS)
    model_version: str

    @field_validator("embedding")
    @classmethod
    def liveness_embedding_must_be_finite(cls, value: list[float]) -> list[float]:
        if any(not math.isfinite(item) for item in value):
            raise ValueError("Embedding contains an invalid number")
        return value


class LivenessResultResponse(BaseModel):
    passed: bool
    method: str
    reason: str | None = None


class LivenessEnrollResponse(BaseModel):
    embedding: list[float]
    dimensions: int
    model_version: str
    audit_frame_index: int
    liveness: LivenessResultResponse


class LivenessVerifyResponse(BaseModel):
    matched: bool
    distance: float
    threshold: float
    faces_detected: int
    model_version: str
    audit_frame_index: int
    liveness: LivenessResultResponse
