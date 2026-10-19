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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
            $solicitud->validated('dispositivo')
        );

        return response()->json([
            'usuario' => new UsuarioResource($resultado['usuario']->load(['rol.permisos', 'cliente'])),
            'token_acceso' => $resultado['token'],
            'tipo_token' => 'Bearer',
            'minutos_inactividad' => config('sigma.sesion.minutos_inactividad'),
        ]);
    }

    public function cerrarSesion(Request $solicitud): JsonResponse
    {
        $this->servicioAutenticacion->cerrarSesion($solicitud->user());

        return response()->json(['mensaje' => 'Sesión cerrada correctamente.']);
    }

    public function perfil(Request $solicitud): UsuarioResource
    {
        return new UsuarioResource($solicitud->user()->load(['rol.permisos', 'cliente']));
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
