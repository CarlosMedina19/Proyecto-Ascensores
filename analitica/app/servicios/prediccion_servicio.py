"""Analítica predictiva: pronóstico de emergencias y riesgo de falla por ascensor (scikit-learn)."""
from datetime import timedelta

import numpy as np
import pandas as pd
from sklearn.linear_model import LinearRegression, LogisticRegression

from ..core.config import configuracion
from ..esquemas.esquemas import Datos

TIPOS_FALLA = {"falla", "atrapamiento"}


def pronosticar_emergencias(datos: Datos, meses_adelante: int = 3) -> tuple[list[dict], str]:
    """Regresión lineal sobre las emergencias por mes; con poca historia, el promedio."""
    meses = pd.period_range(pd.Timestamp(datos.desde).to_period("M"), pd.Timestamp(datos.hasta).to_period("M"), freq="M")
    fechas = pd.to_datetime(pd.Series([e.reportada_en for e in datos.emergencias], dtype="object"), utc=True)
    conteo = fechas.dt.tz_localize(None).dt.to_period("M").value_counts().reindex(meses, fill_value=0) if not fechas.empty else pd.Series(0, index=meses)

    y = conteo.to_numpy(dtype=float)
    x = np.arange(len(y)).reshape(-1, 1)
    if len(y) >= configuracion.meses_minimos_tendencia:
        modelo = LinearRegression().fit(x, y)
        futuro = np.arange(len(y), len(y) + meses_adelante).reshape(-1, 1)
        estimado = modelo.predict(futuro)
        error = float(np.sqrt(np.mean((modelo.predict(x) - y) ** 2)))
        metodo = "Regresión lineal sobre las emergencias de cada mes"
    else:
        estimado = np.full(meses_adelante, y.mean() if len(y) else 0.0)
        error = float(y.std()) if len(y) else 0.0
        metodo = "Promedio mensual (aún hay poca historia para una tendencia)"

    siguientes = pd.period_range(meses[-1] + 1, periods=meses_adelante, freq="M")
    return [
        {
            "mes": str(mes),
            "emergencias": round(max(valor, 0.0), 1),
            "minimo": round(max(valor - 1.96 * error, 0.0), 1),
            "maximo": round(max(valor + 1.96 * error, 0.0), 1),
        }
        for mes, valor in zip(siguientes, estimado)
    ], metodo


def _caracteristicas(datos: Datos, corte: pd.Timestamp) -> pd.DataFrame:
    """Por ascensor, lo que se sabía en la fecha de corte: fallas recientes, atraso del mantenimiento y edad."""
    filas = []
    fallas = pd.DataFrame(
        [{"equipo_id": e.equipo_id, "fecha": pd.Timestamp(e.reportada_en).tz_localize(None) if pd.Timestamp(e.reportada_en).tzinfo is None else pd.Timestamp(e.reportada_en).tz_convert(None)} for e in datos.emergencias if e.tipo in TIPOS_FALLA],
        columns=["equipo_id", "fecha"],
    )
    visitas = pd.DataFrame([v.model_dump() for v in datos.visitas], columns=["equipo_id", "fecha_programada", "estado", "fecha_ejecucion"])
    for equipo in datos.equipos:
        propias = fallas[(fallas["equipo_id"] == equipo.id) & (fallas["fecha"] <= corte)]
        recientes = int((propias["fecha"] > corte - timedelta(days=90)).sum())
        previas = int(((propias["fecha"] > corte - timedelta(days=180)) & (propias["fecha"] <= corte - timedelta(days=90))).sum())
        suyas = visitas[visitas["equipo_id"] == equipo.id]
        ejecutadas = pd.to_datetime(suyas["fecha_ejecucion"].dropna())
        ejecutadas = ejecutadas[ejecutadas <= corte]
        dias_mantenimiento = int((corte - ejecutadas.max()).days) if not ejecutadas.empty else None
        vencidas = int(((pd.to_datetime(suyas["fecha_programada"]) < corte) & suyas["estado"].isin(["programado", "reprogramado"])).sum()) if not suyas.empty else 0
        edad = (corte.year - equipo.anio_instalacion) if equipo.anio_instalacion else None
        filas.append({
            "equipo_id": equipo.id,
            "fallas_recientes": recientes,
            "fallas_previas": previas,
            "dias_mantenimiento": dias_mantenimiento,
            "visitas_vencidas": vencidas,
            "edad": edad,
        })
    return pd.DataFrame(filas)


def _matriz(tabla: pd.DataFrame) -> np.ndarray:
    return np.column_stack([
        tabla["fallas_recientes"],
        tabla["fallas_previas"],
        tabla["dias_mantenimiento"].fillna(365).clip(upper=365) / 30,
        tabla["visitas_vencidas"],
        tabla["edad"].fillna(10),
    ]).astype(float)


def riesgo_por_equipo(datos: Datos) -> list[dict]:
    """
    Si hay ascensores con y sin fallas en los últimos 90 días, se entrena una regresión logística con lo
    que se sabía hace 90 días y se aplica a hoy. Si no, un puntaje ponderado y explicable.
    """
    if not datos.equipos:
        return []
    hoy = pd.Timestamp(datos.hasta) + timedelta(days=1)
    actuales = _caracteristicas(datos, hoy)

    pasado = _caracteristicas(datos, hoy - timedelta(days=90))
    etiqueta = (actuales["fallas_recientes"] > 0).astype(int)
    probabilidad = None
    if len(actuales) >= 8 and etiqueta.nunique() == 2:
        modelo = LogisticRegression(max_iter=500).fit(_matriz(pasado), etiqueta)
        probabilidad = modelo.predict_proba(_matriz(actuales))[:, 1]

    resultado = []
    for k, fila in actuales.iterrows():
        motivos = []
        if fila["fallas_recientes"]:
            motivos.append(f"{int(fila['fallas_recientes'])} falla(s) en los últimos 90 días")
        if fila["visitas_vencidas"]:
            motivos.append(f"{int(fila['visitas_vencidas'])} visita(s) de mantenimiento vencida(s)")
        if pd.notna(fila["dias_mantenimiento"]) and fila["dias_mantenimiento"] > 120:
            motivos.append(f"{int(fila['dias_mantenimiento'])} días sin mantenimiento")
        if pd.isna(fila["dias_mantenimiento"]):
            motivos.append("Sin mantenimientos ejecutados registrados")
        if pd.notna(fila["edad"]) and fila["edad"] >= 20:
            motivos.append(f"{int(fila['edad'])} años de instalado")

        if probabilidad is not None:
            puntaje = float(probabilidad[k]) * 100
        else:
            dias = 365 if pd.isna(fila["dias_mantenimiento"]) else min(fila["dias_mantenimiento"], 365)
            edad = 10 if pd.isna(fila["edad"]) else fila["edad"]
            puntaje = min(100.0, fila["fallas_recientes"] * 22 + fila["fallas_previas"] * 8 + fila["visitas_vencidas"] * 12 + dias / 365 * 25 + min(edad, 30) / 30 * 15)
        equipo = next(e for e in datos.equipos if e.id == fila["equipo_id"])
        resultado.append({
            "equipo_id": equipo.id,
            "codigo": equipo.codigo,
            "edificio": equipo.edificio,
            "puntaje": round(puntaje, 1),
            "nivel": "alto" if puntaje >= 60 else "medio" if puntaje >= 30 else "bajo",
            "fallas_90_dias": int(fila["fallas_recientes"]),
            "dias_desde_mantenimiento": None if pd.isna(fila["dias_mantenimiento"]) else int(fila["dias_mantenimiento"]),
            "motivos": motivos or ["Sin señales de riesgo"],
        })
    return sorted(resultado, key=lambda r: r["puntaje"], reverse=True)
