<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Equipment\StoreEquipmentHistoryRequest;
use App\Http\Requests\Equipment\StoreEquipmentRequest;
use App\Http\Requests\Equipment\UpdateEquipmentRequest;
use App\Http\Resources\EquipmentHistoryResource;
use App\Http\Resources\EquipmentResource;
use App\Models\Equipment;
use App\Services\EquipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EquipmentController extends Controller
{
    public function __construct(
        protected EquipmentService $equipmentService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $equipment = $this->equipmentService->list($request->all(), (int) $request->input('per_page', 15));
        return EquipmentResource::collection($equipment);
    }

    public function store(StoreEquipmentRequest $request): JsonResponse
    {
        $equipment = $this->equipmentService->create($request->validated());
        return (new EquipmentResource($equipment))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Equipment $equipment): EquipmentResource
    {
        return new EquipmentResource(
            $equipment->load(['building.client', 'elevator', 'electricDoor', 'history.user'])
        );
    }

    public function update(UpdateEquipmentRequest $request, Equipment $equipment): EquipmentResource
    {
        $updated = $this->equipmentService->update($equipment, $request->validated());
        return new EquipmentResource($updated);
    }

    public function destroy(Equipment $equipment): JsonResponse
    {
        $this->equipmentService->delete($equipment);
        return response()->json([
            'success' => true,
            'message' => 'Equipo eliminado correctamente',
        ]);
    }

    public function history(Equipment $equipment): AnonymousResourceCollection
    {
        $history = $equipment->history()->with('user')->latest()->get();
        return EquipmentHistoryResource::collection($history);
    }

    public function addHistory(StoreEquipmentHistoryRequest $request, Equipment $equipment): JsonResponse
    {
        $history = $this->equipmentService->addHistory($equipment, $request->validated());
        return (new EquipmentHistoryResource($history))
            ->response()
            ->setStatusCode(201);
    }
}
