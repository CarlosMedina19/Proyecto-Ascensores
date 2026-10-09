<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewDashboard');

        return response()->json([
            'success' => true,
            'message' => 'Dashboard FASE 1',
            'data' => [
                'users' => [
                    'total' => User::count(),
                    'active' => User::where('activo', true)->count(),
                ],
                'clients' => [
                    'total' => Client::count(),
                    'active' => Client::where('estado', true)->count(),
                ],
                'buildings' => [
                    'total' => Building::count(),
                ],
                'equipment' => [
                    'total' => Equipment::count(),
                    'active' => Equipment::where('estado', 'active')->count(),
                    'inactive' => Equipment::where('estado', 'inactive')->count(),
                    'maintenance' => Equipment::where('estado', 'maintenance')->count(),
                ],
            ],
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
