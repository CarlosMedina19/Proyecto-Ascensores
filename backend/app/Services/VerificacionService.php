<?php

namespace App\Services;

use App\Models\CodigoVerificacion;
use App\Models\Usuario;
use App\Notifications\CodigoVerificacionNotification;
use App\Support\Telefono;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Códigos de un solo uso (6 dígitos) para:
 *  - el segundo paso del inicio de sesión, enviado al correo;
 *  - la recuperación de la contraseña con el número de teléfono, enviado por SMS.
 *
 * El cliente recibe un «desafío» (texto aleatorio largo) que identifica la solicitud; en la base solo se
 * guardan las huellas del desafío y del código. Cada código vence a los 10 minutos y admite 5 intentos.
 */
class VerificacionService
{
    public const MINUTOS = 10;

    public const INTENTOS = 5;

    public const SEGUNDOS_REENVIO = 60;

    public function __construct(private readonly MensajeTextoService $sms) {}

    /**
     * @return array{desafio: string, canal: string, destino: string, minutos: int, codigo_demo?: string}
     */
    public function iniciar(Usuario $usuario, string $proposito, string $canal): array
    {
        CodigoVerificacion::where('usuario_id', $usuario->id)->where('proposito', $proposito)->whereNull('usado_en')->delete();

        $desafio = Str::random(48);
        $codigo = $this->nuevoCodigo();
        CodigoVerificacion::create([
            'usuario_id' => $usuario->id,
            'proposito' => $proposito,
            'canal' => $canal,
            'desafio' => $this->huella($desafio),
            'codigo' => $this->huella($codigo),
            'expira_en' => now()->addMinutes(self::MINUTOS),
            'ip' => request()?->ip(),
        ]);

        return ['desafio' => $desafio] + $this->enviar($usuario, $proposito, $canal, $codigo);
    }

    /** Respuesta con la misma forma que iniciar() cuando la cuenta no existe (no revela qué datos están registrados). */
    public function desafioFicticio(string $canal, string $destino): array
    {
        return ['desafio' => Str::random(48), 'canal' => $canal, 'destino' => $destino, 'minutos' => self::MINUTOS];
    }

    public function reenviar(string $desafio, string $proposito): array
    {
        $registro = $this->buscar($desafio, $proposito);
        $ultimo = $registro->reenviado_en ?? $registro->created_at;
        $espera = self::SEGUNDOS_REENVIO - (int) $ultimo->diffInSeconds(now());
        if ($espera > 0) {
            throw ValidationException::withMessages(['codigo' => "Espere {$espera} segundos para pedir otro código."]);
        }

        $codigo = $this->nuevoCodigo();
        $registro->forceFill([
            'codigo' => $this->huella($codigo),
            'intentos' => 0,
            'expira_en' => now()->addMinutes(self::MINUTOS),
            'reenviado_en' => now(),
        ])->save();

        return ['desafio' => $desafio] + $this->enviar($registro->usuario, $proposito, $registro->canal, $codigo);
    }

    /** Comprueba el código y lo marca como usado. Devuelve el dueño de la solicitud. */
    public function verificar(string $desafio, string $codigo, string $proposito): Usuario
    {
        $registro = $this->buscar($desafio, $proposito);

        if ($registro->intentos >= self::INTENTOS) {
            throw ValidationException::withMessages(['codigo' => 'Superó los intentos permitidos. Solicite un código nuevo.']);
        }

        if (! hash_equals($registro->codigo, $this->huella(preg_replace('/\D+/', '', $codigo)))) {
            $registro->increment('intentos');
            $quedan = self::INTENTOS - $registro->intentos;
            if ($quedan === 0) {
                RegistroAuditoriaService::registrar('verificacion_fallida', $registro->usuario, ['proposito' => $proposito, 'canal' => $registro->canal], $registro->usuario_id, 'autenticacion');
            }

            throw ValidationException::withMessages(['codigo' => $quedan > 0
                ? "El código no es correcto. Le quedan {$quedan} intento(s)."
                : 'Superó los intentos permitidos. Solicite un código nuevo.']);
        }

        $registro->forceFill(['usado_en' => now()])->save();

        return $registro->usuario;
    }

    private function buscar(string $desafio, string $proposito): CodigoVerificacion
    {
        $registro = CodigoVerificacion::with('usuario')->vigentes()
            ->where('proposito', $proposito)
            ->where('desafio', $this->huella($desafio))
            ->first();

        if (! $registro || ! $registro->usuario?->activo) {
            throw ValidationException::withMessages(['codigo' => 'El código venció o la solicitud no es válida. Vuelva a empezar.']);
        }

        return $registro;
    }

    private function enviar(Usuario $usuario, string $proposito, string $canal, string $codigo): array
    {
        $respuesta = ['canal' => $canal, 'minutos' => self::MINUTOS];

        try {
            if ($canal === 'sms') {
                $respuesta['destino'] = Telefono::enmascarar($usuario->telefono);
                $this->sms->enviar($usuario->telefono, "SIGMA: su código para restablecer la contraseña es {$codigo}. Vence en ".self::MINUTOS.' minutos. No lo comparta.');
                // Demostración sin operador de SMS: el código se muestra en pantalla, salvo en cuentas protegidas.
                if ($this->sms->muestraCodigo()) {
                    if ($this->cuentaProtegida($usuario)) {
                        $respuesta['nota'] = 'Esta cuenta está protegida: en el modo de demostración el código no se muestra. Recupere la contraseña por correo.';
                    } else {
                        $respuesta['codigo_demo'] = $codigo;
                    }
                }
            } else {
                $respuesta['destino'] = Telefono::enmascararCorreo($usuario->correo);
                $usuario->notify(new CodigoVerificacionNotification($codigo, $proposito, self::MINUTOS, request()?->ip()));
            }
        } catch (Throwable $e) {
            Log::error('No fue posible enviar el código de verificación', ['usuario_id' => $usuario->id, 'canal' => $canal, 'error' => $e->getMessage()]);

            throw new HttpException(503, $canal === 'sms'
                ? 'No fue posible enviar el SMS en este momento. Intente más tarde o recupere la contraseña por correo.'
                : 'No fue posible enviar el código a su correo en este momento. Intente de nuevo en unos minutos.');
        }

        return $respuesta;
    }

    /** Administradores y cuentas con verificación en dos pasos nunca reciben el código en pantalla. */
    public function cuentaProtegida(Usuario $usuario): bool
    {
        return $usuario->doble_factor || $usuario->tieneRol('administrador');
    }

    private function nuevoCodigo(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function huella(string $valor): string
    {
        return hash_hmac('sha256', $valor, (string) config('app.key'));
    }
}
