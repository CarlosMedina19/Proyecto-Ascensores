<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Disco donde SIGMA guarda fotos, firmas, certificados e informes PDF.
 *
 * En producción es el Object Storage (S3 compatible, bucket de SIGMA, disco «objetos»); en desarrollo y
 * en pruebas puede ser el disco local. Se elige con SIGMA_DISCO_ARCHIVOS. La base de datos guarda solo
 * la clave del objeto (ruta relativa), así cambiar de disco no exige tocar los registros.
 * Los archivos son privados: siempre se sirven a través de la API, que revisa los permisos.
 */
class Archivos
{
    public static function nombreDisco(): string
    {
        return (string) config('filesystems.archivos', 'local');
    }

    public static function disco(): Filesystem
    {
        return Storage::disk(self::nombreDisco());
    }

    /** Envía el archivo al navegador sin copiarlo al servidor (se transmite desde el disco). */
    public static function responder(string $ruta, string $nombre, ?string $tipo = null, bool $descargar = false): StreamedResponse
    {
        $disco = self::disco();
        abort_unless($disco->exists($ruta), 404, 'El archivo no está disponible.');
        $cabeceras = array_filter([
            'Content-Type' => $tipo ?: $disco->mimeType($ruta) ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        return $descargar
            ? $disco->download($ruta, $nombre, $cabeceras)
            : $disco->response($ruta, $nombre, $cabeceras);
    }
}
