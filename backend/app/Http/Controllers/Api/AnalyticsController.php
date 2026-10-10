<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiPrediction;
use App\Models\Equipment;
use App\Models\Invoice;
use App\Models\IotAlert;
use App\Models\MaintenanceSchedule;
use App\Models\Part;
use App\Models\WorkOrder;
use App\Models\WorkOrderChecklist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AnalyticsController extends Controller
{
    public function kpis(): JsonResponse
    {
        $workOrdersByStatus = WorkOrder::query()
            ->selectRaw('estado, COUNT(*) AS total')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn (WorkOrder $workOrder): array => [$workOrder->status => $workOrder->total]);

        $issuedInvoices = Invoice::query()
            ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
            ->withSum('payments', 'amount')
            ->get();
        $outstanding = $issuedInvoices->sum(
            fn (Invoice $invoice): float => max((float) $invoice->total - (float) $invoice->payments_sum_amount, 0)
        );

        return response()->json([
            'success' => true,
            'data' => [
                'equipment' => [
                    'total' => Equipment::query()->count(),
                    'active' => Equipment::query()->where('status', 'active')->count(),
                    'maintenance' => Equipment::query()->where('status', 'maintenance')->count(),
                ],
                'work_orders' => [
                    'total' => WorkOrder::query()->count(),
                    'by_status' => $workOrdersByStatus,
                    'overdue' => WorkOrder::query()
                        ->whereNotIn('status', ['completed', 'cancelled'])
                        ->where('scheduled_at', '<', now())
                        ->count(),
                ],
                'maintenance_due_next_30_days' => MaintenanceSchedule::query()
                    ->where('status', 'scheduled')
                    ->whereBetween('scheduled_at', [now(), now()->addDays(30)])
                    ->count(),
                'inventory' => [
                    'active_parts' => Part::query()->where('is_active', true)->count(),
                    'below_minimum' => Part::query()->whereColumn('stock_quantity', '<=', 'minimum_stock')->count(),
                ],
                'finance' => [
                    'unpaid_invoices' => $issuedInvoices->count(),
                    'outstanding_balance' => round($outstanding, 2),
                ],
            ],
        ]);
    }

    public function anomalies(): JsonResponse
    {
        $alerts = IotAlert::query()
            ->whereIn('status', ['open', 'acknowledged'])
            ->with(['equipment:id,code,brand,model'])
            ->latest('triggered_at')
            ->paginate(20);
        $checklistFindings = WorkOrderChecklist::query()
            ->whereIn('condition', ['R', 'M'])
            ->with(['workOrder:id,number,equipment_id,status'])
            ->latest('completed_at')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'iot_alerts' => $alerts,
                'checklist_findings' => $checklistFindings,
            ],
        ]);
    }

    public function predictions(Request $request): JsonResponse
    {
        $filters = Validator::make($request->query(), [
            'equipment_id' => 'sometimes|integer|exists:equipment,id',
            'prediction_type' => 'sometimes|string|max:100',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ])->validate();

        $query = AiPrediction::query()->latest('predicted_at');
        if (isset($filters['equipment_id'])) {
            $query->where('equipment_id', $filters['equipment_id']);
        }
        if (isset($filters['prediction_type'])) {
            $query->where('prediction_type', $filters['prediction_type']);
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate((int) ($filters['per_page'] ?? 20)),
        ]);
    }
}
