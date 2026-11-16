<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Planilla de asistencia técnica N.º {{ $visita->numero }}</title>
<style>
    @page { margin: 7mm 9mm 8mm 9mm; }
    @font-face { font-family: 'Barlow'; font-weight: bold; src: url('{{ resource_path('fuentes/Barlow-600.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Geist'; font-weight: 400; src: url('{{ resource_path('fuentes/Geist-400.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Geist'; font-weight: bold; src: url('{{ resource_path('fuentes/Geist-600.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Michroma'; font-weight: 400; src: url('{{ resource_path('fuentes/Michroma-400.ttf') }}') format('truetype'); }
    * { font-family: 'Geist', Helvetica, sans-serif; }
    body { font-size: 7pt; color: #1b2433; margin: 0; line-height: 1.15; }
    table { border-collapse: collapse; width: 100%; }
    .cabecera { background: #14294a; color: #fff; border-radius: 10px; }
    .cabecera td { padding: 2mm 4mm; vertical-align: middle; }
    .logo-caja { background: #0d1626; border-radius: 6px; padding: 2mm 3mm; }
    .logo-caja img { width: 10mm; height: 10mm; vertical-align: middle; }
    .marca { font-family: 'Michroma', sans-serif; font-size: 12pt; color: #fff; letter-spacing: 1.5px; vertical-align: middle; padding-left: 2mm; }
    .marca-sub { font-size: 6.5pt; color: #b9c3d6; letter-spacing: 2px; }
    .titulo { font-family: 'Barlow', sans-serif; font-weight: bold; font-size: 14.5pt; text-align: right; line-height: 1.05; }
    .titulo-sub { font-size: 7pt; text-align: right; color: #c9d3e6; letter-spacing: 0.5px; margin-top: 1mm; }
    .franja { background: #1f5c99; color: #fff; font-weight: bold; font-size: 7.2pt; margin-top: 1.6mm; border-radius: 5px; }
    .franja td { padding: 1.1mm 4mm; }
    .datos { margin-top: 1.8mm; border: 1px solid #9fb3cf; border-radius: 8px; }
    .datos td { padding: 0.5mm 3mm; vertical-align: middle; }
    .etq { font-weight: bold; font-size: 7.6pt; width: 18mm; }
    .linea { border-bottom: 1px solid #6d7f99; }
    .fecha { border-left: 1px solid #9fb3cf; }
    .fecha-tit { color: #1f5c99; font-weight: bold; font-size: 8pt; }
    .mini { font-size: 6pt; color: #4b5a70; font-weight: bold; }
    .tipos { margin-top: 1.6mm; background: #e4ecf6; border-radius: 6px; }
    .tipos td { padding: 1.2mm 4mm; font-weight: bold; font-size: 7.6pt; text-align: center; }
    .casilla { display: inline-block; width: 3.4mm; height: 3.4mm; border: 1.2px solid #1f5c99; text-align: center; line-height: 3.2mm; font-size: 7pt; vertical-align: middle; margin-left: 1.5mm; }
    .rejilla { margin-top: 1.6mm; }
    .rejilla > tbody > tr > td { vertical-align: top; width: 50%; }
    .seccion { margin-bottom: 0.9mm; border: 1px solid #9fb3cf; }
    .seccion .nombre { background: #14294a; color: #fff; font-weight: bold; font-size: 6.9pt; padding: 0.8mm 2mm; text-transform: uppercase; }
    .seccion th { background: #dfe7f2; font-size: 6pt; text-align: center; padding: 0.4mm 1mm; border: 1px solid #b4c4da; }
    .seccion th.act { text-align: left; }
    .seccion td { border: 1px solid #c8d4e4; padding: 0.1mm 1.3mm; font-size: 6.2pt; }
    .seccion td.c { text-align: center; width: 6.5mm; font-weight: bold; }
    .B { color: #1b7f4a; } .R { color: #b56a00; } .M { color: #c52b3f; }
    .convenciones { margin-top: 1mm; background: #14294a; color: #fff; border-radius: 5px; font-size: 6.6pt; }
    .convenciones td { padding: 1mm 4mm; }
    .caja { margin-top: 1.6mm; border: 1px solid #9fb3cf; border-radius: 8px; }
    .caja td { padding: 1.4mm 3.5mm; vertical-align: top; }
    .caja-tit { font-weight: bold; font-size: 8pt; }
    .obs { font-size: 6.6pt; line-height: 1.2; }
    .firmas td.firma { width: 50%; padding: 0 1.5mm; vertical-align: top; }
    .firma-caja { border: 1px solid #9fb3cf; border-radius: 8px; padding: 1.2mm 3.5mm; height: 17mm; }
    .firma-tit { color: #1f5c99; font-weight: bold; font-size: 7.6pt; }
    .firma-img { height: 9.5mm; text-align: center; border-bottom: 1px solid #6d7f99; }
    .firma-img img { max-height: 9mm; max-width: 60mm; }
    .pie { margin-top: 1.2mm; font-size: 6.4pt; color: #4b5a70; }
</style>
</head>
<body>
@php
    $tipos = \App\Models\VisitaTecnica::TIPOS;
    $equipo = $visita->equipo;
    $edificio = $equipo?->edificio;
    $observadas = collect($visita->secciones)->flatMap(fn ($s) => collect($s['items'])->filter(fn ($i) => ! empty($i['observacion']))->map(fn ($i) => $s['nombre'].' · '.$i['nombre'].' ('.$i['estado'].'): '.$i['observacion']));
@endphp

<table class="cabecera"><tr>
    <td style="width: 52%">
        <span class="logo-caja"><img src="{{ $logo }}" alt="SIGMA"><span class="marca">SIGMA</span></span>
        <div class="marca-sub" style="margin-top: 1.2mm">GESTIÓN DE ASCENSORES</div>
    </td>
    <td>
        <div class="titulo">PLANILLA DE<br>ASISTENCIA TÉCNICA</div>
        <div class="titulo-sub">CONTROL DE MANTENIMIENTO Y REPARACIONES</div>
    </td>
</tr></table>

<table class="franja"><tr>
    <td>{{ mb_strtoupper($entidad) }}</td>
    <td style="text-align: right">N.º {{ $visita->numero }}</td>
</tr></table>

<table class="datos"><tr>
    <td style="width: 60%">
        <table>
            <tr><td class="etq">EDIFICIO:</td><td class="linea">{{ $edificio?->nombre }} · Ascensor {{ $equipo?->codigo }}</td></tr>
            <tr><td class="etq">DIRECCIÓN:</td><td class="linea">{{ collect([$edificio?->direccion, $edificio?->ciudad])->filter()->join(', ') }}</td></tr>
            <tr><td class="etq">TÉCNICO:</td><td class="linea">{{ $visita->tecnico?->nombre }}</td></tr>
        </table>
    </td>
    <td class="fecha">
        <div class="fecha-tit">FECHA DE SERVICIO</div>
        <table style="margin-top: 1mm">
            <tr><td class="mini">DÍA</td><td class="mini">MES</td><td class="mini">AÑO</td></tr>
            <tr><td class="linea">{{ $visita->salida_en->format('d') }}</td><td class="linea">{{ $visita->salida_en->format('m') }}</td><td class="linea">{{ $visita->salida_en->format('Y') }}</td></tr>
        </table>
        <table style="margin-top: 1mm">
            <tr><td class="mini">ENTRADA:</td><td class="linea">{{ $visita->entrada_en->timezone(config('app.timezone'))->format('h:i a') }}</td><td class="mini">SALIDA:</td><td class="linea">{{ $visita->salida_en->timezone(config('app.timezone'))->format('h:i a') }}</td></tr>
        </table>
    </td>
</tr></table>

<table class="tipos"><tr>
    @foreach ($tipos as $clave => $nombre)
        <td>{{ mb_strtoupper($nombre) }} <span class="casilla">{{ $visita->tipo_servicio === $clave ? 'X' : '' }}</span></td>
    @endforeach
</tr></table>

<table class="rejilla"><tr>
    @foreach ($columnas as $k => $columna)
        <td style="{{ $k === 0 ? 'padding-right: 1.5mm' : 'padding-left: 1.5mm' }}">
            @foreach ($columna as $seccion)
                <table class="seccion">
                    <tr><td colspan="4" class="nombre">{{ $seccion['nombre'] }}</td></tr>
                    <tr><th class="act">ACTIVIDAD</th><th>INS</th><th>EST</th><th>N.A.</th></tr>
                    @foreach ($seccion['items'] as $item)
                        <tr>
                            <td>{{ $item['nombre'] }}</td>
                            <td class="c">{{ ! empty($item['ins']) ? 'X' : '' }}</td>
                            <td class="c {{ $item['estado'] ?? '' }}">{{ $item['estado'] ?? '' }}</td>
                            <td class="c">{{ ! empty($item['na']) ? 'X' : '' }}</td>
                        </tr>
                    @endforeach
                </table>
            @endforeach
        </td>
    @endforeach
</tr></table>

<table class="convenciones"><tr>
    <td><b>CONVENCIONES:</b> &nbsp; INS = Inspección &nbsp;|&nbsp; EST = Estado &nbsp;|&nbsp; B = Bueno &nbsp; R = Regular &nbsp; M = Malo &nbsp;|&nbsp; N.A. = No aplica</td>
</tr></table>

<table class="caja"><tr><td>
    <div class="caja-tit">OBSERVACIONES:</div>
    <div class="obs">
        {{ $visita->observaciones ?: ($observadas->isEmpty() ? 'Sin observaciones.' : '') }}
        @foreach ($observadas as $linea)<br>• {{ $linea }}@endforeach
        @if (! empty($visita->repuestos))
            <br><b>Repuestos utilizados:</b> {{ collect($visita->repuestos)->map(fn ($r) => ($r['cantidad'] ?? 1).' × '.($r['nombre'] ?? ''))->join(', ') }}
        @endif
    </div>
</td></tr></table>

<table class="firmas" style="margin-top: 1.6mm"><tr>
    <td class="firma" style="padding-left: 0">
        <div class="firma-caja">
            <div class="firma-tit">RECIBIDO POR EL EDIFICIO</div>
            <div class="firma-img">@if ($firmaRecibe)<img src="{{ $firmaRecibe }}" alt="Firma">@endif</div>
            <div class="mini" style="margin-top: 1mm">FIRMA Y C.C.: <span style="font-weight: normal; color: #1b2433">{{ $visita->recibe_nombre }} · C.C. {{ $visita->recibe_documento }}</span></div>
        </div>
    </td>
    <td class="firma" style="padding-right: 0">
        <div class="firma-caja">
            <div class="firma-tit">REALIZADO POR (TÉCNICO)</div>
            <div class="firma-img">@if ($firmaTecnico)<img src="{{ $firmaTecnico }}" alt="Firma">@endif</div>
            <div class="mini" style="margin-top: 1mm">FIRMA Y C.C.: <span style="font-weight: normal; color: #1b2433">{{ $visita->tecnico?->nombre }}{{ $visita->tecnico_documento ? ' · C.C. '.$visita->tecnico_documento : '' }}</span></div>
        </div>
    </td>
</tr></table>

<table class="pie"><tr>
    <td><b>SIGMA – GESTIÓN DE ASCENSORES</b></td>
    <td style="text-align: right">{{ mb_strtoupper($entidad) }}</td>
</tr></table>
</body>
</html>
