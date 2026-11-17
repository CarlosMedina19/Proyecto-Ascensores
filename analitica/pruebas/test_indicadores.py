"""Pruebas de la analítica descriptiva (indicadores_servicio.resumen)."""
import pytest

from app.esquemas.esquemas import Datos
from app.servicios.indicadores_servicio import resumen


def calcular(**datos):
    base = {"desde": "2026-06-01", "hasta": "2026-08-31"}
    base.update(datos)
    return resumen(Datos.model_validate(base))


def mes(resultado, clave):
    return next(m for m in resultado["meses"] if m["mes"] == clave)


def test_periodo_que_cruza_de_anio():
    r = calcular(desde="2026-11-15", hasta="2027-02-10")
    assert [m["mes"] for m in r["meses"]] == ["2026-11", "2026-12", "2027-01", "2027-02"]
    assert r["periodo"] == {"desde": "2026-11-15", "hasta": "2027-02-10", "meses": 4}


def test_solo_cuentan_las_ordenes_finalizadas():
    r = calcular(ordenes=[
        {"equipo_id": 1, "servicio": "preventivo", "estado": "finalizada", "fecha": "2026-06-05", "duracion_minutos": 30},
        {"equipo_id": 1, "servicio": "preventivo", "estado": "programada", "fecha": "2026-06-20"},
        {"equipo_id": 1, "servicio": "preventivo", "estado": "cancelada", "fecha": "2026-06-25"},
        {"equipo_id": 2, "servicio": "correctivo", "estado": "finalizada", "fecha": "2026-07-01", "duracion_minutos": 90},
        {"equipo_id": 2, "servicio": "inspeccion", "estado": "finalizada", "fecha": "2026-07-02", "duracion_minutos": 60},
    ])
    assert mes(r, "2026-06")["preventivos"] == 1
    assert mes(r, "2026-07")["correctivos"] == 1
    assert r["totales"]["preventivos"] == 1 and r["totales"]["correctivos"] == 1
    # La duración promedio usa todas las órdenes finalizadas (30, 90 y 60 minutos).
    assert r["totales"]["duracion_promedio_minutos"] == 60.0


def test_las_visitas_canceladas_no_afectan_el_cumplimiento():
    r = calcular(visitas=[
        {"equipo_id": 1, "fecha_programada": "2026-06-10", "estado": "ejecutado", "fecha_ejecucion": "2026-06-10"},
        {"equipo_id": 2, "fecha_programada": "2026-06-12", "estado": "cancelado"},
        {"equipo_id": 3, "fecha_programada": "2026-06-14", "estado": "reprogramado"},
    ])
    assert mes(r, "2026-06")["cumplimiento"] == 50.0
    assert r["totales"]["cumplimiento"] == 50.0
    # Un mes sin visitas programadas no tiene cumplimiento (no es 0 %).
    assert mes(r, "2026-07")["cumplimiento"] is None


def test_tiempo_de_respuesta_ignora_emergencias_sin_llegada():
    r = calcular(emergencias=[
        {"equipo_id": 1, "tipo": "falla", "reportada_en": "2026-06-01T08:00:00", "llegada_en": "2026-06-01T08:30:00"},
        {"equipo_id": 1, "tipo": "falla", "reportada_en": "2026-06-10T08:00:00", "llegada_en": "2026-06-10T09:30:00"},
        {"equipo_id": 2, "tipo": "falla", "reportada_en": "2026-06-20T08:00:00"},
    ])
    junio = mes(r, "2026-06")
    assert junio["emergencias"] == 3  # la emergencia sin llegada sí se cuenta
    assert junio["respuesta_minutos"] == 60.0  # promedio de 30 y 90 minutos
    assert mes(r, "2026-07")["respuesta_minutos"] is None
    assert r["totales"]["respuesta_minutos"] == 60.0


def test_promedio_movil_de_tres_meses():
    r = calcular(emergencias=[
        {"equipo_id": 1, "tipo": "falla", "reportada_en": f"2026-06-0{d}T10:00:00"} for d in range(1, 7)
    ])
    # Emergencias por mes: junio 6, julio 0, agosto 0.
    assert [m["emergencias"] for m in r["meses"]] == [6, 0, 0]
    assert [m["emergencias_promedio_movil"] for m in r["meses"]] == [6.0, 3.0, 2.0]


def test_emergencias_con_zona_horaria_se_ubican_en_su_mes_local():
    r = calcular(emergencias=[
        # 30 de junio a las 23:30 en Colombia (UTC-5); en UTC ya sería 1 de julio.
        {"equipo_id": 1, "tipo": "falla", "reportada_en": "2026-06-30T23:30:00-05:00", "llegada_en": "2026-07-01T00:10:00-05:00"},
    ])
    assert mes(r, "2026-06")["emergencias"] == 1
    assert mes(r, "2026-06")["respuesta_minutos"] == 40.0


def test_sin_datos_los_totales_quedan_vacios():
    r = calcular()
    assert all(m["preventivos"] == 0 and m["emergencias"] == 0 for m in r["meses"])
    assert r["totales"]["cumplimiento"] is None
    assert r["totales"]["respuesta_minutos"] is None
    assert r["totales"]["duracion_promedio_minutos"] is None


@pytest.mark.parametrize("campo, valor", [("desde", "no-es-fecha"), ("hasta", "2026-13-01")])
def test_fechas_invalidas_se_rechazan(campo, valor):
    with pytest.raises(ValueError):
        Datos.model_validate({"desde": "2026-06-01", "hasta": "2026-08-31", campo: valor})
