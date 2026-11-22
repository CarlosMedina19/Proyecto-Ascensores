<?php

namespace App\Services;

use App\Models\CodigoVerificacion;
use App\Models\Usuario;
use App\Notifications\AlertaSeguridadNotification;
use App\Support\Telefono;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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
    public function __construct(private readonly VerificacionService $verificacion) {}

    /**
     * Con la verificación en dos pasos activa no entrega el token: envía un código al correo y devuelve el
     * desafío para completar el ingreso en verificarInicioSesion().
     *
     * @return array{usuario: Usuario, token?: string, verificacion?: array}
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

        if ($usuario->doble_factor) {
            $usuario->forceFill(['intentos_fallidos' => 0, 'bloqueado_hasta' => null])->save();

            return ['usuario' => $usuario, 'verificacion' => $this->verificacion->iniciar($usuario, CodigoVerificacion::INICIO_SESION, 'correo')];
        }

        return $this->completarInicioSesion($usuario, $dispositivo);
    }

    /** Segundo paso: el código que llegó al correo. */
    public function verificarInicioSesion(string $desafio, string $codigo, ?string $dispositivo = null): array
    {
        $usuario = $this->verificacion->verificar($desafio, $codigo, CodigoVerificacion::INICIO_SESION);

        return $this->completarInicioSesion($usuario, $dispositivo, ['doble_factor' => true]);
    }

    public function reenviarCodigoInicioSesion(string $desafio): array
    {
        return $this->verificacion->reenviar($desafio, CodigoVerificacion::INICIO_SESION);
    }

    /** @return array{usuario: Usuario, token: string} */
    private function completarInicioSesion(Usuario $usuario, ?string $dispositivo, array $detalle = []): array
    {
        $usuario->forceFill([
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
            'ultimo_acceso_en' => now(),
        ])->save();

        $token = $usuario->createToken($dispositivo ?: 'api')->plainTextToken;

        RegistroAuditoriaService::registrar('inicio_sesion', $usuario, $detalle, $usuario->id, 'autenticacion');

        return ['usuario' => $usuario, 'token' => $token];
    }

    /** Activa o desactiva la verificación en dos pasos de la propia cuenta (pide la contraseña actual). */
    public function cambiarDobleFactor(Usuario $usuario, bool $activo, string $password): void
    {
        if (! Hash::check($password, $usuario->password)) {
            throw ValidationException::withMessages(['password' => 'La contraseña no es correcta.']);
        }

        $usuario->forceFill(['doble_factor' => $activo])->save();
        RegistroAuditoriaService::registrar($activo ? 'doble_factor_activado' : 'doble_factor_desactivado', $usuario, [], $usuario->id, 'autenticacion');
    }

    /**
     * Recuperación con el número de teléfono: envía un código por SMS. La respuesta tiene la misma forma
     * aunque el número no esté registrado, para no revelar qué números existen.
     */
    public function enviarCodigoTelefono(string $telefono): array
    {
        $numero = Telefono::normalizar($telefono);
        $usuario = $numero ? Usuario::where('telefono', $numero)->where('activo', true)->orderBy('id')->first() : null;

        if (! $usuario) {
            return $this->verificacion->desafioFicticio('sms', Telefono::enmascarar($numero));
        }

        return $this->verificacion->iniciar($usuario, CodigoVerificacion::RECUPERACION, 'sms');
    }

    public function reenviarCodigoTelefono(string $desafio): array
    {
        return $this->verificacion->reenviar($desafio, CodigoVerificacion::RECUPERACION);
    }

    public function restablecerConTelefono(string $desafio, string $codigo, string $nueva): void
    {
        $usuario = $this->verificacion->verificar($desafio, $codigo, CodigoVerificacion::RECUPERACION);

        DB::transaction(function () use ($usuario, $nueva) {
            $usuario->forceFill([
                'password' => $nueva,
                'intentos_fallidos' => 0,
                'bloqueado_hasta' => null,
            ])->save();
            $usuario->tokens()->delete();

            RegistroAuditoriaService::registrar('contrasena_restablecida', $usuario, ['canal' => 'sms'], $usuario->id, 'autenticacion');
            event(new PasswordReset($usuario));
        });
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
            $usuario->forceFill($datos)->save();
            $this->avisarCuentaBloqueada($usuario, $intentos);

            return;
        }

        $usuario->forceFill($datos)->save();
    }

    /**
     * Avisa a los administradores y al dueño de la cuenta (campana del panel y/o correo, según la
     * configuración). Un fallo al avisar no impide responder al intento de inicio de sesión.
     */
    private function avisarCuentaBloqueada(Usuario $usuario, int $intentos): void
    {
        try {
            $destinatarios = Usuario::query()
                ->where('activo', true)
                ->whereHas('rol', fn ($rol) => $rol->where('nombre', 'administrador'))
                ->get()
                ->push($usuario)
                ->unique('id');

            app(NotificacionService::class)->enviar(
                $destinatarios,
                new AlertaSeguridadNotification($usuario, $intentos, (int) config('sigma.sesion.minutos_bloqueo'), request()?->ip()),
                'cuenta_bloqueada_'.now()->format('YmdHisv'),
                'usuario',
                $usuario->id,
            );
        } catch (\Throwable $e) {
            Log::warning('No fue posible avisar el bloqueo de la cuenta', ['usuario_id' => $usuario->id, 'error' => $e->getMessage()]);
        }
    }
}
