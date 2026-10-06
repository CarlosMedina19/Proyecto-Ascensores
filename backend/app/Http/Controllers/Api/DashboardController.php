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
        return response()->json([
            'success' => true,
            'message' => 'Dashboard FASE 1',
            'data' => [
                'users' => [
                    'total'  => User::count(),
                    'active' => User::where('is_active', true)->count(),
                ],
                'clients' => [
                    'total'  => Client::count(),
                    'active' => Client::where('status', true)->count(),
                ],
                'buildings' => [
                    'total' => Building::count(),
                ],
                'equipment' => [
                    'total'          => Equipment::count(),
                    'elevators'      => Equipment::where('type', 'elevator')->count(),
                    'electric_doors' => Equipment::where('type', 'electric_door')->count(),
                    'active'         => Equipment::where('status', 'active')->count(),
                ],
            ],
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}