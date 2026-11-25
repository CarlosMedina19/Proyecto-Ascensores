<?php

namespace App\Services\Asistente;

use App\Models\Equipo;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Asistentes de SIGMA: el del panel (con sesión, según el rol) y el de la página pública.
 *
 * Si el agente de n8n está activo (ASISTENTE_N8N_ACTIVO y su URL), la pregunta va a n8n, donde el modelo
 * de IA (DeepSeek) responde. Para consultar datos, el agente usa /asistente/herramienta con un token
 * temporal del mismo usuario: Laravel sigue aplicando los permisos del rol. Si n8n no está activo o falla,
 * responde el motor local (reglas), que entiende las consultas más comunes.
 */
class AsistenteService
{
    public function __construct(
        private readonly HerramientasAsistente $herramientas,
        private readonly AnalisisImagenService $analisisImagen,
    ) {}

    /** @return array{texto: string, acciones: array, sugerencias: array, origen: string} */
    public function panel(Usuario $usuario, string $mensaje, string $sesion, ?UploadedFile $imagen = null): array
    {
        $configuracion = config('services.asistente');
        if (($configuracion['n8n_activo'] ?? false) && filled($configuracion['url_panel'] ?? null)) {
            $respuesta = $this->n8nPanel($usuario, $mensaje, $sesion, $configuracion, $imagen);
            if ($respuesta) {
                return $respuesta;
            }
        }

        if ($imagen) {
            return $this->localImagen($usuario, $mensaje, $imagen) + ['origen' => 'local'];
        }

        return $this->localPanel($usuario, $mensaje) + ['origen' => 'local'];
    }

    /**
     * Foto sin IA: análisis local de la imagen y, si el mensaje nombra un ascensor visible para el usuario,
     * su estado y el enlace a su ficha. La foto no se guarda.
     */
    private function localImagen(Usuario $usuario, string $mensaje, UploadedFile $imagen): array
    {
        $analisis = $this->analisisImagen->analizar($imagen->getRealPath(), $mensaje);
        $acciones = [];
        $codigo = $this->codigoAscensor($mensaje);
        if ($codigo && $usuario->tienePermiso('equipos.ver')) {
            $estado = $this->herramientas->ejecutar($usuario, 'estado_ascensor', ['codigo' => $codigo]);
            $analisis['texto'] .= "\n\n".$estado['texto'];
            $acciones = $estado['acciones'];
        }
        // Repuestos del inventario relacionados con la zona (técnico, coordinador…: quien pueda ver el inventario).
        if ($usuario->tienePermiso('inventario.ver')) {
            $repuestos = $this->analisisImagen->repuestosRelacionados($analisis['zona'], $mensaje);
            if ($repuestos) {
                $analisis['texto'] .= "\n\nRepuestos relacionados en el inventario:\n".implode("\n", array_map(fn ($r) => "• {$r}", $repuestos));
            }
        }
        if ($analisis['estado'] === 'revisar' && $usuario->tienePermiso('ordenes.crear')) {
            $acciones[] = ['tipo' => 'enlace', 'texto' => 'Crear una orden correctiva', 'ruta' => '/ordenes/nueva'];
        }

        return [
            'texto' => $analisis['texto'],
            'acciones' => $acciones,
            'sugerencias' => $analisis['zona'] ? [] : ['Es la puerta de piso', 'Es el cuarto de máquinas', 'Son los cables de tracción'],
            'analisis' => ['estado' => $analisis['estado'], 'zona' => $analisis['zona']],
        ];
    }

    /** @return array{texto: string, acciones: array, sugerencias: array, origen: string} */
    public function publico(string $mensaje, string $sesion): array
    {
        $configuracion = config('services.asistente');
        if (($configuracion['n8n_activo'] ?? false) && filled($configuracion['url_landing'] ?? null) && $this->hayCupoPublico($configuracion)) {
            try {
                $r = Http::withHeaders(['X-Sigma-Clave' => (string) $configuracion['clave']])->acceptJson()
                    ->timeout((int) ($configuracion['tiempo'] ?? 30))
                    ->post($configuracion['url_landing'], ['mensaje' => $mensaje, 'sesion' => 'landing-'.$sesion]);
                $texto = $r->successful() ? trim((string) ($r->json('respuesta') ?? $r->json('output') ?? '')) : '';
                if ($texto !== '') {
                    return ['texto' => Str::limit($texto, 3000), 'acciones' => $this->accionesContacto(), 'sugerencias' => [], 'origen' => 'n8n'];
                }
            } catch (Throwable $e) {
                Log::warning('Asistente público: n8n no respondió', ['error' => $e->getMessage()]);
            }
        }

        return $this->localPublico($mensaje) + ['origen' => 'local'];
    }

    /**
     * Cupo diario del asistente público hacia la IA: cada pregunta cuesta y la página no pide sesión.
     * El contador se reinicia cada día; al agotarse responde el motor local.
     */
    private function hayCupoPublico(array $configuracion): bool
    {
        $limite = (int) ($configuracion['limite_diario_publico'] ?? 0);
        if ($limite <= 0) {
            return true;
        }

        $clave = 'asistente:publico:'.now()->toDateString();
        Cache::add($clave, 0, now()->endOfDay());
        $usadas = Cache::increment($clave);
        if ($usadas === $limite + 1) {
            Log::warning('Asistente público: se alcanzó el tope diario de preguntas a la IA', ['limite' => $limite]);
        }

        return $usadas <= $limite;
    }

    // ---------- n8n ----------

    private function n8nPanel(Usuario $usuario, string $mensaje, string $sesion, array $configuracion, ?UploadedFile $imagen = null): ?array
    {
        // Token temporal (10 min) que solo sirve para /asistente/herramienta y se borra al terminar.
        $token = $usuario->createToken('asistente-n8n', ['asistente'], now()->addMinutes(10));
        $clave = "asistente:acciones:{$token->accessToken->id}";
        try {
            $r = Http::withHeaders(['X-Sigma-Clave' => (string) $configuracion['clave']])->acceptJson()
                ->timeout((int) ($configuracion['tiempo'] ?? 30))
                ->post($configuracion['url_panel'], [
                    'mensaje' => $mensaje,
                    'sesion' => "panel-{$usuario->id}-{$sesion}",
                    'usuario' => ['nombre' => $usuario->nombre, 'rol' => $usuario->rol?->nombre],
                    'acciones_disponibles' => $this->herramientas->disponibles($usuario),
                    'herramientas' => ['url' => rtrim((string) config('app.url'), '/').'/api/v1/asistente/herramienta', 'token' => $token->plainTextToken],
                    // Foto del ascensor para el modelo con visión de DeepSeek (data URL lista para «image_url»).
                    'imagen' => $imagen ? [
                        'tipo' => $imagen->getMimeType(),
                        'url' => 'data:'.$imagen->getMimeType().';base64,'.base64_encode((string) file_get_contents($imagen->getRealPath())),
                        'instruccion' => 'Analiza el estado del componente del ascensor de la foto (desgaste, óxido, fugas, daños, limpieza) y di qué revisar.',
                    ] : null,
                ]);
            $texto = $r->successful() ? trim((string) ($r->json('respuesta') ?? $r->json('output') ?? '')) : '';
            if ($texto === '') {
                Log::warning('Asistente del panel: respuesta vacía o con error de n8n', ['estado' => $r->status()]);

                return null;
            }

            return ['texto' => Str::limit($texto, 4000), 'acciones' => array_slice(Cache::pull($clave, []), 0, 12), 'sugerencias' => [], 'origen' => 'n8n'];
        } catch (Throwable $e) {
            Log::warning('Asistente del panel: n8n no respondió', ['error' => $e->getMessage()]);

            return null;
        } finally {
            $token->accessToken->delete();
            Cache::forget($clave);
        }
    }

    /** Ejecuta una acción pedida por el agente de n8n y guarda sus botones para la respuesta final. */
    public function herramienta(Usuario $usuario, int $tokenId, string $accion, array $parametros): array
    {
        $resultado = $this->herramientas->ejecutar($usuario, $accion, $parametros);
        if ($resultado['acciones']) {
            $clave = "asistente:acciones:{$tokenId}";
            Cache::put($clave, [...Cache::get($clave, []), ...$resultado['acciones']], now()->addMinutes(10));
        }

        return $resultado;
    }

    // ---------- Motor local del panel ----------

    private function localPanel(Usuario $usuario, string $mensaje): array
    {
        $t = $this->normalizar($mensaje);
        $codigo = $this->codigoAscensor($mensaje);
        $numeroOrden = preg_match('/\bOT-\d{4}-\d{3,6}\b/i', $mensaje, $m) ? $m[0] : null;

        $intencion = match (true) {
            $t === '' || (bool) preg_match('/^(hola|buen[oa]s|ayuda|que puedes|qué puedes|menu|opciones|hey)\b/u', $t) => ['ayuda', []],
            str_contains($t, 'pdf') || str_contains($t, 'informe') || str_contains($t, 'descarg') || str_contains($t, 'planilla') => match (true) {
                (bool) $numeroOrden => ['pdf_informe_orden', ['numero' => $numeroOrden]],
                str_contains($t, 'contrato') => ['pdf_contrato', ['numero' => $this->numeroContrato($mensaje)]],
                str_contains($t, 'indicador') => ['pdf_indicadores', []],
                (bool) $codigo => ['pdf_planilla_visita', ['codigo' => $codigo]],
                default => ['ayuda_pdf', []],
            },
            str_contains($t, 'emergencia') => ['emergencias_abiertas', []],
            str_contains($t, 'cartera') || str_contains($t, 'factura') || str_contains($t, 'vencid') || str_contains($t, 'deuda') => ['cartera_vencida', []],
            (str_contains($t, 'orden') && ! str_contains($t, 'buscar')) => $numeroOrden ? ['buscar', ['texto' => $numeroOrden]] : ['ordenes_activas', []],
            str_contains($t, 'mantenimiento') || str_contains($t, 'proxim') || str_contains($t, 'programad') || str_contains($t, 'agenda') => ['proximos_mantenimientos', ['dias' => preg_match('/(\d{1,2})\s*dias?/u', $t, $d) ? (int) $d[1] : 7]],
            $codigo !== null && ! str_starts_with($t, 'buscar') => ['estado_ascensor', ['codigo' => $codigo]],
            default => ['buscar', ['texto' => $this->textoBusqueda($mensaje)]],
        };

        [$accion, $parametros] = $intencion;
        if ($accion === 'ayuda' || $accion === 'ayuda_pdf') {
            $disponibles = $this->herramientas->disponibles($usuario);
            $lineas = collect($disponibles)->map(fn ($d) => '• '.Str::before($d, ' (parámetro'))->values()->all();

            return [
                'texto' => ($accion === 'ayuda_pdf'
                    ? 'Puedo darle estos PDF: informe de una orden (escriba su número, ej. OT-2026-00013), contrato (su número), indicadores o la última planilla de un ascensor (su código).'
                    : "Hola, {$usuario->nombre}. Soy el asistente de SIGMA. Según su rol puedo ayudarle con:\n".implode("\n", $lineas)),
                'acciones' => [],
                'sugerencias' => $this->sugerencias($disponibles),
            ];
        }

        $resultado = $this->herramientas->ejecutar($usuario, $accion, $parametros);

        return $resultado + ['sugerencias' => []];
    }

    private function sugerencias(array $disponibles): array
    {
        $mapa = [
            'ordenes_activas' => 'Mis órdenes activas',
            'proximos_mantenimientos' => 'Mantenimientos de esta semana',
            'emergencias_abiertas' => 'Emergencias abiertas',
            'cartera_vencida' => 'Facturas vencidas',
            'pdf_indicadores' => 'PDF de indicadores',
        ];

        return array_values(array_intersect_key($mapa, $disponibles));
    }

    // ---------- Motor local de la página pública ----------

    private function localPublico(string $mensaje): array
    {
        $t = $this->normalizar($mensaje);
        $contacto = config('services.asistente.contacto');
        $respuestas = [
            'precio' => 'SIGMA se alquila con un solo plan que incluye todo (panel web, app para técnicos, informes PDF y QR por ascensor). El valor depende del tiempo de alquiler y del número de ascensores: entre más meses, mejor precio. Escríbanos y le enviamos una cotización.',
            'demo' => 'Con gusto le mostramos SIGMA con sus propios datos. Escríbanos por WhatsApp o por correo y agendamos la demostración.',
            'app' => 'Los técnicos usan la app móvil (Android): ven sus órdenes, escanean el QR del ascensor, diligencian la planilla de asistencia técnica con firmas y el cliente recibe el informe en PDF.',
            'qr' => 'Cada ascensor tiene un código QR único. Cualquier persona que lo escanee ve el estado del ascensor; el técnico, con su sesión, inicia el mantenimiento o reporta una anomalía.',
            'seguridad' => 'Sí. Cada usuario entra con su cuenta y su rol define a qué accede; los clientes solo ven lo suyo, todo queda en la auditoría y hay copias de seguridad diarias.',
            'funciones' => 'SIGMA organiza clientes, edificios y ascensores; programa los mantenimientos en días hábiles y los asigna a técnicos según su horario; registra emergencias 24/7, cotizaciones, inventario de repuestos, cartera, certificación anual e indicadores, y entrega informes en PDF.',
            'quien' => 'SIGMA está pensado para empresas de mantenimiento de ascensores, administraciones de conjuntos y edificios, y técnicos en campo.',
            'saludo' => 'Hola, soy el asistente de SIGMA, el sistema para gestionar el mantenimiento de ascensores. Puedo contarle qué hace, cómo funciona la app, el plan y cómo contactarnos. ¿Qué le gustaría saber?',
        ];
        $clave = match (true) {
            (bool) preg_match('/precio|costo|cuanto|valor|plan|tarifa|cotiza/u', $t) => 'precio',
            (bool) preg_match('/demo|demostracion|contact|whatsapp|correo|llamar|telefono|asesor/u', $t) => 'demo',
            (bool) preg_match('/app|movil|celular|android|tecnico/u', $t) => 'app',
            (bool) preg_match('/\bqr\b|codigo/u', $t) => 'qr',
            (bool) preg_match('/segur|datos|privacidad|respaldo/u', $t) => 'seguridad',
            (bool) preg_match('/para quien|empresa|conjunto|administra/u', $t) => 'quien',
            (bool) preg_match('/que es|que hace|funcion|sirve|modulo|caracteristica/u', $t) => 'funciones',
            default => 'saludo',
        };
        $texto = $respuestas[$clave];
        if (in_array($clave, ['precio', 'demo'], true)) {
            $texto .= " WhatsApp: {$contacto['whatsapp_visible']} · Correo: {$contacto['correo']}.";
        }

        return [
            'texto' => $texto,
            'acciones' => $this->accionesContacto(),
            'sugerencias' => ['¿Qué hace SIGMA?', '¿Cómo funciona la app?', '¿Cuánto cuesta?', 'Quiero una demostración'],
        ];
    }

    private function accionesContacto(): array
    {
        $contacto = config('services.asistente.contacto');

        return [
            ['tipo' => 'externo', 'texto' => 'Escribir por WhatsApp', 'ruta' => 'https://wa.me/'.$contacto['whatsapp'].'?text='.rawurlencode('Hola, quiero información sobre SIGMA.')],
            ['tipo' => 'externo', 'texto' => 'Enviar correo', 'ruta' => 'mailto:'.$contacto['correo'].'?subject='.rawurlencode('Información de SIGMA')],
        ];
    }

    // ---------- Utilidades ----------

    private function normalizar(string $texto): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower(Str::ascii($texto))));
    }

    /** Código de ascensor tal como se escribió (ej. ASC-101) si existe un ascensor con ese código. */
    private function codigoAscensor(string $mensaje): ?string
    {
        if (! preg_match_all('/\b[A-Za-z]{2,5}-?\d{2,5}\b|\b\d{4,8}\b/u', $mensaje, $coincidencias)) {
            return null;
        }
        foreach ($coincidencias[0] as $candidato) {
            if (! preg_match('/^OT-/i', $candidato) && Equipo::whereRaw('upper(codigo) = ?', [mb_strtoupper($candidato)])->exists()) {
                return $candidato;
            }
        }

        return null;
    }

    private function numeroContrato(string $mensaje): string
    {
        return preg_match('/\b[A-Z]{1,5}-?\d{2,4}-?\d{0,6}\b/i', Str::after($mensaje, 'contrato'), $m) ? $m[0] : '';
    }

    private function textoBusqueda(string $mensaje): string
    {
        $texto = preg_replace('/^\s*(buscar|busca|busque|encuentra|encontrar|donde esta|dónde está|muestrame|muéstrame)\s+/iu', '', $mensaje);

        return trim((string) preg_replace('/[¿?¡!.]/u', '', (string) $texto));
    }
}
