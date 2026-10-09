<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class AuditLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AuditLog::class);

        $query = AuditLog::with('user');

        if ($request->filled('action')) {
            $query->where('accion', $request->input('action'));
        }

        if ($request->filled('model')) {
            $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where('modelo', $like, '%'.$request->input('model').'%');
        }

        if ($request->filled('user_id')) {
            $query->where('usuario_id', $request->input('user_id'));
        }

        $perPage = max(1, min((int) $request->input('per_page', 20), 100));
        $logs = $query->latest('created_at')->paginate($perPage);

        return AuditLogResource::collection($logs);
    }
}
