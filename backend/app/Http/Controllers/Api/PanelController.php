<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PanelService;
use Illuminate\Http\JsonResponse;

/**
 * Panel de operación del personal administrativo (inicio del panel web).
 */
class PanelController extends Controller
{
    public function __invoke(PanelService $panel): JsonResponse
    {
        $this->authorize('verResumen');

        return response()->json(['datos' => $panel->obtener()]);
    }
}
