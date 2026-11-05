<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Contrato {{ $contrato->numero }}</title>
<style>
    @page { margin: 26mm 16mm 20mm 16mm; }
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
    h2 { font-family: 'Barlow', sans-serif; font-weight: 600; font-size: 12pt; color: #6b3fd4; margin: 6mm 0 2mm; border-bottom: 1px solid #e6e0f0; padding-bottom: 1mm; }
    table { width: 100%; border-collapse: collapse; }
    .datos td { padding: 1.6mm 2mm; border: 1px solid #e6e0f0; vertical-align: top; }
    .datos .etiqueta { width: 22%; background: #f8f6fc; color: #655e74; font-size: 8pt; }
    .lista th { background: #f0ecf7; text-align: left; font-size: 8pt; padding: 1.6mm 2mm; border: 1px solid #e6e0f0; }
    .lista td { padding: 1.4mm 2mm; border: 1px solid #e6e0f0; vertical-align: top; }
    .numero { text-align: right; white-space: nowrap; }
    .codigo { font-family: 'JetBrains Mono', monospace; font-size: 8pt; color: #655e74; }
    .valor { font-family: 'Barlow', sans-serif; font-size: 15pt; font-weight: 600; color: #6b3fd4; }
    .texto { white-space: pre-line; line-height: 1.45; }
    .nota { font-size: 8pt; color: #655e74; }
    .estado { display: inline-block; padding: 0.6mm 2.4mm; border-radius: 3mm; background: #f0ecf7; color: #6b3fd4; font-size: 8pt; }
    .servicios li { margin-bottom: 1mm; }
    .firmas td { width: 50%; padding: 18mm 6mm 0; text-align: center; vertical-align: bottom; }
    .linea { border-top: 1px solid #17121f; padding-top: 1mm; }
    .evitar-corte { page-break-inside: avoid; }
</style>
</head>
<body>
<header>
    <table>
        <tr>
            <td><span class="marca">SIGMA</span><br><span class="sub">SISTEMA INTEGRAL DE GESTIÓN Y MANTENIMIENTO DE ASCENSORES</span></td>
            <td style="text-align: right; font-size: 8.5pt;">Contrato<br><b>{{ $contrato->numero }}</b></td>
        </tr>
    </table>
</header>
<footer>
    Contrato {{ $contrato->numero }} · documento generado el {{ now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY, HH:mm') }} por {{ $generadoPor }} desde SIGMA.
</footer>

<h1>Contrato {{ $contrato->numero }}</h1>
<p class="nota">
    <span class="estado">{{ $estados[$contrato->estado] ?? $contrato->estado }}</span>
    @if ($contrato->contratoAnterior) · Renueva el contrato {{ $contrato->contratoAnterior->numero }} @endif
</p>

<h2>Partes y vigencia</h2>
<table class="datos">
    <tr><td class="etiqueta">Cliente</td><td>{{ $contrato->cliente?->nombre }}</td><td class="etiqueta">Documento</td><td>{{ $contrato->cliente?->nit ?: $contrato->cliente?->numero_documento }}</td></tr>
    <tr><td class="etiqueta">Dirección</td><td>{{ $contrato->cliente?->direccion }}</td><td class="etiqueta">Contacto</td><td>{{ collect([$contrato->cliente?->telefono, $contrato->cliente?->correo])->filter()->implode(' · ') }}</td></tr>
    <tr><td class="etiqueta">Desde</td><td>{{ $contrato->fecha_inicio->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}</td><td class="etiqueta">Hasta</td><td>{{ $contrato->fecha_fin->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}</td></tr>
</table>

@if ($contrato->plan)
    <h2>Plan de alquiler del software</h2>
    <table class="datos">
        <tr><td class="etiqueta">Plan</td><td>{{ $contrato->plan->nombre }}</td><td class="etiqueta">Periodicidad</td><td>{{ $contrato->periodicidad?->nombre }}</td></tr>
        <tr>
            <td class="etiqueta">Precio pactado por periodo</td><td>$ {{ number_format((float) $contrato->precio_periodo, 0, ',', '.') }}</td>
            <td class="etiqueta">Periodos en la vigencia</td><td>{{ $contrato->periodos() }}</td>
        </tr>
        @if ($precioCatalogo !== null && round((float) $precioCatalogo) !== round((float) $contrato->precio_periodo))
            <tr><td class="etiqueta">Precio de catálogo</td><td colspan="3">$ {{ number_format((float) $precioCatalogo, 0, ',', '.') }} (ajustado en este contrato)</td></tr>
        @endif
    </table>
    @if (count($contrato->servicios_pactados ?? []))
        <p style="margin: 3mm 0 1mm;"><b>Servicios incluidos</b></p>
        <ul class="servicios">
            @foreach ($contrato->servicios_pactados as $servicio)
                <li>{{ $servicio }}</li>
            @endforeach
        </ul>
    @endif
@endif

<h2>Valor del contrato</h2>
<p class="valor">$ {{ number_format((float) $contrato->valor, 0, ',', '.') }} COP</p>

<h2>Ascensores incluidos ({{ $contrato->equipos->count() }})</h2>
<table class="lista">
    <tr><th>Código</th><th>Ascensor</th><th>Edificio</th><th>Dirección</th></tr>
    @foreach ($contrato->equipos as $equipo)
        <tr>
            <td class="codigo">{{ $equipo->codigo }}</td>
            <td>{{ collect([$equipo->nombre, $equipo->marca])->filter()->implode(' · ') ?: '—' }}</td>
            <td>{{ $equipo->edificio?->nombre }}</td>
            <td>{{ collect([$equipo->edificio?->direccion, $equipo->edificio?->ciudad])->filter()->implode(', ') }}</td>
        </tr>
    @endforeach
</table>

@if ($contrato->asignaciones->isNotEmpty())
    <h2>Programa de mantenimiento</h2>
    <table class="lista">
        <tr><th>Ascensor</th><th>Frecuencia</th><th>Vigencia</th><th class="numero">Valor por visita</th></tr>
        @foreach ($contrato->asignaciones as $asignacion)
            <tr>
                <td class="codigo">{{ $asignacion->equipo?->codigo }}</td>
                <td>{{ $asignacion->periodicidad?->nombre }}</td>
                <td>{{ $asignacion->fecha_inicio->format('d/m/Y') }} – {{ $asignacion->fecha_fin->format('d/m/Y') }}</td>
                <td class="numero">{{ (float) $asignacion->precio > 0 ? '$ '.number_format((float) $asignacion->precio, 0, ',', '.') : 'Incluido' }}</td>
            </tr>
        @endforeach
    </table>
@endif

@if ($contrato->servicios)
    <h2>Alcance de los servicios</h2>
    <div class="texto">{{ $contrato->servicios }}</div>
@endif

@if ($contrato->condiciones)
    <h2>Condiciones</h2>
    <div class="texto">{{ $contrato->condiciones }}</div>
@endif

<table class="firmas evitar-corte">
    <tr>
        <td><div class="linea">Por el prestador del servicio</div></td>
        <td><div class="linea">Por {{ $contrato->cliente?->nombre }}</div></td>
    </tr>
</table>
</body>
</html>
