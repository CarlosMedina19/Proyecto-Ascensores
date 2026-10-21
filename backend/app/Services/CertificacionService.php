<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Certificacion;
use App\Models\Configuracion;
use App\Models\Equipo;
use App\Notifications\AlertaCertificacionNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Certificación anual de cada ascensor: registro con su certificado, estado (vigente, por vencer, vencida)
 * y alertas 60, 30 y 15 días antes del vencimiento (configurables).
 */
class CertificacionService
{
    private const DISCO = 'local';

    public function __construct(private readonly NotificacionService $notificaciones) {}

    public function registrar(Equipo $equipo, array $datos, ?UploadedFile $archivo = null): Certificacion
    {
        return DB::transaction(function () use ($equipo, $datos, $archivo) {
            $certificacion = new Certificacion([
                ...collect($datos)->only(['organismo', 'numero_certificado', 'norma', 'fecha_inspeccion', 'fecha_vencimiento', 'resultado', 'observaciones'])->all(),
                'equipo_id' => $equipo->id,
                'registrado_por' => Auth::id(),
            ]);
            $certificacion->norma ??= 'NTC 5926-1';

            if ($archivo) {
                $certificacion->archivo_ruta = $archivo->store("certificaciones/{$equipo->identificador_uuid}", self::DISCO);
                $certificacion->archivo_nombre = mb_substr($archivo->getClientOriginalName(), 0, 255);
            }
            $certificacion->save();

            $equipo->historial()->create([
                'usuario_id' => Auth::id(),
                'evento' => 'certificacion_registrada',
                'descripcion' => sprintf(
                    'Revisión anual %s por %s. Vence el %s.',
                    mb_strtolower(Certificacion::RESULTADOS[$certificacion->resultado]),
                    $certificacion->organismo,
                    $certificacion->fecha_vencimiento->format('d/m/Y'),
                ),
            ]);
            RegistroAuditoriaService::registrar('creado', $certificacion, $certificacion->toArray(), null, 'certificaciones');

            return $certificacion;
        });
    }

    public function adjuntar(Certificacion $certificacion, UploadedFile $archivo): Certificacion
    {
        $anterior = $certificacion->archivo_ruta;
        $certificacion->update([
            'archivo_ruta' => $archivo->store("certificaciones/{$certificacion->equipo->identificador_uuid}", self::DISCO),
            'archivo_nombre' => mb_substr($archivo->getClientOriginalName(), 0, 255),
        ]);
        if ($anterior) {
            Storage::disk(self::DISCO)->delete($anterior);
        }

        return $certificacion;
    }

    public function rutaArchivo(Certificacion $certificacion): string
    {
        if (! $certificacion->archivo_ruta || ! Storage::disk(self::DISCO)->exists($certificacion->archivo_ruta)) {
            throw new ReglaNegocioException('Esta certificación no tiene el certificado adjunto. Súbalo desde la ficha del ascensor.');
        }

        return Storage::disk(self::DISCO)->path($certificacion->archivo_ruta);
    }

    /** Última certificación de cada ascensor activo, con su estado (incluye los que no tienen ninguna). */
    public function estadoPorEquipo(?int $clienteId = null): Collection
    {
        $equipos = Equipo::query()
            ->with(['edificio.cliente', 'certificaciones'])
            ->where('estado', '!=', 'inactivo')
            ->when($clienteId, fn ($q) => $q->whereHas('edificio', fn ($e) => $e->where('cliente_id', $clienteId)))
            ->orderBy('codigo')
            ->get();

        return $equipos->map(function (Equipo $equipo) {
            $ultima = $equipo->certificaciones->first();

            return [
                'equipo' => $equipo,
                'certificacion' => $ultima,
                'estado' => $ultima ? $ultima->estado() : 'sin_certificar',
                'dias' => $ultima?->diasParaVencer(),
            ];
        });
    }

    /** Avisos 60, 30 y 15 días antes del vencimiento (una vez por umbral y fecha de vencimiento). */
    public function enviarAlertas(): int
    {
        $encolados = 0;
        $umbrales = collect((array) Configuracion::valor('certificaciones.dias_alerta'))->map(fn ($d) => (int) $d)->sortDesc()->values();

        foreach ($this->estadoPorEquipo() as $fila) {
            $certificacion = $fila['certificacion'];
            if (! $certificacion || $fila['dias'] < 0) {
                continue;
            }
            // Umbral más cercano ya alcanzado: con 25 días se envía el aviso de 30 (si no se envió).
            $umbral = $umbrales->filter(fn (int $u) => $fila['dias'] <= $u)->min();
            if ($umbral === null) {
                continue;
            }
            $equipo = $fila['equipo'];
            $encolados += $this->notificaciones->enviar(
                $this->notificaciones->destinatariosEquipo($equipo),
                new AlertaCertificacionNotification($certificacion->setRelation('equipo', $equipo), $fila['dias']),
                "certificacion_{$umbral}d_{$certificacion->fecha_vencimiento->format('Ymd')}",
                'certificacion',
                $certificacion->id,
            );
        }

        return $encolados;
    }
}
