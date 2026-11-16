<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Equipo;
use App\Models\FotoEquipo;
use App\Support\Archivos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fotos del ascensor: máximo 3 por equipo, en el disco privado (solo se descargan con token).
 */
class FotoEquipoService
{
    /**
     * @param  array<int, UploadedFile>  $archivos
     * @return Collection<int, FotoEquipo>
     */
    public function agregar(Equipo $equipo, array $archivos): Collection
    {
        return DB::transaction(function () use ($equipo, $archivos) {
            // Bloquea el equipo para que dos subidas a la vez no pasen del máximo.
            Equipo::whereKey($equipo->id)->lockForUpdate()->first();
            $actuales = $equipo->fotos()->count();

            if ($actuales + count($archivos) > FotoEquipo::MAXIMO_POR_EQUIPO) {
                $disponibles = max(FotoEquipo::MAXIMO_POR_EQUIPO - $actuales, 0);
                throw new ReglaNegocioException(
                    'El ascensor admite máximo '.FotoEquipo::MAXIMO_POR_EQUIPO." fotos. Puede agregar {$disponibles} más."
                );
            }

            $orden = (int) $equipo->fotos()->max('orden');
            $guardadas = collect();

            foreach ($archivos as $archivo) {
                $ruta = $archivo->store("equipos/{$equipo->identificador_uuid}", Archivos::nombreDisco());
                $guardadas->push(FotoEquipo::create([
                    'equipo_id' => $equipo->id,
                    'ruta' => $ruta,
                    'nombre_original' => mb_substr($archivo->getClientOriginalName(), 0, 255),
                    'tipo_mime' => $archivo->getMimeType(),
                    'tamano' => $archivo->getSize(),
                    'orden' => ++$orden,
                    'usuario_id' => Auth::id(),
                ]));
            }

            $equipo->historial()->create([
                'usuario_id' => Auth::id(),
                'evento' => 'fotos_agregadas',
                'descripcion' => count($archivos) === 1 ? 'Se agregó 1 foto del ascensor.' : 'Se agregaron '.count($archivos).' fotos del ascensor.',
            ]);
            RegistroAuditoriaService::registrar('fotos_agregadas', $equipo, ['fotos' => $guardadas->pluck('nombre_original')->all()]);

            return $guardadas;
        });
    }

    public function eliminar(FotoEquipo $foto): void
    {
        DB::transaction(function () use ($foto) {
            $foto->delete();
            RegistroAuditoriaService::registrar('foto_eliminada', $foto->equipo, ['foto' => $foto->nombre_original]);
        });

        Archivos::disco()->delete($foto->ruta);
    }

    public function existe(FotoEquipo $foto): bool
    {
        return Archivos::disco()->exists($foto->ruta);
    }

    public function responder(FotoEquipo $foto): StreamedResponse
    {
        return Archivos::responder($foto->ruta, $foto->nombre_original ?: basename($foto->ruta), $foto->tipo_mime);
    }
}
