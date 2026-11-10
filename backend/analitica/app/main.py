"""
Microservicio de analítica de SIGMA (FastAPI).

Laravel sigue siendo el núcleo: autentica, revisa permisos y envía aquí solo los datos que el usuario
puede ver. Este servicio calcula y devuelve JSON; no guarda nada ni toca la base de datos.
"""
from fastapi import Depends, FastAPI

from .core.seguridad import exigir_clave
from .esquemas.esquemas import Datos, Predicciones, Resumen
from .servicios import indicadores_servicio, prediccion_servicio

app = FastAPI(title="SIGMA · Analítica", version="1.0.0", docs_url="/docs", redoc_url=None)


@app.get("/salud")
def salud() -> dict:
    return {"estado": "ok"}


@app.post("/api/v1/indicadores/resumen", response_model=Resumen, dependencies=[Depends(exigir_clave)])
def resumen(datos: Datos) -> dict:
    return indicadores_servicio.resumen(datos)


@app.post("/api/v1/predicciones", response_model=Predicciones, dependencies=[Depends(exigir_clave)])
def predicciones(datos: Datos) -> dict:
    pronostico, metodo = prediccion_servicio.pronosticar_emergencias(datos)
    return {"pronostico": pronostico, "metodo": metodo, "riesgo": prediccion_servicio.riesgo_por_equipo(datos)}
