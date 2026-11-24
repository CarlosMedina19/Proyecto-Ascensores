<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Autenticacion\CambiarContrasenaRequest;
use App\Http\Requests\Autenticacion\IniciarSesionRequest;
use App\Http\Requests\Autenticacion\RecuperarContrasenaRequest;
use App\Http\Requests\Autenticacion\RestablecerContrasenaRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\Usuario;
use App\Services\AutenticacionService;
use App\Services\RegistroAuditoriaService;
use App\Support\Telefono;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * RF-001: autenticación.
 */
class AutenticacionController extends Controller
{
    public function __construct(
        protected AutenticacionService $servicioAutenticacion
    ) {}

    public function iniciarSesion(IniciarSesionRequest $solicitud): JsonResponse
    {
        $resultado = $this->servicioAutenticacion->iniciarSesion(
            $solicitud->validated('correo'),
            $solicitud->validated('password'),
            $solicitud->validated('dispositivo'),
            $solicitud->validated('dispositivo_confianza'),
        );

        if (isset($resultado['verificacion'])) {
            return response()->json([
                'requiere_verificacion' => true,
                'mensaje' => "Enviamos un código de 6 dígitos a {$resultado['verificacion']['destino']}.",
                ...$resultado['verificacion'],
            ]);
        }

        return $this->respuestaSesion($resultado);
    }

    /** Segundo paso del inicio de sesión: el código que llegó al correo. */
    public function verificarInicioSesion(Request $solicitud): JsonResponse
    {
        $datos = $solicitud->validate([
            'desafio' => ['required', 'string', 'max:100'],
            'codigo' => ['required', 'string', 'max:12'],
            'dispositivo' => ['nullable', 'string', 'max:100'],
        ], [], ['codigo' => 'código']);

        return $this->respuestaSesion($this->servicioAutenticacion->verificarInicioSesion($datos['desafio'], $datos['codigo'], $datos['dispositivo'] ?? null));
    }

    public function reenviarCodigo(Request $solicitud): JsonResponse
    {
        $datos = $solicitud->validate(['desafio' => ['required', 'string', 'max:100'], 'proposito' => ['nullable', 'in:inicio_sesion,recuperacion']]);
        $resultado = ($datos['proposito'] ?? 'inicio_sesion') === 'recuperacion'
            ? $this->servicioAutenticacion->reenviarCodigoTelefono($datos['desafio'])
            : $this->servicioAutenticacion->reenviarCodigoInicioSesion($datos['desafio']);

        return response()->json(['mensaje' => "Enviamos un código nuevo a {$resultado['destino']}.", ...$resultado]);
    }

    private function respuestaSesion(array $resultado): JsonResponse
    {
        return response()->json([
            'usuario' => new UsuarioResource($resultado['usuario']->load(['rol.permisos', 'cliente'])),
            'token_acceso' => $resultado['token'],
            'tipo_token' => 'Bearer',
            'minutos_inactividad' => config('sigma.sesion.minutos_inactividad'),
            // Llave de este dispositivo: se envía en los próximos ingresos para no pedir de nuevo el código.
            ...(isset($resultado['confianza']) ? ['dispositivo_confianza' => $resultado['confianza']] : []),
        ]);
    }

    public function olvidarDispositivos(Request $solicitud): JsonResponse
    {
        $borrados = $this->servicioAutenticacion->olvidarDispositivos($solicitud->user());

        return response()->json(['mensaje' => $borrados
            ? 'Listo: en el próximo ingreso cada dispositivo volverá a pedir el código.'
            : 'No había dispositivos de confianza guardados.']);
    }

    public function cerrarSesion(Request $solicitud): JsonResponse
    {
        $this->servicioAutenticacion->cerrarSesion($solicitud->user());

        return response()->json(['mensaje' => 'Sesión cerrada correctamente.']);
    }

    public function perfil(Request $solicitud): UsuarioResource
    {
        return new UsuarioResource($solicitud->user()->load(['rol.permisos', 'cliente'])->loadCount(['dispositivosConfianza' => fn ($c) => $c->where('expira_en', '>', now())]));
    }

    public function cambiarContrasena(CambiarContrasenaRequest $solicitud): JsonResponse
    {
        $this->servicioAutenticacion->cambiarContrasena(
            $solicitud->user(),
            $solicitud->validated('contrasena_actual'),
            $solicitud->validated('password')
        );

        return response()->json(['mensaje' => 'Contraseña actualizada. Se cerraron las demás sesiones abiertas.']);
    }

    public function recuperarContrasena(RecuperarContrasenaRequest $solicitud): JsonResponse
    {
        $this->servicioAutenticacion->enviarEnlaceRecuperacion($solicitud->validated('correo'));

        return response()->json([
            'mensaje' => 'Si el correo está registrado, recibirá un enlace para restablecer la contraseña.',
        ]);
    }

    /** Recuperación con el número de teléfono (paso 1): envía un código por SMS. */
    public function recuperarConTelefono(Request $solicitud): JsonResponse
    {
        $datos = $solicitud->validate(['telefono' => ['required', 'string', 'max:20']], [], ['telefono' => 'número de celular']);
        if (Telefono::normalizar($datos['telefono']) === null) {
            throw ValidationException::withMessages(['telefono' => 'Escriba un celular colombiano de 10 dígitos (ej. 300 123 4567).']);
        }
        $resultado = $this->servicioAutenticacion->enviarCodigoTelefono($datos['telefono']);

        return response()->json([
            'mensaje' => "Si el número {$resultado['destino']} está registrado, recibirá un SMS con un código de 6 dígitos.",
            ...$resultado,
        ]);
    }

    /** Recuperación con el número de teléfono (paso 2): código del SMS y contraseña nueva. */
    public function restablecerConTelefono(Request $solicitud): JsonResponse
    {
        $datos = $solicitud->validate([
            'desafio' => ['required', 'string', 'max:100'],
            'codigo' => ['required', 'string', 'max:12'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], ['codigo' => 'código', 'password' => 'nueva contraseña']);
        $this->servicioAutenticacion->restablecerConTelefono($datos['desafio'], $datos['codigo'], $datos['password']);

        return response()->json(['mensaje' => 'Contraseña restablecida. Ya puede iniciar sesión.']);
    }

    /** Datos propios que el usuario puede cambiar: por ahora, su celular. */
    public function actualizarPerfil(Request $solicitud): UsuarioResource
    {
        $usuario = $solicitud->user();
        $datos = $solicitud->validate(['telefono' => ['nullable', 'string', 'max:20']], [], ['telefono' => 'celular']);
        $telefono = filled($datos['telefono'] ?? null) ? Telefono::normalizar($datos['telefono']) : null;
        if (filled($datos['telefono'] ?? null) && $telefono === null) {
            throw ValidationException::withMessages(['telefono' => 'Escriba un celular colombiano de 10 dígitos (ej. 300 123 4567).']);
        }
        if ($telefono && Usuario::where('telefono', $telefono)->whereKeyNot($usuario->id)->exists()) {
            throw ValidationException::withMessages(['telefono' => 'Ese celular ya está registrado en otra cuenta.']);
        }
        $usuario->forceFill(['telefono' => $telefono])->save();
        RegistroAuditoriaService::registrar('perfil_actualizado', $usuario, ['telefono' => Telefono::enmascarar($telefono)], $usuario->id, 'autenticacion');

        return new UsuarioResource($usuario->load(['rol.permisos', 'cliente']));
    }

    public function cambiarDobleFactor(Request $solicitud): JsonResponse
    {
        $datos = $solicitud->validate(['activo' => ['required', 'boolean'], 'password' => ['required', 'string', 'max:255']], [], ['password' => 'contraseña']);
        $this->servicioAutenticacion->cambiarDobleFactor($solicitud->user(), (bool) $datos['activo'], $datos['password']);

        return response()->json([
            'mensaje' => $datos['activo']
                ? 'Verificación en dos pasos activada: al iniciar sesión le pediremos un código enviado a su correo.'
                : 'Verificación en dos pasos desactivada.',
            'doble_factor' => (bool) $datos['activo'],
        ]);
    }

    public function restablecerContrasena(RestablecerContrasenaRequest $solicitud): JsonResponse
    {
        $this->servicioAutenticacion->restablecerContrasena(
            $solicitud->validated('correo'),
            $solicitud->validated('token'),
            $solicitud->validated('password')
        );

        return response()->json(['mensaje' => 'Contraseña restablecida. Ya puede iniciar sesión.']);
    }

    /**
     * Enlace firmado enviado por correo al crear el usuario.
     */
    public function verificarCorreo(Usuario $usuario, string $hash): RedirectResponse
    {
        abort_unless(hash_equals(sha1($usuario->correo), $hash), 403, 'El enlace de verificación no es válido.');

        if ($usuario->correo_verificado_en === null) {
            $usuario->forceFill(['correo_verificado_en' => now()])->save();
            RegistroAuditoriaService::registrar('correo_verificado', $usuario, [], $usuario->id, 'autenticacion');
        }

        return redirect()->away(config('sigma.url_frontend').'/iniciar-sesion?verificado=1');
    }
}
