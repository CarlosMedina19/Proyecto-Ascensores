<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Informe {{ $orden->numero }} v{{ $version }}</title>
<style>
    @page { margin: 26mm 16mm 20mm 16mm; }
    /* Helvetica y Courier son fuentes estándar del PDF: no se incrustan y el informe se genera en menos de un segundo. */
    * { font-family: Helvetica, Arial, sans-serif; }
    body { font-size: 9.5pt; color: #17121f; }
    header { position: fixed; top: -19mm; left: 0; right: 0; height: 14mm; border-bottom: 2px solid #6b3fd4; }
    footer { position: fixed; bottom: -14mm; left: 0; right: 0; height: 9mm; font-size: 7.5pt; color: #655e74; border-top: 1px solid #e6e0f0; padding-top: 2mm; }
    .marca { font-size: 15pt; font-weight: bold; color: #6b3fd4; }
    .sub { font-size: 7.5pt; color: #655e74; letter-spacing: 1px; }
    h1 { font-size: 15pt; margin: 0 0 2mm; }
    h2 { font-size: 11pt; color: #6b3fd4; margin: 6mm 0 2mm; border-bottom: 1px solid #e6e0f0; padding-bottom: 1mm; }
    table { width: 100%; border-collapse: collapse; }
    .datos td { padding: 1.6mm 2mm; border: 1px solid #e6e0f0; vertical-align: top; }
    .datos .etiqueta { width: 22%; background: #f8f6fc; color: #655e74; font-size: 8pt; }
    .lista th { background: #f0ecf7; text-align: left; font-size: 8pt; padding: 1.6mm 2mm; border: 1px solid #e6e0f0; }
    .lista td { padding: 1.4mm 2mm; border: 1px solid #e6e0f0; vertical-align: top; }
    .codigo { font-family: Courier, monospace; font-size: 8pt; color: #655e74; }
    .centro { text-align: center; }
    .estado { font-weight: bold; text-align: center; width: 9mm; }
    .B { color: #1f9d5c; } .R { color: #c27508; } .M { color: #d9344a; }
    .resumen td { padding: 2mm; text-align: center; border: 1px solid #e6e0f0; width: 25%; }
    .resumen .numero { font-size: 14pt; font-weight: bold; }
    .texto { white-space: pre-line; line-height: 1.45; }
    .fotos td { width: 50%; padding: 2mm; vertical-align: top; text-align: center; }
    .fotos img { max-width: 100%; max-height: 70mm; }
    .fotos .pie { font-size: 7.5pt; color: #655e74; }
    .firmas td { width: 50%; padding: 2mm 4mm; vertical-align: bottom; text-align: center; }
    .firmas img { max-width: 70mm; max-height: 28mm; }
    .linea { border-top: 1px solid #17121f; margin-top: 1mm; padding-top: 1mm; }
    .nota { font-size: 8pt; color: #655e74; }
    .evitar-corte { page-break-inside: avoid; }
</style>
</head>
<body>
<header>
    <table>
        <tr>
            <td><span class="marca">SIGMA</span><br><span class="sub">SISTEMA INTEGRAL DE GESTIÓN Y MANTENIMIENTO DE ASCENSORES</span></td>
            <td style="text-align: right; font-size: 8.5pt;">Informe técnico<br><b>{{ $orden->numero }}</b> · versión {{ $version }}</td>
        </tr>
    </table>
</header>
<footer>
    Informe {{ $orden->numero }} versión {{ $version }} · generado el {{ now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY, HH:mm') }} por {{ $generadoPor }}.
    Un informe emitido no se modifica; las correcciones generan una versión nueva.
</footer>

<h1>Informe de {{ ['preventivo' => 'mantenimiento preventivo', 'correctivo' => 'mantenimiento correctivo', 'inspeccion' => 'inspección'][$orden->servicio] ?? $orden->servicio }}</h1>
@if ($motivo)
    <p class="nota">Versión {{ $version }}: {{ $motivo }}</p>
@endif

<h2>Datos del servicio</h2>
<table class="datos">
    <tr><td class="etiqueta">Cliente</td><td>{{ $orden->cliente?->nombre }}</td><td class="etiqueta">Orden</td><td>{{ $orden->numero }}</td></tr>
    <tr><td class="etiqueta">Edificio</td><td>{{ $orden->edificio?->nombre }}</td><td class="etiqueta">Dirección</td><td>{{ collect([$orden->edificio?->direccion, $orden->edificio?->ciudad])->filter()->implode(', ') }}</td></tr>
    <tr><td class="etiqueta">Ascensor</td><td>{{ $orden->equipo?->codigo }} {{ $orden->equipo?->ubicacion ? '· '.$orden->equipo->ubicacion : '' }}</td><td class="etiqueta">Marca y modelo</td><td>{{ collect([$orden->equipo?->marca, $orden->equipo?->modelo])->filter()->implode(' · ') }}</td></tr>
    <tr><td class="etiqueta">Fecha programada</td><td>{{ $orden->fecha->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }} · {{ substr($orden->hora, 0, 5) }}</td><td class="etiqueta">Técnicos</td><td>{{ $orden->tecnicos->map(fn ($t) => $t->usuario?->nombre)->filter()->implode(', ') }}</td></tr>
    <tr><td class="etiqueta">Inicio</td><td>{{ $orden->inicio_en?->locale('es')->isoFormat('D MMM YYYY, HH:mm') }}</td><td class="etiqueta">Finalización</td><td>{{ $orden->fin_en?->locale('es')->isoFormat('D MMM YYYY, HH:mm') }}</td></tr>
    <tr><td class="etiqueta">Duración</td><td>{{ $orden->duracion_minutos !== null ? intdiv($orden->duracion_minutos, 60).' h '.($orden->duracion_minutos % 60).' min' : '—' }}</td><td class="etiqueta">Prioridad</td><td>{{ ['baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta', 'critica' => 'Crítica'][$orden->prioridad] ?? $orden->prioridad }}</td></tr>
</table>

@if ($orden->descripcion)
    <h2>Trabajo solicitado</h2>
    <div class="texto">{{ $orden->descripcion }}</div>
@endif

@if (count($secciones))
    <h2>Resultado del checklist</h2>
    <table class="resumen">
        <tr>
            <td><div class="numero">{{ $conteo['B'] + $conteo['R'] + $conteo['M'] }}</div>ítems revisados</td>
            <td><div class="numero B">{{ $conteo['B'] }}</div>Bueno (B)</td>
            <td><div class="numero R">{{ $conteo['R'] }}</div>Regular (R)</td>
            <td><div class="numero M">{{ $conteo['M'] }}</div>Malo (M)</td>
        </tr>
    </table>
    <p class="nota">Resultado de cada ítem: INS, EST o N.A. Estado: B (Bueno), R (Regular) o M (Malo). Los ítems en R o M llevan observación.</p>

    @foreach ($secciones as $seccion => $filas)
        <table class="lista evitar-corte" style="margin-top: 3mm;">
            <tr><th colspan="5">{{ $seccion }}</th></tr>
            <tr><th style="width: 15mm;">Código</th><th>Ítem</th><th class="centro" style="width: 13mm;">Resultado</th><th class="centro" style="width: 11mm;">Estado</th><th>Observación</th></tr>
            @foreach ($filas as $fila)
                <tr>
                    <td class="codigo">{{ $fila->item->codigo }}</td>
                    <td>{{ $fila->item->nombre }}</td>
                    <td class="centro">{{ $fila->resultado === 'NA' ? 'N.A.' : $fila->resultado }}</td>
                    <td class="estado {{ $fila->estado }}">{{ $fila->estado }}</td>
                    <td>
                        {{ $fila->observacion }}
                        @foreach ($fila->correctivos as $correctivo)
                            <br><span class="nota">Correctivo {{ $correctivo->numero }}</span>
                        @endforeach
                    </td>
                </tr>
            @endforeach
        </table>
    @endforeach
@endif

<h2>Informe del trabajo realizado</h2>
<div class="texto">{{ $orden->informe }}</div>

@if ($orden->observaciones)
    <h2>Observaciones</h2>
    <div class="texto">{{ $orden->observaciones }}</div>
@endif

@if ($orden->planilla?->recomendaciones)
    <h2>Recomendaciones</h2>
    <div class="texto">{{ $orden->planilla->recomendaciones }}</div>
@endif

@if ($fotos->isNotEmpty())
    <h2>Registro fotográfico</h2>
    <table class="fotos">
        @foreach ($fotos->chunk(2) as $par)
            <tr class="evitar-corte">
                @foreach ($par as $foto)
                    <td><img src="{{ $foto['imagen'] }}" alt=""><div class="pie">{{ $foto['texto'] }}</div></td>
                @endforeach
                @if ($par->count() === 1)<td></td>@endif
            </tr>
        @endforeach
    </table>
@endif

<h2>Firmas</h2>
<table class="firmas evitar-corte">
    <tr>
        @foreach ($firmas as $firma)
            <td>
                @if ($firma['imagen'])<img src="{{ $firma['imagen'] }}" alt="">@endif
                <div class="linea">
                    <b>{{ $firma['nombre'] }}</b>{{ $firma['documento'] ? ' · '.$firma['documento'] : '' }}<br>
                    {{ $firma['tipo'] }}{{ $firma['cargo'] ? ' · '.$firma['cargo'] : '' }}<br>
                    <span class="nota">{{ $firma['fecha']?->locale('es')->isoFormat('D MMM YYYY, HH:mm') }}</span>
                </div>
            </td>
        @endforeach
    </tr>
</table>
</body>
</html>
