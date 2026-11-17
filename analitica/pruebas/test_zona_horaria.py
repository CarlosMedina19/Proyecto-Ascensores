"""
Las fechas llegan de Laravel con el desfase de Colombia (-05:00). Todos los cálculos deben ubicar cada
emergencia en el mes de su hora local, no en el de su hora UTC.
"""
from datetime import datetime, timedelta, timezone

from app.esquemas.esquemas import Datos
from app.servicios.fechas import hora_local
from app.servicios.indicadores_servicio import resumen
from app.servicios.prediccion_servicio import pronosticar_emergencias, riesgo_por_equipo

# 30 de junio a las 23:30 en Colombia; en UTC ya es 1 de julio a las 04:30.
FIN_DE_JUNIO = "2026-06-30T23:30:00-05:00"


def datos(**extra):
    base = {"desde": "2026-04-01", "hasta": "2026-06-30"}
    base.update(extra)
    return Datos.model_validate(base)


def test_hora_local_conserva_la_hora_registrada():
    colombia = timezone(timedelta(hours=-5))
    assert hora_local(datetime(2026, 6, 30, 23, 30, tzinfo=colombia)) == datetime(2026, 6, 30, 23, 30)
    assert hora_local(datetime(2026, 6, 30, 23, 30)) == datetime(2026, 6, 30, 23, 30)
    assert hora_local(None) is None


def test_indicadores_y_pronostico_cuentan_la_emergencia_en_el_mismo_mes():
    d = datos(emergencias=[{"equipo_id": 1, "tipo": "falla", "reportada_en": FIN_DE_JUNIO}])
    assert [m["emergencias"] for m in resumen(d)["meses"]] == [0, 0, 1]
    # Con 3 meses (0, 0, 1) la recta proyecta 1,33 / 1,83 / 2,33. Antes la emergencia se tomaba
    # como de julio, quedaba fuera del periodo y el pronóstico daba 0.
    pronostico, _ = pronosticar_emergencias(d)
    assert [p["emergencias"] for p in pronostico] == [1.3, 1.8, 2.3]


def test_la_falla_del_ultimo_dia_cuenta_para_el_riesgo():
    d = datos(
        equipos=[{"id": 1, "codigo": "ASC-001"}],
        emergencias=[{"equipo_id": 1, "tipo": "falla", "reportada_en": FIN_DE_JUNIO}],
    )
    assert riesgo_por_equipo(d)[0]["fallas_90_dias"] == 1


def test_fechas_en_utc_tambien_se_aceptan():
    # Si alguna fecha llega en UTC ("Z"), se usa tal como viene.
    d = datos(emergencias=[{"equipo_id": 1, "tipo": "falla", "reportada_en": "2026-05-15T12:00:00Z"}])
    assert [m["emergencias"] for m in resumen(d)["meses"]] == [0, 1, 0]
