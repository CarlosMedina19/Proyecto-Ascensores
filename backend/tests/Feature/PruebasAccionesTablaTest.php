<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Acciones de las tablas: activar o desactivar registros y eliminar usuarios conservando su historial. */
class PruebasAccionesTablaTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $administrador;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed([RolSeeder::class, PermisoSeeder::class]);
        $this->administrador = $this->usuario('administrador');
    }

    private function usuario(string $rol, array $datos = []): Usuario
    {
        return Usuario::factory()->create($datos + ['rol_id' => Rol::where('nombre', $rol)->value('id'), 'activo' => true]);
    }

    public function test_activa_y_desactiva_clientes_edificios_y_ascensores(): void
    {
        $cliente = Cliente::create(['tipo' => 'juridica', 'nombre' => 'Conjunto Sur', 'nit' => '900111222-1', 'estado' => true]);
        $edificio = $cliente->edificios()->create(['tipo' => 'conjunto', 'nombre' => 'Torre 1', 'direccion' => 'Calle 1', 'ciudad' => 'Bogotá', 'nombre_contacto' => 'Ana', 'telefono_contacto' => '3001112233']);
        $equipo = $edificio->equipos()->create(['codigo' => 'ASC-9', 'tipo' => 'ascensor', 'estado' => 'mantenimiento']);
        $this->actingAs($this->administrador, 'sanctum');

        $this->patchJson("/api/v1/clientes/{$cliente->id}/alternar-estado")->assertOk()->assertJsonPath('datos.activo', false);
        $this->assertFalse($cliente->fresh()->estado);
        $this->patchJson("/api/v1/edificios/{$edificio->id}/alternar-estado")->assertOk()->assertJsonPath('datos.activo', false);
        $this->patchJson("/api/v1/equipos/{$equipo->id}/alternar-estado")->assertOk()->assertJsonPath('datos.estado', 'inactivo');
        $this->patchJson("/api/v1/equipos/{$equipo->id}/alternar-estado")->assertOk()->assertJsonPath('datos.estado', 'activo');
        $this->assertDatabaseHas('historial_equipos', ['equipo_id' => $equipo->id, 'evento' => 'cambio_estado']);

        $this->actingAs($this->usuario('supervisor'), 'sanctum')
            ->patchJson("/api/v1/clientes/{$cliente->id}/alternar-estado")->assertForbidden();
    }

    public function test_elimina_un_usuario_y_libera_su_correo(): void
    {
        $tecnico = $this->usuario('tecnico', ['nombre' => 'Andrés Rojas', 'correo' => 'andres@sigma.co']);
        $this->actingAs($this->administrador, 'sanctum');

        $this->deleteJson("/api/v1/usuarios/{$tecnico->id}")->assertOk();
        $this->assertSoftDeleted('usuarios', ['id' => $tecnico->id]);
        $this->getJson('/api/v1/usuarios')->assertOk()->assertJsonMissing(['correo' => 'andres@sigma.co']);
        $this->assertFalse(Usuario::where('correo', 'andres@sigma.co')->exists());

        $this->deleteJson("/api/v1/usuarios/{$this->administrador->id}")->assertUnprocessable();
    }
}
