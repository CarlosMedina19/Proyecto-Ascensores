"""Analítica descriptiva: series mensuales de órdenes, emergencias, cumplimiento y tiempo de respuesta."""
import numpy as np
import pandas as pd

from ..esquemas.esquemas import Datos


def _meses(desde, hasta) -> pd.PeriodIndex:
    return pd.period_range(pd.Timestamp(desde).to_period("M"), pd.Timestamp(hasta).to_period("M"), freq="M")


def _conteo_por_mes(fechas: pd.Series, meses: pd.PeriodIndex) -> pd.Series:
    if fechas.empty:
        return pd.Series(0, index=meses)
    return pd.to_datetime(fechas).dt.to_period("M").value_counts().reindex(meses, fill_value=0)


def _redondear(valor) -> float | None:
    return None if valor is None or pd.isna(valor) else round(float(valor), 1)


def resumen(datos: Datos) -> dict:
    meses = _meses(datos.desde, datos.hasta)

    ordenes = pd.DataFrame([o.model_dump() for o in datos.ordenes], columns=["equipo_id", "servicio", "estado", "fecha", "duracion_minutos"])
    emergencias = pd.DataFrame([e.model_dump() for e in datos.emergencias], columns=["equipo_id", "tipo", "reportada_en", "llegada_en", "resuelta_en", "minutos_objetivo"])
    visitas = pd.DataFrame([v.model_dump() for v in datos.visitas], columns=["equipo_id", "fecha_programada", "estado", "fecha_ejecucion"])

    finalizadas = ordenes[ordenes["estado"] == "finalizada"]
    preventivos = _conteo_por_mes(finalizadas.loc[finalizadas["servicio"] == "preventivo", "fecha"], meses)
    correctivos = _conteo_por_mes(finalizadas.loc[finalizadas["servicio"] == "correctivo", "fecha"], meses)
    por_mes_emergencias = _conteo_por_mes(emergencias["reportada_en"], meses)

    # Cumplimiento: de las visitas que vencían en el mes (sin canceladas), cuántas se ejecutaron.
    cumplimiento = pd.Series(np.nan, index=meses)
    if not visitas.empty:
        visitas = visitas[visitas["estado"] != "cancelado"].copy()
        visitas["mes"] = pd.to_datetime(visitas["fecha_programada"]).dt.to_period("M")
        agrupadas = visitas.groupby("mes")["estado"].agg(total="size", ejecutadas=lambda s: (s == "ejecutado").sum())
        cumplimiento = (agrupadas["ejecutadas"] / agrupadas["total"] * 100).reindex(meses)

    # Tiempo de respuesta: minutos desde el reporte hasta la llegada del técnico.
    respuesta = pd.Series(np.nan, index=meses)
    if not emergencias.empty:
        con_llegada = emergencias.dropna(subset=["llegada_en"]).copy()
        if not con_llegada.empty:
            con_llegada["minutos"] = (pd.to_datetime(con_llegada["llegada_en"]) - pd.to_datetime(con_llegada["reportada_en"])).dt.total_seconds() / 60
            con_llegada["mes"] = pd.to_datetime(con_llegada["reportada_en"]).dt.tz_localize(None).dt.to_period("M")
            respuesta = con_llegada.groupby("mes")["minutos"].mean().reindex(meses)

    movil = por_mes_emergencias.rolling(window=3, min_periods=1).mean()

    filas = [
        {
            "mes": str(mes),
            "preventivos": int(preventivos[mes]),
            "correctivos": int(correctivos[mes]),
            "emergencias": int(por_mes_emergencias[mes]),
            "cumplimiento": _redondear(cumplimiento[mes]),
            "respuesta_minutos": _redondear(respuesta[mes]),
            "emergencias_promedio_movil": _redondear(movil[mes]),
        }
        for mes in meses
    ]

    total_visitas = int(visitas.shape[0]) if not visitas.empty else 0
    ejecutadas = int((visitas["estado"] == "ejecutado").sum()) if total_visitas else 0
    return {
        "meses": filas,
        "totales": {
            "preventivos": int(preventivos.sum()),
            "correctivos": int(correctivos.sum()),
            "emergencias": int(por_mes_emergencias.sum()),
            "cumplimiento": _redondear(ejecutadas / total_visitas * 100) if total_visitas else None,
            "respuesta_minutos": _redondear(respuesta.mean()) if respuesta.notna().any() else None,
            "duracion_promedio_minutos": _redondear(finalizadas["duracion_minutos"].dropna().mean()) if not finalizadas.empty else None,
        },
        "periodo": {"desde": datos.desde.isoformat(), "hasta": datos.hasta.isoformat(), "meses": len(meses)},
    }
