import hmac
import logging
import time
from collections import defaultdict, deque
from contextlib import asynccontextmanager
from typing import Annotated, AsyncIterator

from fastapi import Depends, FastAPI, Header, HTTPException, Request, status
from fastapi.responses import JSONResponse

from app.config import Settings, get_settings
from app.face_engine import FaceEngine, FaceEngineError
from app.schemas import (
    EnrollRequest,
    EnrollResponse,
    HealthResponse,
    LivenessEnrollResponse,
    LivenessRequest,
    LivenessResultResponse,
    LivenessVerifyRequest,
    LivenessVerifyResponse,
    VerifyRequest,
    VerifyResponse,
)

logger = logging.getLogger(__name__)


def create_app(settings: Settings | None = None, engine: FaceEngine | None = None) -> FastAPI:
    app_settings = settings or get_settings()
    face_engine = engine or FaceEngine(app_settings)

    @asynccontextmanager
    async def lifespan(_: FastAPI) -> AsyncIterator[None]:
        if not face_engine.is_ready:
            face_engine.load()
        yield

    app = FastAPI(
        title="School Attendance Face Service",
        version="1.0.0",
        lifespan=lifespan,
        docs_url="/docs" if app_settings.enable_docs else None,
        redoc_url="/redoc" if app_settings.enable_docs else None,
        openapi_url="/openapi.json" if app_settings.enable_docs else None,
    )
    app.state.face_engine = face_engine
    app.state.settings = app_settings
    request_times: dict[str, deque[float]] = defaultdict(deque)

    @app.middleware("http")
    async def request_limits(request: Request, call_next):
        if request.url.path.startswith("/api/v1/faces/"):
            content_length = request.headers.get("content-length")
            if content_length is None:
                return JSONResponse(status_code=411, content={"detail": "Content-Length wajib dikirim"})
            try:
                request_size = int(content_length)
            except ValueError:
                return JSONResponse(status_code=400, content={"detail": "Content-Length tidak valid"})
            if request_size < 0:
                return JSONResponse(status_code=400, content={"detail": "Content-Length tidak valid"})
            if request_size > app_settings.max_request_bytes:
                return JSONResponse(status_code=413, content={"detail": "Request body terlalu besar"})

            client = request.client.host if request.client else "unknown"
            now = time.monotonic()
            window = request_times[client]
            while window and now - window[0] >= app_settings.rate_limit_window_seconds:
                window.popleft()
            if len(window) >= app_settings.rate_limit_requests:
                return JSONResponse(status_code=429, content={"detail": "Terlalu banyak permintaan"})
            window.append(now)

        return await call_next(request)

    async def require_api_key(x_api_key: Annotated[str | None, Header()] = None) -> None:
        if x_api_key is None or not hmac.compare_digest(x_api_key, app_settings.api_key):
            raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid API key")

    @app.exception_handler(FaceEngineError)
    async def face_engine_error_handler(_: Request, error: FaceEngineError) -> JSONResponse:
        status_code = status.HTTP_503_SERVICE_UNAVAILABLE if error.code == "model_unavailable" else status.HTTP_422_UNPROCESSABLE_CONTENT
        return JSONResponse(
            status_code=status_code,
            content={"detail": {"code": error.code, "message": error.message, "faces_detected": error.faces_detected}},
        )

    @app.get("/health", response_model=HealthResponse)
    async def health() -> HealthResponse:
        return HealthResponse(
            status="ok" if face_engine.is_ready else "starting",
            model_loaded=face_engine.is_ready,
            model_version=app_settings.model_version,
            liveness_ready=face_engine.is_ready,
            liveness_method=app_settings.liveness_method,
            quality_ready=face_engine.is_quality_ready,
            quality_method=app_settings.visibility_model_name,
        )

    @app.post(
        "/api/v1/faces/enroll",
        response_model=EnrollResponse,
        dependencies=[Depends(require_api_key)],
    )
    async def enroll(payload: EnrollRequest) -> EnrollResponse:
        if not app_settings.legacy_face_endpoints_enabled:
            raise HTTPException(status_code=status.HTTP_410_GONE, detail="Legacy face endpoint disabled")
        embedding = face_engine.enroll(payload.image)
        logger.info("Face enrollment processed")
        return EnrollResponse(
            embedding=embedding,
            dimensions=len(embedding),
            model_version=app_settings.model_version,
        )

    @app.post(
        "/api/v1/faces/verify",
        response_model=VerifyResponse,
        dependencies=[Depends(require_api_key)],
    )
    async def verify(payload: VerifyRequest) -> VerifyResponse:
        if payload.model_version != app_settings.model_version:
            raise FaceEngineError("model_mismatch", "Template wajah dibuat oleh model yang tidak kompatibel.")

        result = face_engine.verify(payload.image, payload.embedding)
        logger.info("Face verification processed: matched=%s", result.matched)
        return VerifyResponse(
            matched=result.matched,
            distance=result.distance,
            threshold=result.threshold,
            faces_detected=result.faces_detected,
            model_version=app_settings.model_version,
        )

    @app.post(
        "/api/v1/faces/liveness/enroll",
        response_model=LivenessEnrollResponse,
        dependencies=[Depends(require_api_key)],
    )
    async def liveness_enroll(payload: LivenessRequest) -> LivenessEnrollResponse:
        frames = [(frame.step, frame.image) for frame in payload.frames]
        result = face_engine.enroll_liveness(frames)
        logger.info("Liveness enrollment processed: challenge_id=%s", payload.challenge_id)
        return LivenessEnrollResponse(
            embedding=result.embedding,
            dimensions=len(result.embedding),
            model_version=app_settings.model_version,
            audit_frame_index=result.audit_frame_index,
            liveness=LivenessResultResponse(passed=True, method=app_settings.liveness_method),
        )

    @app.post(
        "/api/v1/faces/liveness/verify",
        response_model=LivenessVerifyResponse,
        dependencies=[Depends(require_api_key)],
    )
    async def liveness_verify(payload: LivenessVerifyRequest) -> LivenessVerifyResponse:
        if payload.model_version != app_settings.model_version:
            raise FaceEngineError("model_mismatch", "Template wajah dibuat oleh model yang tidak kompatibel.")
        frames = [(frame.step, frame.image) for frame in payload.frames]
        result = face_engine.verify_liveness(frames, payload.embedding)
        logger.info(
            "Liveness verification processed: challenge_id=%s matched=%s",
            payload.challenge_id,
            result.matched,
        )
        return LivenessVerifyResponse(
            matched=result.matched,
            distance=result.distance,
            threshold=result.threshold,
            faces_detected=result.faces_detected,
            model_version=app_settings.model_version,
            audit_frame_index=result.audit_frame_index,
            liveness=LivenessResultResponse(passed=True, method=app_settings.liveness_method),
        )

    return app


app = create_app()
