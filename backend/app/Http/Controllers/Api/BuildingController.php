<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Building\StoreBuildingRequest;
use App\Http\Requests\Building\UpdateBuildingRequest;
use App\Http\Resources\BuildingResource;
use App\Models\Building;
use App\Services\BuildingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BuildingController extends Controller
{
    public function __construct(
        protected BuildingService $buildingService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $buildings = $this->buildingService->list($request->all(), (int) $request->input('per_page', 15));
        return BuildingResource::collection($buildings);
    }

    public function store(StoreBuildingRequest $request): JsonResponse
    {
        $building = $this->buildingService->create($request->validated());
        return (new BuildingResource($building))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Building $building): BuildingResource
    {
        return new BuildingResource($building->load(['client', 'equipment.elevator', 'equipment.electricDoor']));
    }

    public function update(UpdateBuildingRequest $request, Building $building): BuildingResource
    {
        $updated = $this->buildingService->update($building, $request->validated());
        return new BuildingResource($updated);
    }

    public function destroy(Building $building): JsonResponse
    {
        $this->buildingService->delete($building);
        return response()->json([
            'success' => true,
            'message' => 'Edificio eliminado correctamente',
        ]);
    }
}
