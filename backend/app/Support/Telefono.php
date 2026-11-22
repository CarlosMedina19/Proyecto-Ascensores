<?php

namespace App\Support;

/** Números de celular de Colombia: se guardan con 10 dígitos, sin espacios ni el indicativo +57. */
final class Telefono
{
    public static function normalizar(?string $valor): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $valor);
        if (strlen($digitos) === 12 && str_starts_with($digitos, '57')) {
            $digitos = substr($digitos, 2);
        }

        return preg_match('/^3\d{9}$/', $digitos) ? $digitos : null;
    }

    /** Formato internacional para el operador de SMS (+573001234567). */
    public static function internacional(string $telefono): string
    {
        return '+57'.$telefono;
    }

    /** 300 *** **67: suficiente para que la persona reconozca su número sin revelarlo completo. */
    public static function enmascarar(?string $telefono): string
    {
        $telefono = (string) $telefono;

        return strlen($telefono) === 10 ? substr($telefono, 0, 3).' *** **'.substr($telefono, -2) : '***';
    }

    /** si***09@gmail.com */
    public static function enmascararCorreo(string $correo): string
    {
        [$usuario, $dominio] = array_pad(explode('@', $correo, 2), 2, '');
        $visible = mb_substr($usuario, 0, 2);
        $final = mb_strlen($usuario) > 4 ? mb_substr($usuario, -2) : '';

        return $visible.'***'.$final.'@'.$dominio;
    }
}
