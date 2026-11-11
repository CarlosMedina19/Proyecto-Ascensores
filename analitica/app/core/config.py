"""Configuración del microservicio (variables de entorno)."""
import os


class Configuracion:
    # Clave compartida con Laravel (ANALITICA_CLAVE en backend/.env de la VPS). Sin clave, el servicio rechaza todo.
    clave: str = os.environ.get("ANALITICA_CLAVE", "")
    # Meses de historia mínimos para ajustar una tendencia; con menos se usa el promedio.
    meses_minimos_tendencia: int = int(os.environ.get("ANALITICA_MESES_MINIMOS", "3"))


configuracion = Configuracion()
