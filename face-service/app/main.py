import hmac
import logging
from contextlib import asynccontextmanager
from typing import Annotated, AsyncIterator

from fastapi import Depends, FastAPI, Header, HTTPException, Request, status
from fastapi.responses import JSONResponse

from app.config import Settings, get_settings
from app.face_engine import FaceEngine, FaceEngineError
from app.schemas import EnrollRequest, EnrollResponse, HealthResponse, VerifyRequest, VerifyResponse

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
    )
    app.state.face_engine = face_engine
    app.state.settings = app_settings

    async def require_api_key(x_api_key: Annotated[str | None, Header()] = None) -> None:
        if x_api_key is None or not hmac.compare_digest(x_api_key, app_settings.api_key):
            raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid API key")

    @app.exception_handler(FaceEngineError)
    async def face_engine_error_handler(_: Request, error: FaceEngineError) -> JSONResponse:
        status_code = status.HTTP_503_SERVICE_UNAVAILABLE if error.code == "model_unavailable" else status.HTTP_422_UNPROCESSABLE_ENTITY
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
        )

    @app.post(
        "/api/v1/faces/enroll",
        response_model=EnrollResponse,
        dependencies=[Depends(require_api_key)],
    )
    async def enroll(payload: EnrollRequest) -> EnrollResponse:
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

    return app


app = create_app()
