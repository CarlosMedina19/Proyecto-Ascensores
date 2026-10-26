<?php

namespace App\Support\Excel;

use App\Models\Ascensor;
use App\Models\ChecklistItem;
use App\Models\Edificio;
use App\Models\Pago;
use App\Models\PuertaPiso;

/** Valores internos ↔ etiquetas de las listas de la plantilla. */
class Opciones
{
    public const TIPOS_PERSONA = ['juridica' => 'Persona jurídica', 'natural' => 'Persona natural'];

    public const ESTADOS_REGISTRO = ['1' => 'Activo', '0' => 'Inactivo'];

    public const ESTADOS_EQUIPO = ['activo' => 'Activo', 'mantenimiento' => 'En mantenimiento', 'inactivo' => 'Inactivo'];

    public const TIPOS_ORDEN = ['preventivo' => 'Mantenimiento preventivo', 'correctivo' => 'Mantenimiento correctivo', 'inspeccion' => 'Inspección'];

    public const PRIORIDADES = ['baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta', 'critica' => 'Crítica'];

    public const ESTADOS_ORDEN = [
        'programada' => 'Programada', 'asignada' => 'Asignada', 'en_camino' => 'En camino', 'en_proceso' => 'En proceso',
        'pendiente' => 'Pendiente', 'finalizada' => 'Finalizada', 'cancelada' => 'Cancelada',
    ];

    public const ZONAS = [
        'cuarto_maquinas' => 'Cuarto de máquinas', 'cabina' => 'Cabina', 'contrapeso' => 'Contrapeso',
        'puertas' => 'Puertas', 'hueco' => 'Hueco y recorrido', 'foso' => 'Foso',
    ];

    public const TIPOS_REVISION = ['INS' => 'INS', 'EST' => 'EST', 'NA' => 'N.A.'];

    public const ROLES = [
        'administrador' => 'Administrador', 'coordinador' => 'Coordinador', 'tecnico' => 'Técnico',
        'contador' => 'Contador', 'supervisor' => 'Supervisor', 'cliente' => 'Cliente',
    ];

    public const ESTADOS_FACTURA = ['pendiente' => 'Pendiente', 'parcial' => 'Abono', 'pagada' => 'Pagada'];

    public static function tiposEdificio(): array
    {
        return Edificio::TIPOS;
    }

    public static function tiposAscensor(): array
    {
        return Ascensor::TIPOS;
    }

    public static function tiposPuertaPiso(): array
    {
        return PuertaPiso::TIPOS;
    }

    public static function estadosPuerta(): array
    {
        return PuertaPiso::ESTADOS;
    }

    public static function mediosPago(): array
    {
        return Pago::MEDIOS;
    }

    /** Zona: acepta la etiqueta corta de la plantilla o la del catálogo ("Puertas de cabina y de piso"). */
    public static function zona(mixed $valor): ?string
    {
        $zona = Plantilla::opcion($valor, self::ZONAS);

        return array_key_exists((string) $zona, self::ZONAS) ? $zona : Plantilla::opcion($valor, ChecklistItem::SECCIONES);
    }
}
