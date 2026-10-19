<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Equipo;
use App\Models\FotoEquipo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Fotos del ascensor: máximo 3 por equipo, en el disco privado (solo se descargan con token).
 */
class FotoEquipoService
{
    private const DISCO = 'local';

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
                $ruta = $archivo->store("equipos/{$equipo->identificador_uuid}", self::DISCO);
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

        Storage::disk(self::DISCO)->delete($foto->ruta);
    }

    public function existe(FotoEquipo $foto): bool
    {
        return Storage::disk(self::DISCO)->exists($foto->ruta);
    }

    public function rutaAbsoluta(FotoEquipo $foto): string
    {
        return Storage::disk(self::DISCO)->path($foto->ruta);
    }
}
