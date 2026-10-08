<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseOneApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();


        $this->adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            ['description' => 'Administrador']
        );


        $this->adminUser = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);
    }

    public function test_user_can_login_and_receive_token(): void
    {
        $user = User::factory()->create([
            'email' => 'tech@ascensores.com',
            'password' => bcrypt('password123'),
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'tech@ascensores.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'inactive@ascensores.com',
            'password' => bcrypt('password123'),
            'role_id' => $this->adminRole->id,
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'inactive@ascensores.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Usuario inactivo']);
    }

    public function test_authenticated_user_can_access_me_and_dashboard(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $this->getJson('/api/v1/me')
            ->assertStatus(200)
            ->assertJsonFragment(['email' => $this->adminUser->email]);

        $this->getJson('/api/v1/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['users', 'clients', 'buildings', 'equipment']]);
    }

    public function test_can_list_roles(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $this->getJson('/api/v1/roles')
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'admin']);
    }

    public function test_can_crud_users(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        
        $storeResponse = $this->postJson('/api/v1/users', [
            'name' => 'Nuevo Técnico',
            'email' => 'tecnico1@ascensores.com',
            'password' => 'secret1234',
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $storeResponse->assertStatus(201)
            ->assertJsonFragment(['email' => 'tecnico1@ascensores.com']);

        $userId = $storeResponse->json('data.id');

        
        $this->patchJson("/api/v1/users/{$userId}/toggle-status")
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', false);
    }

    public function test_can_crud_clients_and_add_contacts(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');


        $clientResponse = $this->postJson('/api/v1/clients', [
            'type' => 'juridica',
            'name' => 'Edificio Mirador Plaza',
            'nit' => '900123456-1',
            'address' => 'Cra 15 # 100-20',
            'phone' => '3001234567',
            'email' => 'admin@miradorplaza.com',
        ]);

        $clientResponse->assertStatus(201)
            ->assertJsonFragment(['name' => 'Edificio Mirador Plaza']);

        $clientId = $clientResponse->json('data.id');


        $this->postJson("/api/v1/clients/{$clientId}/contacts", [
            'name' => 'Pedro Pérez',
            'position' => 'Administrador',
            'phone' => '3109876543',
            'email' => 'pedro@miradorplaza.com',
        ])->assertStatus(201)
          ->assertJsonFragment(['name' => 'Pedro Pérez']);


        $this->getJson("/api/v1/clients/{$clientId}")
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'Edificio Mirador Plaza']);
    }

    public function test_can_crud_buildings(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $client = Client::create([
            'name' => 'Constructora Bolívar',
            'nit' => '901234567-2',
            'status' => true,
        ]);

        $response = $this->postJson('/api/v1/buildings', [
            'client_id' => $client->id,
            'name' => 'Torre Central',
            'address' => 'Calle 26 # 68-50',
            'city' => 'Bogotá',
            'floors' => 20,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Torre Central']);
    }

    public function test_can_create_elevator_equipment_with_specs_and_history(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $client = Client::create(['name' => 'Inversiones Bogotá', 'nit' => '900999888-1']);
        $building = Building::create([
            'client_id' => $client->id,
            'name' => 'Torre Norte',
            'address' => 'Cra 7 # 72-10',
        ]);


        $response = $this->postJson('/api/v1/equipment', [
            'building_id' => $building->id,
            'code' => 'ASC-001',
            'type' => 'elevator',
            'brand' => 'Otis',
            'model' => 'Gen2',
            'serial_number' => 'OT-98765',
            'location' => 'Torre Norte - Fila A',
            'status' => 'active',
            'elevator' => [
                'capacity_kg' => 1000,
                'stops' => 15,
                'speed_mpm' => 120.0,
                'drive_type' => 'Tracción con cintas planas',
                'motor' => 'Imanes permanentes ReGen',
                'controller' => 'GCS2200',
                'door_type' => 'Apertura central 2 hojas',
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'ASC-001')
            ->assertJsonPath('data.elevator.stops', 15)
            ->assertJsonPath('data.elevator.capacity_kg', 1000);

        $equipmentId = $response->json('data.id');


        $this->getJson("/api/v1/equipment/{$equipmentId}/history")
            ->assertStatus(200)
            ->assertJsonFragment(['event' => 'alta_equipo']);


        $this->postJson("/api/v1/equipment/{$equipmentId}/history", [
            'event' => 'inspeccion_inicial',
            'description' => 'Inspección de entrega satisfactoria.',
        ])->assertStatus(201)
          ->assertJsonFragment(['event' => 'inspeccion_inicial']);
    }
}
