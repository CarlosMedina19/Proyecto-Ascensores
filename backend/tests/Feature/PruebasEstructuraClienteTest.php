<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Edificio;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Agrupación de los edificios por tipo (conjunto, institucional, residencial), árbol del cliente
 * y ubicación de los ascensores tomada del edificio.
 */
class PruebasEstructuraClienteTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $administrador;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->seed([RolSeeder::class, PermisoSeeder::class]);
        $this->administrador = $this->crearUsuario('administrador');
    }

    private function crearUsuario(string $rol, array $datos = []): Usuario
    {
        return Usuario::factory()->create($datos + [
            'rol_id' => Rol::where('nombre', $rol)->firstOrFail()->id,
            'activo' => true,
        ]);
    }

    private function crearCliente(string $nombre, string $nit): Cliente
    {
        return Cliente::create(['tipo' => 'juridica', 'nombre' => $nombre, 'nit' => $nit, 'estado' => true]);
    }

    private function crearEdificio(Cliente $cliente, string $nombre, string $tipo): Edificio
    {
        return $cliente->edificios()->create([
            'tipo' => $tipo,
            'nombre' => $nombre,
            'direccion' => 'Calle 1 # 2-3',
            'ciudad' => 'Bogotá',
            'nombre_contacto' => 'Ana Torres',
            'telefono_contacto' => '3001112233',
        ]);
    }

    public function test_el_arbol_agrupa_solo_los_tipos_que_tiene_el_cliente(): void
    {
        $cliente = $this->crearCliente('Administraciones Sur', '900111222-1');
        $torre = $this->crearEdificio($cliente, 'Torre 1', 'conjunto');
        $this->crearEdificio($cliente, 'Torre 2', 'conjunto');
        $this->crearEdificio($cliente, 'Colegio Sur', 'institucional');
        $torre->equipos()->create(['codigo' => 'ASC-T1', 'tipo' => 'ascensor', 'estado' => 'activo']);

        $this->actingAs($this->administrador, 'sanctum')
            ->getJson("/api/v1/clientes/{$cliente->id}/estructura")
            ->assertOk()
            ->assertJsonCount(2, 'datos')
            ->assertJsonPath('datos.0.tipo', 'conjunto')
            ->assertJsonPath('datos.0.nombre', 'Conjunto')
            ->assertJsonCount(2, 'datos.0.edificios')
            ->assertJsonPath('datos.0.edificios.0.nombre', 'Torre 1')
            ->assertJsonPath('datos.0.edificios.0.equipos.0.codigo', 'ASC-T1')
            ->assertJsonPath('datos.1.tipo', 'institucional');
    }

    public function test_el_cliente_solo_ve_su_propio_arbol(): void
    {
        $propio = $this->crearCliente('Propio', '900111222-1');
        $ajeno = $this->crearCliente('Ajeno', '900333444-2');
        $this->crearEdificio($propio, 'Torre propia', 'residencial');

        $this->actingAs($this->crearUsuario('cliente', ['cliente_id' => $propio->id]), 'sanctum');

        $this->getJson("/api/v1/clientes/{$propio->id}/estructura")->assertOk()->assertJsonPath('datos.0.tipo', 'residencial');
        $this->getJson("/api/v1/clientes/{$ajeno->id}/estructura")->assertForbidden();
    }

    public function test_filtra_edificios_por_tipo_y_valida_el_tipo(): void
    {
        $cliente = $this->crearCliente('Administraciones Sur', '900111222-1');
        $this->crearEdificio($cliente, 'Torre 1', 'conjunto');
        $this->crearEdificio($cliente, 'Hospital Norte', 'institucional');

        $this->actingAs($this->administrador, 'sanctum');

        $this->getJson('/api/v1/edificios?tipo=institucional')
            ->assertOk()
            ->assertJsonCount(1, 'datos')
            ->assertJsonPath('datos.0.nombre', 'Hospital Norte');

        // Ascensores de un tipo de edificio (Clientes → Conjuntos → Edificios → Ascensores).
        Edificio::where('nombre', 'Torre 1')->first()->equipos()->create(['codigo' => 'ASC-C1', 'tipo' => 'ascensor']);
        Edificio::where('nombre', 'Hospital Norte')->first()->equipos()->create(['codigo' => 'ASC-H1', 'tipo' => 'ascensor']);
        $this->getJson('/api/v1/equipos?tipo_edificio=conjunto')
            ->assertOk()
            ->assertJsonCount(1, 'datos')
            ->assertJsonPath('datos.0.codigo', 'ASC-C1');

        $this->postJson('/api/v1/edificios', [
            'cliente_id' => $cliente->id,
            'tipo' => 'comercial',
            'nombre' => 'Centro',
            'direccion' => 'Calle 5',
            'ciudad' => 'Cali',
            'nombre_contacto' => 'Luis',
            'telefono_contacto' => '3000000000',
        ])->assertUnprocessable()->assertJsonValidationErrors(['tipo'], 'errores');
    }

    public function test_la_ubicacion_del_ascensor_sigue_al_edificio(): void
    {
        $cliente = $this->crearCliente('Administraciones Sur', '900111222-1');
        $edificio = $this->crearEdificio($cliente, 'Torre 1', 'conjunto');
        $otro = $this->crearEdificio($cliente, 'Torre 2', 'conjunto');

        $this->actingAs($this->administrador, 'sanctum');

        $equipoId = $this->postJson('/api/v1/equipos', [
            'edificio_id' => $edificio->id,
            'codigo' => 'ASC-100',
            'ascensor' => ['capacidad_kg' => 630, 'velocidad_mpm' => 60, 'paradas' => 6],
        ])->assertCreated()->assertJsonPath('datos.ubicacion', 'Torre 1 · Calle 1 # 2-3, Bogotá')->json('datos.id');

        // Si cambia la dirección del edificio, cambia la de sus ascensores.
        $this->patchJson("/api/v1/edificios/{$edificio->id}", ['direccion' => 'Calle 9 # 9-9'])->assertOk();
        $this->getJson("/api/v1/equipos/{$equipoId}")->assertJsonPath('datos.ubicacion', 'Torre 1 · Calle 9 # 9-9, Bogotá');

        // Si el ascensor pasa a otro edificio, toma la ubicación del nuevo.
        $this->patchJson("/api/v1/equipos/{$equipoId}", ['edificio_id' => $otro->id])
            ->assertOk()
            ->assertJsonPath('datos.ubicacion', 'Torre 2 · Calle 1 # 2-3, Bogotá');
    }
}
