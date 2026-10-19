<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Tamaño de página solicitado, limitado entre 1 y 100 registros.
     */
    protected function porPagina(Request $solicitud, int $porDefecto = 15): int
    {
        return max(1, min((int) $solicitud->input('por_pagina', $porDefecto), 100));
    }
}
