<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiDocument;
use App\Models\Attachment;
use App\Models\Building;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class AttachmentController extends Controller
{
    private const ATTACHABLE_TYPES = [
        'ai_document' => AiDocument::class,
        'building' => Building::class,
        'client' => Client::class,
        'equipment' => Equipment::class,
        'invoice' => Invoice::class,
        'quotation' => Quotation::class,
        'work_order' => WorkOrder::class,
    ];

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'attachable_type' => ['required', Rule::in(array_keys(self::ATTACHABLE_TYPES))],
            'attachable_id' => ['required', 'integer', 'min:1'],
            'category' => ['nullable', 'string', 'max:80'],
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $model = self::ATTACHABLE_TYPES[$data['attachable_type']];
        $model::query()->findOrFail($data['attachable_id']);

        $file = $request->file('file');
        $path = $file->store('attachments', 'local');
        if ($path === false) {
            throw new RuntimeException('No se pudo guardar el archivo adjunto.');
        }

        $attachment = Attachment::query()->create([
            'uuid' => (string) Str::uuid(),
            'attachable_type' => $model,
            'attachable_id' => $data['attachable_id'],
            'uploaded_by' => $request->user()->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'category' => $data['category'] ?? null,
        ]);
        AuditService::log('created', $attachment, $attachment->toArray());

        return response()->json(['success' => true, 'data' => $attachment], 201);
    }

    public function download(Attachment $attachment)
    {
        abort_unless(
            (int) $attachment->uploaded_by === (int) request()->user()->id
                || $this->isStaff(request()->user()),
            403
        );
        abort_unless($attachment->disk === 'local', 404);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    private function isStaff(User $user): bool
    {
        return $user->role()
            ->whereIn('name', ['admin', 'coordinador', 'tecnico', 'contador', 'supervisor'])
            ->exists();
    }
}
