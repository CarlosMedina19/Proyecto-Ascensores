<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class RecursoApi extends JsonResource
{
    public static $wrap = 'datos';

    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        $metadata = $default['meta'];
        $metadata['pagina_actual'] = $metadata['current_page'];
        $metadata['desde'] = $metadata['from'];
        $metadata['ultima_pagina'] = $metadata['last_page'];
        $metadata['por_pagina'] = $metadata['per_page'];
        $metadata['hasta'] = $metadata['to'];
        unset($metadata['current_page'], $metadata['from'], $metadata['last_page'], $metadata['per_page'], $metadata['to']);

        $links = $default['links'];
        $links = [
            'primera' => $links['first'],
            'ultima' => $links['last'],
            'anterior' => $links['prev'],
            'siguiente' => $links['next'],
        ];

        return ['enlaces' => $links, 'metadatos' => $metadata];
    }
}
