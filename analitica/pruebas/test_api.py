import os

os.environ["ANALITICA_CLAVE"] = "clave-de-prueba"

from fastapi.testclient import TestClient  # noqa: E402

from app.main import app  # noqa: E402

cliente = TestClient(app)
CABECERA = {"X-Api-Key": "clave-de-prueba"}


def datos(**extra):
    base = {
        "desde": "2026-05-01",
        "hasta": "2026-10-31",
        "equipos": [{"id": i, "codigo": f"ASC-{i:03d}", "edificio": "Torre A", "anio_instalacion": 2000 + i} for i in range(1, 11)],
        "ordenes": [
            {"equipo_id": 1, "servicio": "preventivo", "estado": "finalizada", "fecha": "2026-06-10", "duracion_minutos": 60},
            {"equipo_id": 2, "servicio": "correctivo", "estado": "finalizada", "fecha": "2026-07-03", "duracion_minutos": 120},
            {"equipo_id": 3, "servicio": "preventivo", "estado": "programada", "fecha": "2026-10-20"},
        ],
        "emergencias": [
            {"equipo_id": 1, "tipo": "falla", "reportada_en": f"2026-{m:02d}-05T10:00:00", "llegada_en": f"2026-{m:02d}-05T10:40:00"}
            for m in range(5, 11)
        ] + [{"equipo_id": 2, "tipo": "atrapamiento", "reportada_en": "2026-10-20T08:00:00", "llegada_en": "2026-10-20T08:20:00"}],
        "visitas": [
            {"equipo_id": 1, "fecha_programada": "2026-06-10", "estado": "ejecutado", "fecha_ejecucion": "2026-06-10"},
            {"equipo_id": 1, "fecha_programada": "2026-07-10", "estado": "programado"},
            {"equipo_id": 2, "fecha_programada": "2026-08-10", "estado": "ejecutado", "fecha_ejecucion": "2026-08-12"},
        ],
    }
    base.update(extra)
    return base


def test_sin_clave_no_responde():
    assert cliente.post("/api/v1/indicadores/resumen", json=datos()).status_code == 401
    assert cliente.post("/api/v1/indicadores/resumen", json=datos(), headers={"X-Api-Key": "otra"}).status_code == 401
    assert cliente.get("/salud").json() == {"estado": "ok"}


def test_resumen_mensual():
    r = cliente.post("/api/v1/indicadores/resumen", json=datos(), headers=CABECERA)
    assert r.status_code == 200
    cuerpo = r.json()
    assert [m["mes"] for m in cuerpo["meses"]] == ["2026-05", "2026-06", "2026-07", "2026-08", "2026-09", "2026-10"]
    junio = cuerpo["meses"][1]
    assert junio["preventivos"] == 1 and junio["emergencias"] == 1 and junio["cumplimiento"] == 100.0
    assert cuerpo["meses"][2]["cumplimiento"] == 0.0  # la visita de julio no se ejecutó
    assert cuerpo["meses"][5]["emergencias"] == 2
    assert cuerpo["totales"]["respuesta_minutos"] is not None
    assert cuerpo["totales"]["cumplimiento"] == round(2 / 3 * 100, 1)


def test_predicciones_pronostico_y_riesgo():
    r = cliente.post("/api/v1/predicciones", json=datos(), headers=CABECERA)
    assert r.status_code == 200
    cuerpo = r.json()
    assert [p["mes"] for p in cuerpo["pronostico"]] == ["2026-11", "2026-12", "2027-01"]
    assert all(p["minimo"] <= p["emergencias"] <= p["maximo"] for p in cuerpo["pronostico"])
    assert "Regresión lineal" in cuerpo["metodo"]
    riesgo = cuerpo["riesgo"]
    assert len(riesgo) == 10
    assert riesgo[0]["equipo_id"] == 1  # el que más falla queda primero
    assert riesgo[0]["nivel"] in {"medio", "alto"}
    assert any("falla" in m for m in riesgo[0]["motivos"])


def test_sin_datos_no_falla():
    vacio = {"desde": "2026-10-01", "hasta": "2026-10-31"}
    assert cliente.post("/api/v1/indicadores/resumen", json=vacio, headers=CABECERA).status_code == 200
    r = cliente.post("/api/v1/predicciones", json=vacio, headers=CABECERA).json()
    assert r["riesgo"] == [] and len(r["pronostico"]) == 3
