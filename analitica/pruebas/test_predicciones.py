"""Pruebas de la analítica predictiva (prediccion_servicio)."""
from app.esquemas.esquemas import Datos
from app.servicios.prediccion_servicio import pronosticar_emergencias, riesgo_por_equipo


def emergencias_por_mes(conteos, anio=2026, mes_inicial=1):
    """Crea `conteos[i]` emergencias en el mes `mes_inicial + i`."""
    lista = []
    for i, cantidad in enumerate(conteos):
        for d in range(cantidad):
            lista.append({"equipo_id": 1, "tipo": "falla", "reportada_en": f"{anio}-{mes_inicial + i:02d}-{d + 1:02d}T10:00:00"})
    return lista


def datos(**extra):
    base = {"desde": "2026-01-01", "hasta": "2026-06-30"}
    base.update(extra)
    return Datos.model_validate(base)


# --- Pronóstico de emergencias ---------------------------------------------------------------

def test_tendencia_lineal_exacta_se_proyecta_sin_margen():
    pronostico, metodo = pronosticar_emergencias(datos(emergencias=emergencias_por_mes([1, 2, 3, 4, 5, 6])))
    assert "Regresión lineal" in metodo
    assert [p["mes"] for p in pronostico] == ["2026-07", "2026-08", "2026-09"]
    assert [p["emergencias"] for p in pronostico] == [7.0, 8.0, 9.0]
    # Los datos caen exactos sobre la recta: el error es cero y el rango se cierra.
    assert all(p["minimo"] == p["emergencias"] == p["maximo"] for p in pronostico)


def test_el_pronostico_nunca_es_negativo():
    pronostico, _ = pronosticar_emergencias(datos(emergencias=emergencias_por_mes([6, 5, 4, 3, 2, 1])))
    # La recta seguiría bajando a 0, -1 y -2: se recorta en cero.
    assert [p["emergencias"] for p in pronostico] == [0.0, 0.0, 0.0]
    assert all(p["minimo"] >= 0 for p in pronostico)


def test_con_poca_historia_usa_el_promedio():
    pronostico, metodo = pronosticar_emergencias(
        datos(desde="2026-05-01", emergencias=emergencias_por_mes([2, 4], mes_inicial=5), hasta="2026-06-30")
    )
    assert "Promedio" in metodo
    assert [p["emergencias"] for p in pronostico] == [3.0, 3.0, 3.0]
    # Con desviación estándar 1, el rango es 3 ± 1,96.
    assert pronostico[0]["minimo"] == 1.0 and pronostico[0]["maximo"] == 5.0


def test_cantidad_de_meses_a_pronosticar():
    pronostico, _ = pronosticar_emergencias(datos(), meses_adelante=6)
    assert len(pronostico) == 6
    assert pronostico[-1]["mes"] == "2026-12"


# --- Riesgo de falla por ascensor (puntaje explicable, menos de 8 ascensores) ----------------

def equipos(*ids, anio=2015):
    return [{"id": i, "codigo": f"ASC-{i:03d}", "edificio": "Torre A", "anio_instalacion": anio} for i in ids]


def test_sin_ascensores_no_hay_riesgo():
    assert riesgo_por_equipo(datos()) == []


def test_el_ascensor_con_fallas_y_visitas_vencidas_queda_primero():
    resultado = riesgo_por_equipo(datos(
        equipos=equipos(1, 2),
        emergencias=[
            {"equipo_id": 1, "tipo": "falla", "reportada_en": "2026-06-01T10:00:00"},
            {"equipo_id": 1, "tipo": "atrapamiento", "reportada_en": "2026-06-15T10:00:00"},
        ],
        visitas=[
            {"equipo_id": 1, "fecha_programada": "2026-05-01", "estado": "programado"},
            {"equipo_id": 2, "fecha_programada": "2026-06-01", "estado": "ejecutado", "fecha_ejecucion": "2026-06-01"},
        ],
    ))
    assert [r["equipo_id"] for r in resultado] == [1, 2]
    primero, segundo = resultado
    assert primero["fallas_90_dias"] == 2
    assert primero["nivel"] == "alto"
    assert "2 falla(s) en los últimos 90 días" in primero["motivos"]
    assert "1 visita(s) de mantenimiento vencida(s)" in primero["motivos"]
    assert "Sin mantenimientos ejecutados registrados" in primero["motivos"]
    assert segundo["nivel"] == "bajo"
    assert segundo["dias_desde_mantenimiento"] == 30
    assert segundo["motivos"] == ["Sin señales de riesgo"]


def test_solo_fallas_y_atrapamientos_cuentan_como_falla():
    resultado = riesgo_por_equipo(datos(
        equipos=equipos(1),
        emergencias=[{"equipo_id": 1, "tipo": "ruido", "reportada_en": "2026-06-10T10:00:00"}],
    ))
    assert resultado[0]["fallas_90_dias"] == 0


def test_las_fallas_posteriores_al_periodo_no_cuentan():
    resultado = riesgo_por_equipo(datos(
        equipos=equipos(1),
        emergencias=[{"equipo_id": 1, "tipo": "falla", "reportada_en": "2026-07-15T10:00:00"}],
    ))
    assert resultado[0]["fallas_90_dias"] == 0


def test_ascensor_antiguo_y_sin_mantenimiento_reciente():
    resultado = riesgo_por_equipo(datos(
        equipos=equipos(1, anio=1995),
        visitas=[{"equipo_id": 1, "fecha_programada": "2026-01-10", "estado": "ejecutado", "fecha_ejecucion": "2026-01-10"}],
    ))
    motivos = resultado[0]["motivos"]
    assert "31 años de instalado" in motivos
    assert "172 días sin mantenimiento" in motivos


def test_el_puntaje_no_pasa_de_100():
    muchas = [{"equipo_id": 1, "tipo": "falla", "reportada_en": f"2026-06-{d:02d}T10:00:00"} for d in range(1, 21)]
    resultado = riesgo_por_equipo(datos(equipos=equipos(1, anio=1980), emergencias=muchas))
    assert resultado[0]["puntaje"] == 100.0


# --- Riesgo con regresión logística (8 ascensores o más, con y sin fallas) -------------------

def test_con_suficientes_ascensores_el_modelo_da_probabilidades():
    fallas = [
        {"equipo_id": i, "tipo": "falla", "reportada_en": f"2026-{m:02d}-10T10:00:00"}
        for i in (1, 2, 3) for m in (2, 3, 5, 6)
    ]
    resultado = riesgo_por_equipo(datos(equipos=equipos(*range(1, 11)), emergencias=fallas))
    assert len(resultado) == 10
    assert all(0 <= r["puntaje"] <= 100 for r in resultado)
    assert {r["equipo_id"] for r in resultado[:3]} == {1, 2, 3}
    assert [r["puntaje"] for r in resultado] == sorted((r["puntaje"] for r in resultado), reverse=True)
