<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Departamentos y municipios de Colombia (resources/datos/colombia.json).
 * Normaliza lo que escribe el usuario ("ibague", "tolima") al nombre oficial ("Ibagué", "Tolima").
 */
class Colombia
{
    private static ?array $datos = null;

    /** @return array<string, list<string>> departamento => municipios */
    public static function datos(): array
    {
        return self::$datos ??= json_decode(file_get_contents(resource_path('datos/colombia.json')), true);
    }

    public static function departamento(?string $texto): ?string
    {
        $clave = self::clave($texto);
        if ($clave === '') {
            return null;
        }
        foreach (array_keys(self::datos()) as $departamento) {
            if (self::clave($departamento) === $clave) {
                return $departamento;
            }
        }
        // "Bogotá", "Bogota DC" o "Distrito Capital" también nombran a Bogotá D.C.
        return in_array($clave, ['bogota', 'bogota dc', 'distrito capital'], true) ? 'Bogotá D.C.' : null;
    }

    /** Municipio con su nombre oficial dentro del departamento indicado. */
    public static function ciudad(?string $texto, string $departamento): ?string
    {
        $clave = self::clave($texto);
        foreach (self::datos()[$departamento] ?? [] as $ciudad) {
            if (self::clave($ciudad) === $clave) {
                return $ciudad;
            }
        }

        return null;
    }

    /**
     * Nombre oficial de la ciudad y su departamento, o null si la ciudad no pertenece al departamento.
     * Sin departamento, se deduce cuando el municipio solo existe en uno.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function ubicacion(?string $ciudad, ?string $departamento): ?array
    {
        // Bogotá es un distrito capital, no un municipio de Cundinamarca.
        if (self::clave($ciudad) === 'bogota' || self::clave($ciudad) === 'bogota d c') {
            return ['Bogotá', 'Bogotá D.C.'];
        }
        // Nombre corto de uso común.
        if (self::clave($ciudad) === 'cartagena' && in_array(self::clave($departamento), ['', 'bolivar'], true)) {
            return ['Cartagena de Indias', 'Bolívar'];
        }
        $departamento = blank($departamento) ? self::departamentoDe($ciudad) : self::departamento($departamento);
        $oficial = $departamento ? self::ciudad($ciudad, $departamento) : null;

        return $oficial ? [$oficial, $departamento] : null;
    }

    /**
     * Departamento de un municipio cuando no se indicó (solo si el nombre no se repite en otro departamento).
     */
    public static function departamentoDe(?string $ciudad): ?string
    {
        $clave = self::clave($ciudad);
        $encontrados = array_keys(array_filter(self::datos(), fn ($ciudades) => in_array($clave, array_map(self::clave(...), $ciudades), true)));

        return count($encontrados) === 1 ? $encontrados[0] : null;
    }

    private static function clave(?string $texto): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii((string) $texto))));
    }
}
