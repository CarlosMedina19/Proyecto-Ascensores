# SIGMA · Backend de analítica (Python)

Servicio aparte del backend Laravel (`../backend`). Hecho con **FastAPI**, **Pandas**, **NumPy** y **scikit-learn**,
calcula la analítica descriptiva y predictiva de la sección *Indicadores* del panel.

- **Laravel es el núcleo.** Autentica, revisa permisos y audita. Solo envía a este servicio los registros que el
  usuario puede ver, sin datos personales, y le devuelve el JSON al panel.
- **Este servicio solo calcula.** No guarda nada ni se conecta a la base de datos.
- **Comunicación interna.** Recibe peticiones HTTP/JSON por la red interna de Docker (`sigma_interna`) con la
  cabecera `X-Api-Key` (`ANALITICA_CLAVE`). No publica puertos a internet.

| Ruta | Qué calcula |
|---|---|
| `GET /salud` | Estado del servicio |
| `POST /api/v1/indicadores/resumen` | Por mes: preventivos, correctivos, emergencias, cumplimiento del plan, tiempo de respuesta y promedio móvil |
| `POST /api/v1/predicciones` | Pronóstico de emergencias a 3 meses (regresión lineal con rango) y riesgo de falla por ascensor (regresión logística, o un puntaje explicable si hay pocos datos) |

## Estructura

```
app/
  main.py          rutas de FastAPI
  core/            configuración y validación de la clave de servicio
  esquemas/        contrato de datos con Laravel (Pydantic)
  servicios/       cálculos: indicadores_servicio.py y prediccion_servicio.py
pruebas/           pruebas con pytest
```

## Desarrollo y pruebas

```sh
cd analitica
python -m venv .venv && .venv/Scripts/activate        # en Linux/macOS: source .venv/bin/activate
pip install -r requirements-pruebas.txt
python -m pytest -q pruebas
ANALITICA_CLAVE=clave-local uvicorn app.main:app --port 8001 --reload
```

## Producción

El contenedor `sigma_analitica` se construye desde esta carpeta
(`backend/docker-compose.prod.yml`, `context: ../analitica`). La clave va en `ANALITICA_CLAVE` dentro de
`backend/.env` en la VPS, y Laravel usa la misma clave para llamar al servicio.
