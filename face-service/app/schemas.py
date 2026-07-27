import math

from pydantic import BaseModel, Field, field_validator


class EnrollRequest(BaseModel):
    image: str = Field(min_length=32, max_length=4_000_000)


class EnrollResponse(BaseModel):
    embedding: list[float]
    dimensions: int
    model_version: str


class VerifyRequest(BaseModel):
    image: str = Field(min_length=32, max_length=4_000_000)
    embedding: list[float]
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
