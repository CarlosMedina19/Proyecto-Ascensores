<?php

namespace Tests\Unit;

use App\Services\Asistente\AsistenteService;
use PHPUnit\Framework\TestCase;

/** El chat muestra texto plano: el Markdown que devuelva el modelo de IA se limpia. */
class TextoPlanoAsistenteTest extends TestCase
{
    public function test_quita_negritas_titulos_y_convierte_tablas_en_lineas(): void
    {
        $markdown = "## Resumen\nUsted tiene **2 órdenes activas**:\n\n| Orden | Estado |\n|---|---|\n| OT-1 | asignada |\n| OT-2 | en proceso |\n\n\n\n* Vea [el detalle](https://x.co) abajo.";

        $this->assertSame(
            "Resumen\nUsted tiene 2 órdenes activas:\n\nOrden · Estado:\n- OT-1 · asignada\n- OT-2 · en proceso\n\n- Vea el detalle abajo.",
            AsistenteService::textoPlano($markdown)
        );
    }

    public function test_el_texto_sin_markdown_no_cambia(): void
    {
        $texto = "Sí, tiene una factura vencida.\n- FV-0009 · \$1.499.400";

        $this->assertSame($texto, AsistenteService::textoPlano("  {$texto}  "));
    }
}
