@php
    $fecha = fn ($f) => \Carbon\CarbonImmutable::parse($f)->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
    $cifra = fn ($v, $sufijo = '') => $v === null ? '—' : number_format((float) $v, floor((float) $v) != (float) $v ? 1 : 0, ',', '.').$sufijo;
    $r = $datos['respuesta'];
    $pctObjetivo = $r['atendidas'] > 0 ? round(100 * $r['dentro_objetivo'] / $r['atendidas']) : null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Indicadores del servicio</title>
<style>
    @page { margin: 26mm 16mm 20mm 16mm; }
    /* Tipografía de SIGMA, igual que el informe técnico. */
    @font-face { font-family: 'Barlow'; font-weight: bold; src: url('{{ resource_path('fuentes/Barlow-600.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Geist'; font-weight: 400; src: url('{{ resource_path('fuentes/Geist-400.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Geist'; font-weight: bold; src: url('{{ resource_path('fuentes/Geist-600.ttf') }}') format('truetype'); }
    @font-face { font-family: 'JetBrains Mono'; font-weight: 400; src: url('{{ resource_path('fuentes/JetBrainsMono-400.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Michroma'; font-weight: 400; src: url('{{ resource_path('fuentes/Michroma-400.ttf') }}') format('truetype'); }
    * { font-family: 'Geist', Helvetica, sans-serif; }
    body { font-size: 9.5pt; color: #17121f; }
    header { position: fixed; top: -19mm; left: 0; right: 0; height: 14mm; border-bottom: 2px solid #6b3fd4; }
    footer { position: fixed; bottom: -14mm; left: 0; right: 0; height: 9mm; font-size: 7.5pt; color: #655e74; border-top: 1px solid #e6e0f0; padding-top: 2mm; }
    .marca { font-family: 'Michroma', sans-serif; font-size: 13pt; letter-spacing: 1.5px; color: #6b3fd4; }
    .sub { font-family: 'JetBrains Mono', monospace; font-size: 7pt; color: #655e74; letter-spacing: 0.08em; text-transform: uppercase; }
    h1 { font-family: 'Barlow', sans-serif; font-weight: 600; font-size: 16pt; margin: 0 0 1mm; }
    h2 { font-family: 'Barlow', sans-serif; font-weight: 600; font-size: 12pt; color: #6b3fd4; margin: 7mm 0 2mm; border-bottom: 1px solid #e6e0f0; padding-bottom: 1mm; }
    table { width: 100%; border-collapse: collapse; }
    .nota { font-size: 8pt; color: #655e74; }
    .kpi td { width: 25%; padding: 3mm; border: 1px solid #e6e0f0; vertical-align: top; }
    .kpi .titulo { font-size: 8pt; color: #655e74; }
    .kpi .numero { font-family: 'Barlow', sans-serif; font-weight: 600; font-size: 20pt; margin: 1mm 0; }
    .kpi .detalle { font-size: 7.5pt; color: #655e74; }
    .lista th { background: #f0ecf7; text-align: left; font-size: 8pt; padding: 1.6mm 2mm; border: 1px solid #e6e0f0; }
    .lista td { padding: 1.4mm 2mm; border: 1px solid #e6e0f0; vertical-align: top; font-size: 8.5pt; }
    .codigo { font-family: 'JetBrains Mono', monospace; font-size: 8pt; white-space: nowrap; }
    .bien { color: #1f9d5c; } .mal { color: #d9344a; }
    .definiciones td { padding: 1mm 0; font-size: 8pt; color: #655e74; vertical-align: top; }
</style>
</head>
<body>
<header>
    <table>
        <tr>
            <td><span class="marca">SIGMA</span><br><span class="sub">SISTEMA INTEGRAL DE GESTIÓN Y MANTENIMIENTO DE ASCENSORES</span></td>
            <td style="text-align: right; font-size: 8.5pt;">Indicadores del servicio<br><b>{{ $alcance }}</b></td>
        </tr>
    </table>
</header>
<footer>
    Generado el {{ now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY, HH:mm') }} por {{ $generadoPor }}.
    Las cifras se calculan con los registros de SIGMA al momento de generar el informe.
</footer>

<h1>Indicadores del servicio</h1>
<p class="nota">{{ $alcance }} · del {{ $fecha($datos['periodo']['desde']) }} al {{ $fecha($datos['periodo']['hasta']) }}
    ({{ $datos['periodo']['dias'] }} días) · {{ $datos['ascensores'] }} {{ $datos['ascensores'] === 1 ? 'ascensor' : 'ascensores' }} en servicio.</p>

<table class="kpi" style="margin-top: 4mm;">
    <tr>
        <td>
            <div class="titulo">Disponibilidad</div>
            <div class="numero">{{ $cifra($datos['disponibilidad']['porcentaje'], ' %') }}</div>
            <div class="detalle">{{ $cifra($datos['disponibilidad']['horas_detenidas']) }} horas detenidas por fallas</div>
        </td>
        <td>
            <div class="titulo">Tiempo medio entre fallas</div>
            <div class="numero">{{ $cifra($datos['mtbf']['horas'], ' h') }}</div>
            <div class="detalle">{{ $datos['mtbf']['fallas'] }} {{ $datos['mtbf']['fallas'] === 1 ? 'falla' : 'fallas' }} en el período</div>
        </td>
        <td>
            <div class="titulo">Respuesta a emergencias</div>
            <div class="numero">{{ $cifra($r['minutos_promedio'], ' min') }}</div>
            <div class="detalle">{{ $r['atendidas'] }} atendidas{{ $pctObjetivo !== null ? " · {$pctObjetivo} % dentro del objetivo" : '' }}{{ $r['abiertas'] ? ' · '.$r['abiertas'].($r['abiertas'] === 1 ? ' abierta' : ' abiertas') : '' }}</div>
        </td>
        <td>
            <div class="titulo">Cumplimiento del plan</div>
            <div class="numero">{{ $cifra($datos['cumplimiento_plan']['porcentaje'], ' %') }}</div>
            <div class="detalle">{{ $datos['cumplimiento_plan']['ejecutados'] }} de {{ $datos['cumplimiento_plan']['programados'] }} mantenimientos</div>
        </td>
    </tr>
</table>

<h2>Emergencias del período</h2>
@if ($emergencias->isEmpty())
    <p class="nota">No se reportaron emergencias en el período.</p>
@else
    <table class="lista">
        <thead>
            <tr><th>Número</th><th>Ascensor</th><th>Tipo</th><th>Reportada</th><th>Llegada</th><th>Estado</th></tr>
        </thead>
        <tbody>
            @foreach ($emergencias as $e)
                @php $minutos = $e->llegada_en ? (int) $e->reportada_en->diffInMinutes($e->llegada_en) : null; @endphp
                <tr>
                    <td class="codigo">{{ $e->numero }}</td>
                    <td><span class="codigo">{{ $e->equipo?->codigo }}</span> · {{ $e->equipo?->edificio?->nombre }}</td>
                    <td>{{ \App\Models\Emergencia::TIPOS[$e->tipo] ?? $e->tipo }}</td>
                    <td>{{ $e->reportada_en->format('d/m/Y H:i') }}</td>
                    <td>
                        @if ($minutos !== null)
                            <span class="{{ $minutos <= $e->minutos_objetivo ? 'bien' : 'mal' }}">{{ $minutos }} min</span>
                            <span class="nota">(objetivo {{ $e->minutos_objetivo }})</span>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ \App\Models\Emergencia::ESTADOS[$e->estado] ?? $e->estado }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<h2>Cómo se calcula</h2>
<table class="definiciones">
    <tr><td style="width: 30%;"><b>Disponibilidad</b></td><td>1 − horas detenidas por fallas y atrapamientos ÷ horas posibles de los ascensores en el período.</td></tr>
    <tr><td><b>Tiempo medio entre fallas</b></td><td>Horas posibles ÷ número de fallas (emergencias por falla o atrapamiento y órdenes correctivas).</td></tr>
    <tr><td><b>Respuesta a emergencias</b></td><td>Minutos promedio entre el reporte y la llegada del técnico; el objetivo depende de si hay personas atrapadas.</td></tr>
    <tr><td><b>Cumplimiento del plan</b></td><td>Mantenimientos preventivos ejecutados ÷ programados con fecha cumplida en el período.</td></tr>
</table>
</body>
</html>
