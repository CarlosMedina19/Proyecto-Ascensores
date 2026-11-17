<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Encabezados de seguridad del middleware EncabezadosSeguridad.
 */
class PruebasEncabezadosSeguridadTest extends TestCase
{
    private const ESPERADOS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'no-referrer',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
    ];

    public function test_las_respuestas_de_la_api_llevan_encabezados_de_seguridad(): void
    {
        $respuesta = $this->getJson('/up');

        $respuesta->assertOk();
        foreach (self::ESPERADOS as $nombre => $valor) {
            $respuesta->assertHeader($nombre, $valor);
        }
        $respuesta->assertHeaderMissing('X-Powered-By');
    }

    public function test_los_errores_tambien_llevan_encabezados_de_seguridad(): void
    {
        // Sin token: el error 401 lo genera el manejador de excepciones.
        $respuesta = $this->getJson('/api/v1/perfil');

        $respuesta->assertUnauthorized();
        foreach (self::ESPERADOS as $nombre => $valor) {
            $respuesta->assertHeader($nombre, $valor);
        }
    }

    public function test_hsts_solo_se_envia_por_https(): void
    {
        $this->getJson('http://localhost/up')->assertHeaderMissing('Strict-Transport-Security');

        $this->getJson('https://localhost/up')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }
}
