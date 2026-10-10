<?php

namespace Tests\Feature;

use App\Models\User;
use App\Auth\SpanishPasswordBrokerManager;
use App\Database\DatabaseNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatabaseRequirementsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_electric_door_requirement_tables_are_migrated(): void
    {
        $tables = [
            'usuarios',
            'roles',
            'permisos',
            'roles_permisos',
            'registros_auditoria',
            'clientes',
            'contactos_clientes',
            'edificios',
            'equipos',
            'ascensores',
            'historial_equipos',
            'planes_mantenimiento',
            'asignaciones_planes',
            'historial_precios_planes',
            'contratos',
            'equipos_contratos',
            'tecnicos',
            'ordenes_trabajo',
            'tecnicos_ordenes_trabajo',
            'programaciones_mantenimiento',
            'eventos_ordenes_trabajo',
            'plantillas_verificacion',
            'secciones_verificacion',
            'elementos_verificacion',
            'verificaciones_ordenes_trabajo',
            'repuestos',
            'repuestos_ordenes_trabajo',
            'movimientos_inventario',
            'cotizaciones',
            'conceptos_cotizaciones',
            'facturas',
            'conceptos_facturas',
            'pagos',
            'movimientos_cuenta',
            'adjuntos',
            'firmas',
            'preferencias_notificacion',
            'dispositivos_iot',
            'sensores_iot',
            'lecturas_iot',
            'eventos_iot',
            'reglas_alerta_iot',
            'alertas_iot',
            'documentos_ia',
            'fragmentos_documentos_ia',
            'incrustaciones_ia',
            'conversaciones_ia',
            'mensajes_ia',
            'comentarios_ia',
            'predicciones_ia',
            'tokens_acceso_personal',
            'notificaciones',
            'tokens_restablecimiento_contrasena',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }

        $this->assertFalse(Schema::hasTable('electric_doors'));
        $this->assertFalse(Schema::hasTable('clients'));
        $this->assertTrue(Schema::hasColumn('clientes', 'identificador_universal'));
        $this->assertTrue(Schema::hasColumn('registros_auditoria', 'direccion_ip'));
        $this->assertTrue(Schema::hasColumn('ordenes_trabajo', 'estado'));
        $this->assertTrue(Schema::hasColumn('facturas', 'descuento_total'));
        $this->assertTrue(Schema::hasColumn('tokens_acceso_personal', 'autenticable_id'));
        $this->assertTrue(Schema::hasColumn('notificaciones', 'notificable_tipo'));
        $this->assertTrue(Schema::hasColumn('tokens_restablecimiento_contrasena', 'correo_electronico'));

        $englishColumns = array_keys(array_filter(
            DatabaseNames::columns(),
            static fn (string $spanish, string $english): bool => $spanish !== $english,
            ARRAY_FILTER_USE_BOTH
        ));

        foreach (DatabaseNames::tables() as $spanishTable) {
            $remainingEnglishColumns = array_values(array_intersect(
                $englishColumns,
                Schema::getColumnListing($spanishTable)
            ));

            $this->assertSame([], $remainingEnglishColumns, "English columns remain in {$spanishTable}.");
        }
    }

    public function test_framework_authentication_storage_uses_spanish_identifiers(): void
    {
        $this->assertInstanceOf(SpanishPasswordBrokerManager::class, Password::getFacadeRoot());

        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->assertTrue(Password::broker()->tokenExists($user, $token));
        $this->assertDatabaseHas('tokens_restablecimiento_contrasena', [
            'correo_electronico' => $user->email,
        ]);

        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test.notification',
            'data' => ['message' => 'ok'],
        ]);

        $this->assertSame('test.notification', $user->notifications()->firstOrFail()->type);
    }

    public function test_legacy_electric_doors_table_and_columns_are_renamed_without_losing_data(): void
    {
        Schema::create('electric_doors', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('equipment_id');
            $table->string('brand')->nullable();
            $table->string('door_type')->nullable();
            $table->string('opening_type')->nullable();
            $table->string('access_type')->nullable();
            $table->string('serial_number')->nullable();
            $table->text('technical_specs')->nullable();
            $table->timestamps();
        });
        DB::statement(
            "INSERT INTO electric_doors (equipment_id, brand, door_type, opening_type, access_type, serial_number, technical_specs) VALUES (1, 'Marca de prueba', 'corrediza', 'doble', 'entrada', 'PUE-001', 'Datos de prueba')"
        );

        $migration = require database_path(
            'migrations/2026_10_08_200008_translate_electric_doors_table_to_spanish.php'
        );
        $migration->up();

        $this->assertTrue(Schema::hasTable('puertas_electricas'));
        $this->assertFalse(Schema::hasTable('electric_doors'));
        $this->assertTrue(Schema::hasColumn('puertas_electricas', 'equipo_id'));
        $this->assertTrue(Schema::hasColumn('puertas_electricas', 'tipo_apertura'));
        $this->assertTrue(Schema::hasColumn('puertas_electricas', 'tipo_acceso'));
        $this->assertDatabaseHas('puertas_electricas', [
            'numero_serie' => 'PUE-001',
            'tipo_apertura' => 'doble',
        ]);
    }
}
