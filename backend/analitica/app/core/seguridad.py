"""Solo Laravel llama al servicio: debe enviar la clave compartida en la cabecera X-Api-Key."""
import hmac

from fastapi import Header, HTTPException, status

from .config import configuracion


def exigir_clave(x_api_key: str | None = Header(default=None)) -> None:
    clave = configuracion.clave
    if not clave or not x_api_key or not hmac.compare_digest(x_api_key, clave):
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Clave de servicio no válida.")
