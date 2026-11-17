"""Manejo común de fechas y horas para todos los cálculos."""
from datetime import datetime

import pandas as pd


def hora_local(valor: datetime | None) -> pd.Timestamp | None:
    """
    Fecha y hora tal como la registró Laravel (hora de Colombia), sin zona horaria.

    Laravel envía las fechas con su desfase (por ejemplo ``2026-06-30T23:30:00-05:00``). Para agrupar por
    mes y comparar con las fechas del periodo se usa la hora local que trae el dato, no la hora UTC: una
    emergencia del 30 de junio a las 23:30 pertenece a junio, aunque en UTC ya sea 1 de julio.
    """
    if valor is None:
        return None
    return pd.Timestamp(valor.replace(tzinfo=None))


def serie_hora_local(valores) -> pd.Series:
    """Aplica ``hora_local`` a una lista o serie y devuelve una serie de fechas sin zona horaria."""
    return pd.to_datetime(pd.Series([hora_local(v) for v in valores], dtype="object"))
