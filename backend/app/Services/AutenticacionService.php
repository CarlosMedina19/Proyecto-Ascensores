<?php

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * RF-001: inicio y cierre de sesión, bloqueo por intentos fallidos,
 * recuperación y cambio de contraseña.
 */
class AutenticacionService
{
    /**
     * @return array{usuario: Usuario, token: string}
     */
    public function iniciarSesion(string $correo, string $password, ?string $dispositivo = null): array
    {
        $usuario = Usuario::where('correo', Str::lower($correo))->first();

        if ($usuario?->estaBloqueado()) {
            $minutos = (int) ceil(now()->diffInSeconds($usuario->bloqueado_hasta) / 60);

            throw new HttpException(423, "La cuenta está bloqueada por intentos fallidos. Intente de nuevo en {$minutos} minuto(s).");
        }

        if (! $usuario || ! Hash::check($password, $usuario->password)) {
            if ($usuario) {
                $this->registrarIntentoFallido($usuario);
            }

            throw new HttpException(401, 'Credenciales incorrectas.');
        }

        if (! $usuario->activo) {
            throw new HttpException(403, 'El usuario está inactivo. Comuníquese con el administrador.');
        }

        $usuario->forceFill([
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
            'ultimo_acceso_en' => now(),
        ])->save();

        $token = $usuario->createToken($dispositivo ?: 'api')->plainTextToken;

        RegistroAuditoriaService::registrar('inicio_sesion', $usuario, [], $usuario->id, 'autenticacion');

        return ['usuario' => $usuario, 'token' => $token];
    }

    public function cerrarSesion(Usuario $usuario): void
    {
        $usuario->currentAccessToken()?->delete();

        RegistroAuditoriaService::registrar('cierre_sesion', $usuario, [], $usuario->id, 'autenticacion');
    }

    public function cambiarContrasena(Usuario $usuario, string $actual, string $nueva): void
    {
        if (! Hash::check($actual, $usuario->password)) {
            throw ValidationException::withMessages([
                'contrasena_actual' => 'La contraseña actual no es correcta.',
            ]);
        }

        DB::transaction(function () use ($usuario, $nueva) {
            $usuario->forceFill(['password' => $nueva])->save();

            // Cierra las demás sesiones abiertas del usuario.
            $tokenActual = $usuario->currentAccessToken();
            $usuario->tokens()
                ->when($tokenActual?->id, fn ($consulta, $id) => $consulta->whereKeyNot($id))
                ->delete();

            RegistroAuditoriaService::registrar('contrasena_cambiada', $usuario, [], $usuario->id, 'autenticacion');
        });
    }

    /**
     * Envía el enlace de recuperación. La respuesta es la misma exista o no
     * la cuenta, para no revelar qué correos están registrados.
     */
    public function enviarEnlaceRecuperacion(string $correo): void
    {
        Password::broker()->sendResetLink(['correo' => Str::lower($correo)]);
    }

    public function restablecerContrasena(string $correo, string $token, string $nueva): void
    {
        $estado = Password::broker()->reset(
            ['correo' => Str::lower($correo), 'token' => $token, 'password' => $nueva],
            function (Usuario $usuario, string $password) {
                $usuario->forceFill([
                    'password' => $password,
                    'intentos_fallidos' => 0,
                    'bloqueado_hasta' => null,
                ])->save();

                $usuario->tokens()->delete();

                RegistroAuditoriaService::registrar('contrasena_restablecida', $usuario, [], $usuario->id, 'autenticacion');

                event(new PasswordReset($usuario));
            }
        );

        if ($estado !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => 'El enlace de recuperación no es válido o ya venció.',
            ]);
        }
    }

    private function registrarIntentoFallido(Usuario $usuario): void
    {
        $intentos = $usuario->intentos_fallidos + 1;
        $datos = ['intentos_fallidos' => $intentos];

        if ($intentos >= config('sigma.sesion.intentos_maximos')) {
            $datos = [
                'intentos_fallidos' => 0,
                'bloqueado_hasta' => now()->addMinutes(config('sigma.sesion.minutos_bloqueo')),
            ];

            RegistroAuditoriaService::registrar('cuenta_bloqueada', $usuario, ['intentos' => $intentos], $usuario->id, 'autenticacion');
        }

        $usuario->forceFill($datos)->save();
    }
}
