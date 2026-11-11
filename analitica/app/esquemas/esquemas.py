"""Contrato de datos con Laravel. Laravel envía solo los registros que el usuario puede ver."""
from datetime import date, datetime

from pydantic import BaseModel, Field


class Orden(BaseModel):
    equipo_id: int
    servicio: str  # preventivo | correctivo | inspeccion
    estado: str
    fecha: date
    duracion_minutos: int | None = None


class Emergencia(BaseModel):
    equipo_id: int
    tipo: str
    reportada_en: datetime
    llegada_en: datetime | None = None
    resuelta_en: datetime | None = None
    minutos_objetivo: int | None = None


class Visita(BaseModel):
    equipo_id: int
    fecha_programada: date
    estado: str  # programado | reprogramado | ejecutado | cancelado
    fecha_ejecucion: date | None = None


class Equipo(BaseModel):
    id: int
    codigo: str
    edificio: str | None = None
    anio_instalacion: int | None = None


class Datos(BaseModel):
    desde: date
    hasta: date
    equipos: list[Equipo] = Field(default_factory=list)
    ordenes: list[Orden] = Field(default_factory=list)
    emergencias: list[Emergencia] = Field(default_factory=list)
    visitas: list[Visita] = Field(default_factory=list)


class Mes(BaseModel):
    mes: str  # AAAA-MM
    preventivos: int
    correctivos: int
    emergencias: int
    cumplimiento: float | None  # % de visitas ejecutadas de las que vencían ese mes
    respuesta_minutos: float | None  # promedio de minutos hasta la llegada
    emergencias_promedio_movil: float | None


class Resumen(BaseModel):
    meses: list[Mes]
    totales: dict
    periodo: dict


class Pronostico(BaseModel):
    mes: str
    emergencias: float
    minimo: float
    maximo: float


class RiesgoEquipo(BaseModel):
    equipo_id: int
    codigo: str
    edificio: str | None
    puntaje: float  # 0 a 100
    nivel: str  # bajo | medio | alto
    fallas_90_dias: int
    dias_desde_mantenimiento: int | None
    motivos: list[str]


class Predicciones(BaseModel):
    pronostico: list[Pronostico]
    metodo: str
    riesgo: list[RiesgoEquipo]
