<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\Invoice;
use App\Models\Part;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DomainApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'admin'], ['description' => 'Administrador']);
        $this->user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $this->actingAs($this->user, 'sanctum');
    }

    public function test_authenticated_user_can_create_and_list_maintenance_plans(): void
    {
        $created = $this->postJson('/api/v1/maintenance-plans', [
            'name' => 'Preventivo trimestral',
            'service_type' => 'preventive',
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Preventivo trimestral');

        $this->getJson('/api/v1/maintenance-plans?search=trimestral')
            ->assertOk()
            ->assertJsonPath('data.0.id', $created->json('data.id'));
    }

    public function test_stock_movements_update_inventory_and_reject_negative_stock(): void
    {
        $part = Part::query()->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'REP-001',
            'name' => 'Rodamiento',
            'stock_quantity' => 0,
            'minimum_stock' => 2,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/stock-movements', [
            'part_id' => $part->id,
            'movement_type' => 'in',
            'quantity' => 5,
            'unit_cost' => 1000,
        ])->assertCreated();

        $this->assertDatabaseHas('parts', ['id' => $part->id, 'stock_quantity' => 5]);

        $this->postJson('/api/v1/stock-movements', [
            'part_id' => $part->id,
            'movement_type' => 'out',
            'quantity' => 6,
        ])->assertUnprocessable();

        $this->assertDatabaseHas('parts', ['id' => $part->id, 'stock_quantity' => 5]);
    }

    public function test_payment_posts_credit_and_updates_invoice_balance_status(): void
    {
        $client = Client::query()->create([
            'name' => 'Cliente de prueba',
            'nit' => '901234567-8',
            'status' => true,
        ]);

        $invoice = Invoice::query()->create([
            'uuid' => (string) Str::uuid(),
            'number' => 'FAC-TEST-001',
            'client_id' => $client->id,
            'issued_at' => now()->toDateString(),
            'total' => 100000,
            'status' => 'issued',
            'currency' => 'COP',
            'tax_status' => 'taxable',
        ]);

        $this->postJson('/api/v1/payments', [
            'invoice_id' => $invoice->id,
            'paid_at' => now()->toIso8601String(),
            'amount' => 40000,
            'method' => 'transfer',
            'reference' => 'TRX-001',
        ])->assertCreated();

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'partially_paid']);
        $this->assertDatabaseHas('account_movements', [
            'client_id' => $client->id,
            'movement_type' => 'payment',
            'credit' => 40000,
        ]);
    }

    public function test_approved_quotation_converts_to_an_invoice_using_calculated_totals(): void
    {
        $client = Client::query()->create(['name' => 'Cliente cotización', 'nit' => '901000000-3']);
        $quote = $this->postJson('/api/v1/quotations', [
            'number' => 'COT-TEST-001',
            'client_id' => $client->id,
            'issued_at' => now()->toDateString(),
            'subtotal' => 1,
            'total' => 1,
        ])->assertCreated()->json('data');

        $this->postJson('/api/v1/quotation-items', [
            'quotation_id' => $quote['id'],
            'description' => 'Repuesto y mano de obra',
            'quantity' => 2,
            'unit_price' => 1000,
            'discount_amount' => 100,
            'tax_rate' => 19,
        ])->assertCreated();

        $this->assertDatabaseHas('quotations', [
            'id' => $quote['id'],
            'subtotal' => 2000,
            'discount_total' => 100,
            'tax_total' => 361,
            'total' => 2261,
        ]);

        $this->patchJson('/api/v1/quotations/'.$quote['id'], ['status' => 'sent'])->assertOk();
        $this->patchJson('/api/v1/quotations/'.$quote['id'], ['status' => 'approved'])->assertOk();

        $invoice = $this->postJson('/api/v1/invoices', [
            'number' => 'FAC-QUOTE-001',
            'client_id' => $client->id,
            'quotation_id' => $quote['id'],
            'issued_at' => now()->toDateString(),
            'status' => 'issued',
            'total' => 1,
        ])->assertCreated()->json('data');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice['id'],
            'status' => 'draft',
            'subtotal' => 2000,
            'discount_total' => 100,
            'tax_total' => 361,
            'total' => 2261,
        ]);
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice['id'],
            'discount_amount' => 100,
            'line_total' => 2261,
        ]);

        $this->patchJson('/api/v1/invoices/'.$invoice['id'], ['status' => 'issued'])->assertOk();
        $this->postJson('/api/v1/payments', [
            'invoice_id' => $invoice['id'],
            'paid_at' => now()->toIso8601String(),
            'amount' => 2262,
            'method' => 'transfer',
        ])->assertUnprocessable();
    }

    public function test_kpi_endpoint_returns_operational_summary(): void
    {
        $this->getJson('/api/v1/analytics/kpis')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'equipment' => ['total', 'active', 'maintenance'],
                    'work_orders' => ['total', 'by_status', 'overdue'],
                    'inventory' => ['active_parts', 'below_minimum'],
                    'finance' => ['unpaid_invoices', 'outstanding_balance'],
                ],
            ]);

        $this->getJson('/api/v1/analytics/anomalies')->assertOk();
        $this->getJson('/api/v1/analytics/predictions')->assertOk();
    }

    public function test_work_order_status_change_is_validated_and_recorded(): void
    {
        $client = Client::query()->create(['name' => 'Cliente órdenes', 'nit' => '901000000-1']);
        $building = Building::query()->create([
            'client_id' => $client->id,
            'name' => 'Torre de prueba',
            'address' => 'Calle 1',
        ]);
        $equipment = Equipment::query()->create([
            'building_id' => $building->id,
            'code' => 'ASC-TEST-01',
            'type' => 'elevator',
        ]);

        $order = $this->postJson('/api/v1/work-orders', [
            'number' => 'OT-TEST-001',
            'client_id' => $client->id,
            'building_id' => $building->id,
            'equipment_id' => $equipment->id,
            'service_type' => 'preventive',
            'status' => 'scheduled',
        ])->assertCreated()->json('data');

        $this->patchJson('/api/v1/work-orders/'.$order['id'], ['status' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->assertDatabaseHas('work_order_events', [
            'work_order_id' => $order['id'],
            'from_status' => 'scheduled',
            'to_status' => 'in_progress',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'model' => WorkOrder::class,
            'model_id' => $order['id'],
            'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_attachment_upload_is_stored_and_downloadable_by_owner(): void
    {
        Storage::fake('local');
        $client = Client::query()->create(['name' => 'Cliente adjuntos', 'nit' => '901000000-2']);

        $response = $this->postJson('/api/v1/attachments/upload', [
            'attachable_type' => 'client',
            'attachable_id' => $client->id,
            'category' => 'informe',
            'file' => UploadedFile::fake()->create('informe.pdf', 20, 'application/pdf'),
        ])->assertCreated();

        $attachmentId = $response->json('data.id');
        Storage::disk('local')->assertExists($response->json('data.path'));

        $this->get('/api/v1/attachments/'.$attachmentId.'/download')->assertOk();
    }

    public function test_iot_reading_requires_sensor_and_equipment_to_match(): void
    {
        $client = Client::query()->create(['name' => 'Cliente IoT', 'nit' => '901000000-4']);
        $building = Building::query()->create([
            'client_id' => $client->id,
            'name' => 'Torre IoT',
            'address' => 'Calle 2',
        ]);
        $equipment = Equipment::query()->create([
            'building_id' => $building->id,
            'code' => 'ASC-IOT-01',
            'type' => 'elevator',
        ]);
        $otherEquipment = Equipment::query()->create([
            'building_id' => $building->id,
            'code' => 'ASC-IOT-02',
            'type' => 'elevator',
        ]);
        $device = $this->postJson('/api/v1/iot/devices', [
            'device_id' => 'gateway-001',
            'equipment_id' => $equipment->id,
            'manufacturer' => 'SensorCo',
            'status' => 'active',
        ])->assertCreated()->json('data');
        $sensor = $this->postJson('/api/v1/iot/sensors', [
            'iot_device_id' => $device['id'],
            'sensor_id' => 'temp-01',
            'sensor_type' => 'temperature',
            'unit' => 'C',
        ])->assertCreated()->json('data');

        $this->postJson('/api/v1/iot/readings', [
            'iot_sensor_id' => $sensor['id'],
            'equipment_id' => $equipment->id,
            'value' => 24.5,
            'unit' => 'C',
            'recorded_at' => now()->toIso8601String(),
        ])->assertCreated();

        $this->postJson('/api/v1/iot/readings', [
            'iot_sensor_id' => $sensor['id'],
            'equipment_id' => $otherEquipment->id,
            'value' => 24.5,
            'unit' => 'C',
            'recorded_at' => now()->toIso8601String(),
        ])->assertUnprocessable();
    }

    public function test_ai_conversation_is_only_visible_to_its_owner(): void
    {
        $conversation = $this->postJson('/api/v1/ai/conversations', ['title' => 'Consulta de prueba'])
            ->assertCreated()
            ->json('data');
        $clientRole = Role::firstOrCreate(['name' => 'cliente'], ['description' => 'Cliente']);
        $otherUser = User::factory()->create(['role_id' => $clientRole->id, 'is_active' => true]);

        $this->actingAs($otherUser, 'sanctum')
            ->getJson('/api/v1/ai/conversations/'.$conversation['id'])
            ->assertNotFound();
    }

    public function test_api_exposes_spanish_routes_and_json_fields(): void
    {
        $created = $this->postJson('/api/v1/planes-mantenimiento', [
            'nombre' => 'Preventivo trimestral',
            'tipo_servicio' => 'preventive',
            'esta_activo' => true,
        ])->assertCreated()
            ->assertJsonPath('datos.nombre', 'Preventivo trimestral');

        $this->getJson('/api/v1/planes-mantenimiento?buscar=trimestral')
            ->assertOk()
            ->assertJsonPath('datos.0.id', $created->json('datos.id'));

        $client = Client::query()->create(['name' => 'Cliente API', 'nit' => '901000000-5']);
        $building = Building::query()->create([
            'client_id' => $client->id,
            'name' => 'Edificio API',
            'address' => 'Calle 10',
        ]);
        $equipment = Equipment::query()->create([
            'building_id' => $building->id,
            'code' => 'ASC-API-01',
            'type' => 'elevator',
        ]);

        $workOrder = $this->postJson('/api/v1/ordenes-trabajo', [
            'numero' => 'OT-API-001',
            'cliente_id' => $client->id,
            'edificio_id' => $building->id,
            'equipo_id' => $equipment->id,
            'tipo_servicio' => 'preventivo',
            'estado' => 'programada',
        ])->assertCreated()
            ->assertJsonPath('datos.tipo_servicio', 'preventivo')
            ->assertJsonPath('datos.estado', 'programada')
            ->json('datos');

        $this->patchJson('/api/v1/ordenes-trabajo/'.$workOrder['id'], [
            'estado' => 'en_progreso',
        ])->assertOk()->assertJsonPath('datos.estado', 'en_progreso');
    }

    public function test_spanish_api_returns_validation_errors_in_spanish(): void
    {
        $this->postJson('/api/v1/planes-mantenimiento', [])
            ->assertUnprocessable()
            ->assertJsonPath('errores.nombre.0', 'El campo nombre es obligatorio.')
            ->assertJsonMissingPath('errors');
    }

    public function test_spanish_api_returns_authentication_errors_in_spanish(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/ordenes-trabajo')
            ->assertUnauthorized()
            ->assertJsonPath('mensaje', 'Se requiere autenticación para acceder a este recurso.');
    }
}
