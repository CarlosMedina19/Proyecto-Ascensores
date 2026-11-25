<?php

namespace App\Services;

use App\Support\Telefono;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Envío de mensajes de texto (SMS). El operador se elige con SIGMA_SMS: log, twilio, n8n o simulado
 * (ver config/services.php). Un fallo del operador se informa como RuntimeException.
 */
class MensajeTextoService
{
    public function operador(): string
    {
        return (string) config('services.sms.driver', 'log');
    }

    /** Sin operador real: el código se muestra en pantalla (demostración del proyecto formativo). */
    public function esSimulado(): bool
    {
        return $this->operador() === 'simulado';
    }

    /**
     * ¿Se puede mostrar el código en pantalla? Solo en modo simulado y nunca en producción, salvo que se permita
     * de forma explícita (SIGMA_SMS_DEMO_EN_PRODUCCION=true) para una presentación.
     */
    public function muestraCodigo(): bool
    {
        return $this->esSimulado() && (! app()->isProduction() || (bool) config('services.sms.demo_en_produccion'));
    }

    /**
     * ¿Hay cómo entregar el código? Con un operador real (Twilio o n8n) siempre; sin operador, solo fuera de
     * producción o en la demostración permitida. En producción con «log» o «simulado» la recuperación por
     * celular se desactiva y se usa el correo.
     */
    public function disponible(): bool
    {
        return in_array($this->operador(), ['twilio', 'n8n'], true) || ! app()->isProduction() || $this->muestraCodigo();
    }

    public function enviar(string $telefono, string $mensaje): void
    {
        $destino = Telefono::internacional($telefono);

        match ($this->operador()) {
            'twilio' => $this->twilio($destino, $mensaje),
            'n8n' => $this->n8n($destino, $mensaje),
            'simulado' => null,
            default => Log::info('SMS (solo registro, sin operador)', ['telefono' => Telefono::enmascarar($telefono), 'mensaje' => $mensaje]),
        };
    }

    private function twilio(string $destino, string $mensaje): void
    {
        $configuracion = config('services.sms.twilio');
        if (blank($configuracion['sid'] ?? null) || blank($configuracion['token'] ?? null) || blank($configuracion['numero'] ?? null)) {
            throw new RuntimeException('Twilio no está configurado (TWILIO_SID, TWILIO_TOKEN y TWILIO_NUMERO).');
        }

        $respuesta = Http::asForm()->timeout(15)
            ->withBasicAuth($configuracion['sid'], $configuracion['token'])
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$configuracion['sid']}/Messages.json", [
                'To' => $destino,
                'From' => $configuracion['numero'],
                'Body' => $mensaje,
            ]);

        if ($respuesta->failed()) {
            throw new RuntimeException('Twilio rechazó el SMS: '.($respuesta->json('message') ?? $respuesta->status()));
        }
    }

    private function n8n(string $destino, string $mensaje): void
    {
        $configuracion = config('services.sms.n8n');
        if (blank($configuracion['url'] ?? null)) {
            throw new RuntimeException('Falta SIGMA_SMS_N8N_URL para enviar SMS por n8n.');
        }

        Http::withHeaders(['X-Sigma-Clave' => (string) ($configuracion['clave'] ?? '')])->acceptJson()->timeout(15)
            ->post($configuracion['url'], ['telefono' => $destino, 'mensaje' => $mensaje])
            ->throw();
    }
}
