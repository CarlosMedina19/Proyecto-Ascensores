<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Colección de recursos con las claves en español:
 * { "datos": [...], "enlaces": {...}, "metadatos": {...} }
 */
class ColeccionApi extends AnonymousResourceCollection
{
    public static $wrap = 'datos';

    public function paginationInformation(Request $solicitud, array $paginado, array $porDefecto): array
    {
        $meta = $porDefecto['meta'];
        $enlaces = $porDefecto['links'];

        return [
            'enlaces' => [
                'primera' => $enlaces['first'] ?? null,
                'ultima' => $enlaces['last'] ?? null,
                'anterior' => $enlaces['prev'] ?? null,
                'siguiente' => $enlaces['next'] ?? null,
            ],
            'metadatos' => [
                'pagina_actual' => $meta['current_page'],
                'ultima_pagina' => $meta['last_page'],
                'por_pagina' => $meta['per_page'],
                'desde' => $meta['from'],
                'hasta' => $meta['to'],
                'total' => $meta['total'],
            ],
        ];
    }
}
