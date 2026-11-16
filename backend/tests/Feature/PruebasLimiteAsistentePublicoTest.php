<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * El asistente de la página pública no pide sesión y cada pregunta puede ir a la IA (con costo):
 * tiene límite por dirección IP y un tope diario de preguntas a la IA.
 */
class PruebasLimiteAsistentePublicoTest extends TestCase
{
    private const URL = 'https://n8n.prueba/webhook/landing';

    private function preguntar(string $ip = '203.0.113.20')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/v1/publico/asistente', ['mensaje' => '¿Qué hace SIGMA?', 'sesion' => 'visitante1']);
    }

    private function activarIa(int $limiteDiario): void
    {
        config([
            'services.asistente.n8n_activo' => true,
            'services.asistente.url_landing' => self::URL,
            'services.asistente.limite_diario_publico' => $limiteDiario,
        ]);
        Http::fake([self::URL => Http::response(['respuesta' => 'Respuesta de la IA'])]);
    }

    public function test_al_pasar_el_tope_diario_responde_el_motor_local_sin_llamar_a_la_ia(): void
    {
        $this->activarIa(2);

        $this->assertSame('n8n', $this->preguntar()->assertOk()->json('datos.origen'));
        $this->assertSame('n8n', $this->preguntar()->assertOk()->json('datos.origen'));
        $tercera = $this->preguntar()->assertOk()->json('datos');

        $this->assertSame('local', $tercera['origen']);
        $this->assertNotSame('', $tercera['texto']);
        Http::assertSentCount(2);
    }

    public function test_el_tope_diario_se_reinicia_al_dia_siguiente(): void
    {
        $this->activarIa(1);
        $this->preguntar();
        $this->assertSame('local', $this->preguntar()->json('datos.origen'));

        $this->travel(1)->days();
        $this->assertSame('n8n', $this->preguntar()->json('datos.origen'));
    }

    public function test_limite_de_mensajes_por_minuto_por_ip(): void
    {
        foreach (range(1, 10) as $n) {
            $this->preguntar()->assertOk();
        }
        $this->preguntar()->assertTooManyRequests()->assertJsonStructure(['mensaje']);

        // Otra persona no se ve afectada.
        $this->preguntar('198.51.100.30')->assertOk();
    }

    public function test_limite_de_mensajes_por_hora_por_ip(): void
    {
        foreach (range(1, 6) as $minuto) {
            foreach (range(1, 10) as $n) {
                $this->preguntar()->assertOk();
            }
            $this->travel(61)->seconds();
        }

        // 60 mensajes en 6 minutos: el siguiente ya pasa el límite por hora.
        $this->preguntar()->assertTooManyRequests();
    }
}
