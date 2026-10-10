<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountMovement;
use App\Models\AiConversation;
use App\Models\AiDocument;
use App\Models\AiDocumentChunk;
use App\Models\AiEmbedding;
use App\Models\AiFeedback;
use App\Models\AiMessage;
use App\Models\AiPrediction;
use App\Models\Attachment;
use App\Models\Building;
use App\Models\ChecklistItem;
use App\Models\ChecklistSection;
use App\Models\ChecklistTemplate;
use App\Models\Contract;
use App\Models\ContractEquipment;
use App\Models\Equipment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\IotAlert;
use App\Models\IotAlertRule;
use App\Models\IotDevice;
use App\Models\IotEvent;
use App\Models\IotReading;
use App\Models\IotSensor;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceSchedule;
use App\Models\NotificationPreference;
use App\Models\Part;
use App\Models\Payment;
use App\Models\PlanAssignment;
use App\Models\PlanPriceHistory;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Signature;
use App\Models\StockMovement;
use App\Models\Technician;
use App\Models\WorkOrder;
use App\Models\WorkOrderChecklist;
use App\Models\WorkOrderEvent;
use App\Models\WorkOrderPart;
use App\Models\WorkOrderTechnician;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DomainApiController extends Controller
{
    private const RESOURCES = [
        'maintenance-plans' => [
            'model' => MaintenancePlan::class, 'uuid' => true, 'deletable' => true,
            'fields' => [
                'name' => 'required|string|max:255', 'description' => 'nullable|string',
                'service_type' => 'required|string|max:80', 'is_active' => 'sometimes|boolean',
            ],
            'search' => ['name', 'service_type'], 'filters' => ['service_type', 'is_active'],
        ],
        'plan-assignments' => [
            'model' => PlanAssignment::class, 'uuid' => true, 'deletable' => false,
            'fields' => [
                'maintenance_plan_id' => 'required|integer|exists:maintenance_plans,id',
                'equipment_id' => 'required|integer|exists:equipment,id',
                'periodicity_months' => 'required|integer|min:1|max:12',
                'price' => 'required|numeric|min:0', 'tax_rate' => 'sometimes|numeric|min:0|max:100',
                'starts_at' => 'required|date', 'ends_at' => 'nullable|date|after_or_equal:starts_at',
                'next_maintenance_at' => 'nullable|date',
                'status' => 'sometimes|in:active,inactive,expired',
                'price_change_reason' => 'nullable|string|max:1000',
            ],
            'search' => [], 'filters' => ['equipment_id', 'maintenance_plan_id', 'status'],
        ],
        'plan-price-history' => [
            'model' => PlanPriceHistory::class, 'uuid' => false, 'readonly' => true, 'creatable' => false,
            'fields' => [
                'plan_assignment_id' => 'required|integer|exists:plan_assignments,id',
                'previous_price' => 'nullable|numeric|min:0', 'new_price' => 'required|numeric|min:0',
                'reason' => 'nullable|string',
            ],
            'search' => ['reason'], 'filters' => ['plan_assignment_id'],
        ],
        'contracts' => [
            'model' => Contract::class, 'uuid' => true, 'deletable' => false,
            'fields' => [
                'client_id' => 'required|integer|exists:clients,id',
                'number' => 'required|string|max:100|unique:contracts,number',
                'starts_at' => 'required|date', 'ends_at' => 'nullable|date|after_or_equal:starts_at',
                'total_value' => 'sometimes|numeric|min:0', 'tax_configuration' => 'nullable|array',
                'terms' => 'nullable|string', 'status' => 'sometimes|in:draft,active,expired,cancelled',
            ],
            'search' => ['number', 'status'], 'filters' => ['client_id', 'status'],
        ],
        'contract-equipment' => [
            'model' => ContractEquipment::class, 'uuid' => false, 'deletable' => true,
            'fields' => [
                'contract_id' => 'required|integer|exists:contracts,id',
                'equipment_id' => 'required|integer|exists:equipment,id',
                'plan_assignment_id' => 'nullable|integer|exists:plan_assignments,id',
                'service_description' => 'nullable|string|max:255', 'price' => 'required|numeric|min:0',
            ],
            'search' => ['service_description'], 'filters' => ['contract_id', 'equipment_id'],
        ],
        'technicians' => [
            'model' => Technician::class, 'uuid' => true, 'deletable' => false,
            'fields' => [
                'user_id' => 'nullable|integer|exists:users,id|unique:technicians,user_id',
                'document_number' => 'nullable|string|max:100|unique:technicians,document_number',
                'phone' => 'nullable|string|max:60', 'specialty' => 'nullable|string|max:255',
                'availability' => 'nullable|array', 'status' => 'sometimes|in:active,inactive',
                'observations' => 'nullable|string',
            ],
            'search' => ['document_number', 'phone', 'specialty'], 'filters' => ['status', 'user_id'],
        ],
        'work-orders' => [
            'model' => WorkOrder::class, 'uuid' => true, 'deletable' => false,
            'fields' => [
                'number' => 'required|string|max:100|unique:work_orders,number',
                'client_id' => 'required|integer|exists:clients,id',
                'building_id' => 'required|integer|exists:buildings,id',
                'equipment_id' => 'required|integer|exists:equipment,id',
                'service_type' => 'required|string|max:80', 'scheduled_at' => 'nullable|date',
                'priority' => 'sometimes|in:low,medium,high,critical',
                'status' => 'sometimes|in:scheduled,assigned,on_the_way,in_progress,pending,completed,cancelled',
                'observations' => 'nullable|string', 'started_at' => 'nullable|date',
                'completed_at' => 'nullable|date|after_or_equal:started_at',
                'duration_minutes' => 'nullable|integer|min:0', 'source' => 'sometimes|string|max:80',
            ],
            'search' => ['number', 'service_type', 'status'], 'filters' => ['client_id', 'building_id', 'equipment_id', 'status', 'priority'],
        ],
        'work-order-technicians' => [
            'model' => WorkOrderTechnician::class, 'uuid' => false, 'deletable' => true,
            'fields' => [
                'work_order_id' => 'required|integer|exists:work_orders,id',
                'technician_id' => 'required|integer|exists:technicians,id',
                'assigned_at' => 'sometimes|date', 'accepted_at' => 'nullable|date',
            ],
            'search' => [], 'filters' => ['work_order_id', 'technician_id'],
        ],
        'maintenance-schedules' => [
            'model' => MaintenanceSchedule::class, 'uuid' => true, 'deletable' => false,
            'fields' => [
                'plan_assignment_id' => 'required|integer|exists:plan_assignments,id',
                'scheduled_at' => 'required|date', 'status' => 'sometimes|in:scheduled,completed,cancelled,skipped',
                'work_order_id' => 'nullable|integer|exists:work_orders,id|unique:maintenance_schedules,work_order_id',
                'completed_at' => 'nullable|date',
            ],
            'search' => ['status'], 'filters' => ['plan_assignment_id', 'status'],
        ],
        'work-order-events' => [
            'model' => WorkOrderEvent::class, 'uuid' => false, 'readonly' => true,
            'fields' => [
                'work_order_id' => 'required|integer|exists:work_orders,id', 'event' => 'required|string|max:100',
                'from_status' => 'nullable|in:scheduled,assigned,on_the_way,in_progress,pending,completed,cancelled',
                'to_status' => 'nullable|in:scheduled,assigned,on_the_way,in_progress,pending,completed,cancelled',
                'description' => 'nullable|string', 'metadata' => 'nullable|array',
            ],
            'search' => ['event', 'description'], 'filters' => ['work_order_id', 'event'],
        ],
        'checklist-templates' => [
            'model' => ChecklistTemplate::class, 'uuid' => true, 'deletable' => true,
            'fields' => [
                'name' => 'required|string|max:255', 'equipment_type' => 'sometimes|in:elevator',
                'service_type' => 'required|string|max:80', 'description' => 'nullable|string',
                'is_active' => 'sometimes|boolean',
            ],
            'search' => ['name', 'service_type'], 'filters' => ['service_type', 'is_active'],
        ],
        'checklist-sections' => [
            'model' => ChecklistSection::class, 'uuid' => false, 'deletable' => true,
            'fields' => [
                'checklist_template_id' => 'required|integer|exists:checklist_templates,id',
                'name' => 'required|string|max:255', 'description' => 'nullable|string',
                'sort_order' => 'sometimes|integer|min:0',
            ],
            'search' => ['name'], 'filters' => ['checklist_template_id'],
        ],
        'checklist-items' => [
            'model' => ChecklistItem::class, 'uuid' => false, 'deletable' => true,
            'fields' => [
                'checklist_section_id' => 'required|integer|exists:checklist_sections,id',
                'label' => 'required|string|max:255', 'instructions' => 'nullable|string',
                'requires_observation' => 'sometimes|boolean', 'sort_order' => 'sometimes|integer|min:0',
                'is_active' => 'sometimes|boolean',
            ],
            'search' => ['label'], 'filters' => ['checklist_section_id', 'is_active'],
        ],
        'work-order-checklist' => [
            'model' => WorkOrderChecklist::class, 'uuid' => false, 'deletable' => false,
            'fields' => [
                'work_order_id' => 'required|integer|exists:work_orders,id',
                'checklist_item_id' => 'nullable|integer|exists:checklist_items,id',
                'section_name' => 'required|string|max:255', 'item_label' => 'required|string|max:255',
                'result_code' => 'nullable|in:INS,EST,NA', 'condition' => 'nullable|in:B,R,M',
                'observations' => 'nullable|string', 'corrective_required' => 'sometimes|boolean',
            ],
            'search' => ['section_name', 'item_label'], 'filters' => ['work_order_id', 'result_code', 'condition'],
        ],
        'parts' => [
            'model' => Part::class, 'uuid' => true, 'deletable' => false,
            'fields' => [
                'code' => 'required|string|max:100|unique:parts,code', 'name' => 'required|string|max:255',
                'brand' => 'nullable|string|max:100', 'reference' => 'nullable|string|max:100',
                'unit' => 'sometimes|string|max:40', 'unit_price' => 'sometimes|numeric|min:0',
                'minimum_stock' => 'sometimes|numeric|min:0', 'is_active' => 'sometimes|boolean',
            ],
            'search' => ['code', 'name', 'brand', 'reference'], 'filters' => ['is_active'],
        ],
        'work-order-parts' => [
            'model' => WorkOrderPart::class, 'uuid' => false, 'deletable' => false,
            'fields' => [
                'work_order_id' => 'required|integer|exists:work_orders,id',
                'part_id' => 'required|integer|exists:parts,id', 'quantity' => 'required|numeric|gt:0',
                'unit_price' => 'required|numeric|min:0', 'observations' => 'nullable|string',
            ],
            'search' => ['observations'], 'filters' => ['work_order_id', 'part_id'],
        ],
        'stock-movements' => [
            'model' => StockMovement::class, 'uuid' => false, 'deletable' => false,
            'fields' => [
                'part_id' => 'required|integer|exists:parts,id', 'work_order_id' => 'nullable|integer|exists:work_orders,id',
                'movement_type' => 'required|in:in,out,adjustment,return',
                'quantity' => 'required|numeric|gt:0', 'unit_cost' => 'nullable|numeric|min:0',
                'reference' => 'nullable|string|max:255', 'observations' => 'nullable|string',
                'occurred_at' => 'sometimes|date',
            ],
            'search' => ['reference', 'observations'], 'filters' => ['part_id', 'work_order_id', 'movement_type'],
        ],
        'quotations' => [
            'model' => Quotation::class, 'uuid' => true, 'deletable' => false,
            'fields' => [
                'number' => 'required|string|max:100|unique:quotations,number',
                'client_id' => 'required|integer|exists:clients,id', 'equipment_id' => 'nullable|integer|exists:equipment,id',
                'work_order_id' => 'nullable|integer|exists:work_orders,id', 'issued_at' => 'required|date',
                'valid_until' => 'nullable|date|after_or_equal:issued_at',
                'tax_rate' => 'sometimes|numeric|min:0|max:100',
                'status' => 'sometimes|in:draft,sent,approved,rejected,expired',
                'notes' => 'nullable|string',
            ],
            'search' => ['number', 'status'], 'filters' => ['client_id', 'equipment_id', 'status'],
        ],
        'quotation-items' => [
            'model' => QuotationItem::class, 'uuid' => false, 'deletable' => true,
            'fields' => [
                'quotation_id' => 'required|integer|exists:quotations,id', 'part_id' => 'nullable|integer|exists:parts,id',
                'description' => 'required|string|max:1000', 'quantity' => 'sometimes|numeric|gt:0',
                'unit_price' => 'required|numeric|min:0', 'discount_amount' => 'sometimes|numeric|min:0',
                'tax_rate' => 'sometimes|numeric|min:0|max:100',
            ],
            'search' => ['description'], 'filters' => ['quotation_id', 'part_id'],
        ],
        'invoices' => [
            'model' => Invoice::class, 'uuid' => true, 'deletable' => false,
            'fields' => [
                'number' => 'required|string|max:100|unique:invoices,number',
                'client_id' => 'required|integer|exists:clients,id', 'quotation_id' => 'nullable|integer|exists:quotations,id',
                'work_order_id' => 'nullable|integer|exists:work_orders,id', 'issued_at' => 'required|date',
                'due_at' => 'nullable|date|after_or_equal:issued_at', 'currency' => 'sometimes|string|size:3',
                'tax_status' => 'sometimes|in:taxable,exempt,not_applicable',
                'status' => 'sometimes|in:draft,issued,void',
                'tax_configuration' => 'nullable|array', 'notes' => 'nullable|string',
            ],
            'search' => ['number', 'status'], 'filters' => ['client_id', 'status'],
        ],
        'invoice-items' => [
            'model' => InvoiceItem::class, 'uuid' => false, 'deletable' => false,
            'fields' => [
                'invoice_id' => 'required|integer|exists:invoices,id', 'part_id' => 'nullable|integer|exists:parts,id',
                'description' => 'required|string|max:1000', 'quantity' => 'sometimes|numeric|gt:0',
                'unit_price' => 'required|numeric|min:0', 'discount_amount' => 'sometimes|numeric|min:0',
                'tax_rate' => 'sometimes|numeric|min:0|max:100',
            ],
            'search' => ['description'], 'filters' => ['invoice_id', 'part_id'],
        ],
        'payments' => [
            'model' => Payment::class, 'uuid' => true, 'deletable' => false,
            'fields' => [
                'invoice_id' => 'required|integer|exists:invoices,id', 'paid_at' => 'required|date',
                'amount' => 'required|numeric|gt:0', 'method' => 'required|string|max:80',
                'reference' => 'nullable|string|max:255', 'document_path' => 'nullable|string|max:1000',
                'notes' => 'nullable|string',
            ],
            'search' => ['method', 'reference'], 'filters' => ['invoice_id', 'method'],
        ],
        'account-movements' => [
            'model' => AccountMovement::class, 'uuid' => false, 'readonly' => true, 'creatable' => false,
            'fields' => [
                'client_id' => 'required|integer|exists:clients,id', 'invoice_id' => 'nullable|integer|exists:invoices,id',
                'payment_id' => 'nullable|integer|exists:payments,id', 'movement_type' => 'required|string|max:80',
                'description' => 'required|string|max:255', 'debit' => 'sometimes|numeric|min:0',
                'credit' => 'sometimes|numeric|min:0', 'reference' => 'nullable|string|max:255',
                'occurred_at' => 'sometimes|date',
            ],
            'search' => ['description', 'reference'], 'filters' => ['client_id', 'invoice_id', 'movement_type'],
        ],
        'attachments' => [
            'model' => Attachment::class, 'uuid' => true, 'deletable' => false, 'creatable' => false, 'readonly' => true,
            'fields' => [
                'attachable_type' => 'required|string|max:255', 'attachable_id' => 'required|integer|min:1',
                'disk' => 'sometimes|string|max:100', 'path' => 'required|string|max:1000',
                'original_name' => 'required|string|max:255', 'mime_type' => 'nullable|string|max:150',
                'size_bytes' => 'nullable|integer|min:0', 'category' => 'nullable|string|max:80',
                'metadata' => 'nullable|array',
            ],
            'search' => ['original_name', 'category'], 'filters' => ['attachable_type', 'attachable_id', 'category'],
        ],
        'signatures' => [
            'model' => Signature::class, 'uuid' => true, 'deletable' => false,
            'fields' => [
                'signable_type' => 'required|string|max:255', 'signable_id' => 'required|integer|min:1',
                'signer_name' => 'required|string|max:255', 'signer_role' => 'required|string|max:100',
                'signature_path' => 'nullable|string|max:1000', 'signed_at' => 'sometimes|date',
                'metadata' => 'nullable|array',
            ],
            'search' => ['signer_name', 'signer_role'], 'filters' => ['signable_type', 'signable_id'],
        ],
        'notification-preferences' => [
            'model' => NotificationPreference::class, 'uuid' => false, 'deletable' => true,
            'fields' => [
                'user_id' => 'required|integer|exists:users,id', 'event_type' => 'required|string|max:100',
                'channel' => 'required|in:database,mail,sms,push', 'is_enabled' => 'sometimes|boolean',
                'settings' => 'nullable|array',
            ],
            'search' => ['event_type', 'channel'], 'filters' => ['user_id', 'event_type', 'channel', 'is_enabled'],
        ],
        'iot/devices' => [
            'model' => IotDevice::class, 'uuid' => true, 'deletable' => false,
            'fields' => [
                'device_id' => 'required|string|max:255|unique:iot_devices,device_id',
                'equipment_id' => 'required|integer|exists:equipment,id',
                'gateway_id' => 'nullable|integer|exists:iot_devices,id',
                'manufacturer' => 'nullable|string|max:255', 'firmware_version' => 'nullable|string|max:100',
                'status' => 'sometimes|in:active,inactive,maintenance', 'last_seen_at' => 'nullable|date',
                'metadata' => 'nullable|array',
            ],
            'search' => ['device_id', 'manufacturer', 'firmware_version'], 'filters' => ['equipment_id', 'status'],
        ],
        'iot/sensors' => [
            'model' => IotSensor::class, 'uuid' => true, 'deletable' => false,
            'fields' => [
                'iot_device_id' => 'required|integer|exists:iot_devices,id', 'sensor_id' => 'required|string|max:255',
                'sensor_type' => 'required|string|max:100', 'unit' => 'nullable|string|max:40',
                'status' => 'sometimes|in:active,inactive', 'configuration' => 'nullable|array',
            ],
            'search' => ['sensor_id', 'sensor_type'], 'filters' => ['iot_device_id', 'sensor_type', 'status'],
        ],
        'iot/readings' => [
            'model' => IotReading::class, 'uuid' => false, 'deletable' => false, 'readonly' => true,
            'fields' => [
                'iot_sensor_id' => 'required|integer|exists:iot_sensors,id',
                'equipment_id' => 'required|integer|exists:equipment,id', 'value' => 'required|numeric',
                'unit' => 'nullable|string|max:40', 'source' => 'nullable|string|max:100',
                'recorded_at' => 'required|date', 'metadata' => 'nullable|array',
            ],
            'search' => ['source'], 'filters' => ['iot_sensor_id', 'equipment_id'],
        ],
        'iot/events' => [
            'model' => IotEvent::class, 'uuid' => true, 'deletable' => false, 'readonly' => true,
            'fields' => [
                'iot_device_id' => 'nullable|integer|exists:iot_devices,id',
                'equipment_id' => 'required|integer|exists:equipment,id',
                'event_type' => 'required|string|max:100', 'severity' => 'sometimes|in:info,warning,error,critical',
                'data' => 'nullable|array', 'occurred_at' => 'required|date',
            ],
            'search' => ['event_type', 'severity'], 'filters' => ['equipment_id', 'iot_device_id', 'severity'],
        ],
        'iot/alert-rules' => [
            'model' => IotAlertRule::class, 'uuid' => true, 'deletable' => true,
            'fields' => [
                'equipment_id' => 'nullable|integer|exists:equipment,id',
                'iot_sensor_id' => 'nullable|integer|exists:iot_sensors,id',
                'name' => 'required|string|max:255', 'severity' => 'sometimes|in:info,warning,error,critical',
                'condition' => 'required|array', 'cooldown_minutes' => 'sometimes|integer|min:0',
                'is_active' => 'sometimes|boolean',
            ],
            'search' => ['name'], 'filters' => ['equipment_id', 'iot_sensor_id', 'severity', 'is_active'],
        ],
        'iot/alerts' => [
            'model' => IotAlert::class, 'uuid' => true, 'deletable' => false,
            'fields' => [
                'equipment_id' => 'required|integer|exists:equipment,id',
                'iot_sensor_id' => 'nullable|integer|exists:iot_sensors,id',
                'iot_event_id' => 'nullable|integer|exists:iot_events,id',
                'iot_alert_rule_id' => 'nullable|integer|exists:iot_alert_rules,id',
                'name' => 'required|string|max:255', 'rule' => 'required|array',
                'severity' => 'sometimes|in:info,warning,error,critical',
                'status' => 'sometimes|in:open,acknowledged,resolved',
                'triggered_at' => 'required|date', 'work_order_id' => 'nullable|integer|exists:work_orders,id',
                'details' => 'nullable|array',
            ],
            'search' => ['name', 'severity'], 'filters' => ['equipment_id', 'status', 'severity'],
        ],
        'ai/documents' => [
            'model' => AiDocument::class, 'uuid' => true, 'deletable' => true,
            'fields' => [
                'client_id' => 'nullable|integer|exists:clients,id', 'equipment_id' => 'nullable|integer|exists:equipment,id',
                'title' => 'required|string|max:255', 'document_type' => 'nullable|string|max:100',
                'disk' => 'sometimes|string|max:100', 'path' => 'required|string|max:1000',
                'mime_type' => 'nullable|string|max:150', 'status' => 'sometimes|in:pending,processing,ready,failed',
                'metadata' => 'nullable|array',
            ],
            'search' => ['title', 'document_type'], 'filters' => ['client_id', 'equipment_id', 'status'],
        ],
        'ai/document-chunks' => [
            'model' => AiDocumentChunk::class, 'uuid' => false, 'deletable' => true,
            'fields' => [
                'ai_document_id' => 'required|integer|exists:ai_documents,id', 'chunk_index' => 'required|integer|min:0',
                'content' => 'required|string', 'token_count' => 'nullable|integer|min:0', 'metadata' => 'nullable|array',
            ],
            'search' => ['content'], 'filters' => ['ai_document_id'],
        ],
        'ai/embeddings' => [
            'model' => AiEmbedding::class, 'uuid' => false, 'deletable' => true,
            'fields' => [
                'ai_document_chunk_id' => 'required|integer|exists:ai_document_chunks,id',
                'model_name' => 'required|string|max:255', 'model_version' => 'nullable|string|max:100',
                'dimensions' => 'nullable|integer|min:1', 'vector' => 'required|array',
            ],
            'search' => ['model_name', 'model_version'], 'filters' => ['ai_document_chunk_id', 'model_name'],
        ],
        'ai/conversations' => [
            'model' => AiConversation::class, 'uuid' => true, 'deletable' => false,
            'fields' => [
                'client_id' => 'nullable|integer|exists:clients,id', 'title' => 'nullable|string|max:255',
                'context' => 'nullable|array',
            ],
            'search' => ['title'], 'filters' => ['client_id'],
        ],
        'ai/messages' => [
            'model' => AiMessage::class, 'uuid' => false, 'deletable' => false, 'readonly' => true,
            'fields' => [
                'ai_conversation_id' => 'required|integer|exists:ai_conversations,id',
                'role' => 'required|in:system,user,assistant,tool', 'content' => 'required|string',
                'sources' => 'nullable|array', 'metadata' => 'nullable|array',
                'prompt_tokens' => 'nullable|integer|min:0', 'completion_tokens' => 'nullable|integer|min:0',
            ],
            'search' => ['content', 'role'], 'filters' => ['ai_conversation_id', 'role'],
        ],
        'ai/feedback' => [
            'model' => AiFeedback::class, 'uuid' => false, 'deletable' => false,
            'fields' => [
                'ai_message_id' => 'required|integer|exists:ai_messages,id',
                'rating' => 'nullable|integer|min:1|max:5', 'comments' => 'nullable|string',
                'metadata' => 'nullable|array',
            ],
            'search' => ['comments'], 'filters' => ['ai_message_id', 'rating'],
        ],
        'ai/predictions' => [
            'model' => AiPrediction::class, 'uuid' => true, 'deletable' => false, 'readonly' => true,
            'fields' => [
                'equipment_id' => 'nullable|integer|exists:equipment,id',
                'work_order_id' => 'nullable|integer|exists:work_orders,id',
                'prediction_type' => 'required|string|max:100', 'model_name' => 'required|string|max:255',
                'model_version' => 'required|string|max:100', 'predicted_at' => 'required|date',
                'horizon_at' => 'nullable|date', 'confidence' => 'nullable|numeric|min:0|max:1',
                'input_data' => 'nullable|array', 'result' => 'required|array',
            ],
            'search' => ['prediction_type', 'model_name'], 'filters' => ['equipment_id', 'prediction_type'],
        ],
    ];

    public function index(Request $request, string $resource): JsonResponse
    {
        $resource = $this->resourceName($request);
        $definition = self::RESOURCES[$resource];
        $query = $this->scopedQuery($request, $resource, $definition['model']);
        $search = $definition['search'];

        $filters = Validator::make($request->query(), [
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
            'search' => 'sometimes|string|max:255',
            ...array_fill_keys($definition['filters'], 'sometimes|string|max:255'),
        ])->validate();

        foreach ($definition['filters'] as $field) {
            if (array_key_exists($field, $filters)) {
                $query->where($field, $filters[$field]);
            }
        }

        if ($resource === 'invoices' && isset($data['status'])) {
            $allowedTransitions = [
                'draft' => ['issued', 'void'],
                'issued' => ['void'],
                'partially_paid' => ['void'],
                'overdue' => ['void'],
                'paid' => [],
                'void' => [],
            ];
            $current = (string) $instance->getAttribute('status');
            abort_unless(in_array($data['status'], $allowedTransitions[$current] ?? [], true) || $data['status'] === $current, 422, 'Transición de factura no permitida.');
            if ($data['status'] === 'void') {
                abort_if($instance->payments()->exists(), 422, 'No se puede anular una factura con pagos registrados.');
            }
        }

        if (isset($filters['search']) && $search !== []) {
            $term = $filters['search'];
            $operator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function (Builder $nested) use ($search, $term, $operator): void {
                foreach ($search as $index => $field) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $nested->{$method}($field, $operator, "%{$term}%");
                }
            });
        }

        $page = $query->latest()->paginate((int) ($filters['per_page'] ?? 15));

        return response()->json([
            'success' => true,
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        $resource = $this->resourceName($request);
        $definition = self::RESOURCES[$resource];
        abort_if(($definition['creatable'] ?? true) === false, 405, 'Este recurso no acepta creación directa.');
        $data = $this->validated($request, $definition['fields']);
        $record = DB::transaction(function () use ($request, $resource, $definition, $data): Model {
            $record = $this->createRecord($request, $resource, $definition, $data);
            AuditService::log('created', $record, $record->toArray());

            return $record;
        });

        return response()->json(['success' => true, 'data' => $record], 201);
    }

    public function show(Request $request, string $record): JsonResponse
    {
        $definition = self::RESOURCES[$this->resourceName($request)];
        $model = $definition['model'];

        return response()->json([
            'success' => true,
            'data' => $this->scopedQuery($request, $this->resourceName($request), $model)->findOrFail($record),
        ]);
    }

    public function update(Request $request, string $record): JsonResponse
    {
        $resource = $this->resourceName($request);
        $definition = self::RESOURCES[$resource];
        abort_if($definition['readonly'] ?? false, 405, 'Este recurso es de solo lectura.');
        $modelClass = $definition['model'];
        $instance = $this->scopedQuery($request, $resource, $modelClass)->findOrFail($record);
        $rules = $this->updateRules($definition['fields'], $instance);

        $data = $this->validated($request, $rules);
        if ($data === []) {
            throw ValidationException::withMessages(['data' => 'Debe enviar al menos un campo válido para actualizar.']);
        }
        if ($resource === 'iot/devices' && isset($data['gateway_id'])) {
            abort_if((int) $data['gateway_id'] === (int) $instance->getKey(), 422, 'Un dispositivo no puede ser su propio gateway.');
        }

        $updated = DB::transaction(function () use ($request, $resource, $definition, $instance, $data): Model {
            $updated = $this->updateRecord($request, $resource, $definition, $instance, $data);
            AuditService::log('updated', $updated, $updated->getChanges());

            return $updated;
        });

        return response()->json(['success' => true, 'data' => $updated->refresh()]);
    }

    public function destroy(Request $request, string $record): JsonResponse
    {
        $resource = $this->resourceName($request);
        $definition = self::RESOURCES[$resource];
        abort_unless($definition['deletable'] ?? false, 405, 'Este recurso no se puede eliminar.');
        $instance = $this->scopedQuery($request, $this->resourceName($request), $definition['model'])->findOrFail($record);
        if ($resource === 'ai/documents') {
            abort_unless((int) $instance->uploaded_by === (int) $request->user()->id, 404);
        } elseif ($resource === 'ai/document-chunks') {
            $this->assertAiDocumentOwnership($request, $instance->ai_document_id);
        } elseif ($resource === 'ai/embeddings') {
            $this->assertAiDocumentOwnership($request, $instance->chunk->ai_document_id);
        } elseif ($resource === 'quotation-items') {
            abort_unless($instance->quotation->status === 'draft', 422, 'Solo se pueden eliminar conceptos de cotizaciones en borrador.');
        }
        DB::transaction(function () use ($instance): void {
            $instance->delete();
            AuditService::log('deleted', $instance);
        });
        if ($resource === 'quotation-items') {
            $this->recalculateQuotation($instance->quotation_id);
        }
        if ($resource === 'quotation-items') {
            $this->recalculateQuotation($instance->quotation_id);
        }

        return response()->json(['success' => true, 'message' => 'Registro eliminado.']);
    }

    private function createRecord(Request $request, string $resource, array $definition, array $data): Model
    {
        $modelClass = $definition['model'];

        if ($definition['uuid'] ?? false) {
            $data['uuid'] = (string) Str::uuid();
        }

        if ($resource === 'work-orders') {
            $this->assertWorkOrderRelations($data);
            $data['created_by'] = $request->user()->id;
        } elseif ($resource === 'technicians') {
            $data['uuid'] = (string) Str::uuid();
        } elseif ($resource === 'stock-movements') {
            $data['user_id'] = $request->user()->id;
            $data['occurred_at'] ??= now();
        } elseif ($resource === 'work-order-parts') {
            $data['used_by'] = $request->user()->id;
        } elseif ($resource === 'payments') {
            $data['received_by'] = $request->user()->id;
        } elseif ($resource === 'account-movements') {
            $data['recorded_by'] = $request->user()->id;
        } elseif (in_array($resource, ['iot/readings', 'iot/events'], true)) {
            $this->assertEquipmentMatchesSensor($resource, $data);
        } elseif ($resource === 'ai/conversations') {
            $data['user_id'] = $request->user()->id;
        } elseif ($resource === 'ai/messages') {
            $this->assertConversationOwnership($request, $data['ai_conversation_id']);
        } elseif ($resource === 'ai/feedback') {
            $message = AiMessage::query()->findOrFail($data['ai_message_id']);
            $this->assertConversationOwnership($request, $message->ai_conversation_id);
        } elseif ($resource === 'ai/document-chunks') {
            $this->assertAiDocumentOwnership($request, $data['ai_document_id']);
        } elseif ($resource === 'ai/embeddings') {
            $chunk = AiDocumentChunk::query()->findOrFail($data['ai_document_chunk_id']);
            $this->assertAiDocumentOwnership($request, $chunk->ai_document_id);
        }

        if (in_array($resource, ['stock-movements', 'work-order-parts', 'payments'], true)) {
            return $this->createTransactionalRecord($resource, $modelClass, $data);
        }

        if ($resource === 'plan-assignments') {
            return DB::transaction(function () use ($request, $data): Model {
                $reason = $data['price_change_reason'] ?? null;
                unset($data['price_change_reason']);
                $assignment = PlanAssignment::query()->create($data);
                $assignment->priceHistory()->create([
                    'previous_price' => null,
                    'new_price' => $assignment->price,
                    'changed_by' => $request->user()->id,
                    'reason' => $reason,
                ]);

                return $assignment;
            });
        }

        unset($data['price_change_reason']);

        if ($resource === 'quotations') {
            $data['status'] = 'draft';
            $data['subtotal'] = 0;
            $data['discount_total'] = 0;
            $data['tax_total'] = 0;
            $data['total'] = 0;
        } elseif ($resource === 'invoices') {
            $data['status'] = 'draft';
            $data['subtotal'] = 0;
            $data['tax_total'] = 0;
            $data['total'] = 0;
        }

        if ($resource === 'invoices') {
            return $this->createInvoice($data);
        }
        if ($resource === 'quotation-items') {
            return $this->createQuotationItem($data);
        }
        if ($resource === 'invoice-items') {
            return $this->createInvoiceItem($data);
        }

        if ($resource === 'work-order-events') {
            return $this->createWorkOrderEvent($request, $data);
        }

        if ($resource === 'iot/alerts') {
            $data['acknowledged_by'] = null;
        }

        if (in_array($resource, ['ai/documents', 'attachments'], true)) {
            $data['uploaded_by'] = $request->user()->id;
        }

        if (in_array($resource, ['signatures', 'notification-preferences', 'ai/feedback'], true)) {
            $data['user_id'] = $request->user()->id;
        }

        $created = $modelClass::query()->create($data);
        if (in_array($resource, ['ai/conversations', 'ai/messages'], true)) {
            return $created;
        }

        return $created;
    }

    private function updateRecord(Request $request, string $resource, array $definition, Model $instance, array $data): Model
    {
        if ($resource === 'payments' || $resource === 'stock-movements' || $resource === 'work-order-parts') {
            abort(405, 'Los registros financieros y de inventario son inmutables; registre una reversión.');
        }

        if ($resource === 'work-orders') {
            $this->assertWorkOrderRelations([
                'client_id' => $data['client_id'] ?? $instance->client_id,
                'building_id' => $data['building_id'] ?? $instance->building_id,
                'equipment_id' => $data['equipment_id'] ?? $instance->equipment_id,
            ]);
        }
        if ($resource === 'ai/messages') {
            $this->assertConversationOwnership($request, $instance->ai_conversation_id);
        } elseif ($resource === 'ai/documents') {
            abort_unless(
                $instance->client_id === null || (int) $instance->uploaded_by === (int) $request->user()->id,
                404
            );
        } elseif ($resource === 'ai/document-chunks') {
            $this->assertAiDocumentOwnership($request, $instance->ai_document_id);
        } elseif ($resource === 'ai/embeddings') {
            $this->assertAiDocumentOwnership($request, $instance->chunk->ai_document_id);
        }

        if ($resource === 'plan-assignments' && array_key_exists('price', $data)) {
            $oldPrice = (float) $instance->getAttribute('price');
            $newPrice = (float) $data['price'];
            if ($oldPrice !== $newPrice) {
                $reason = $data['price_change_reason'] ?? null;
                PlanPriceHistory::query()->create([
                    'plan_assignment_id' => $instance->getKey(),
                    'previous_price' => $oldPrice,
                    'new_price' => $newPrice,
                    'changed_by' => $request->user()->id,
                    'reason' => $reason,
                ]);
            }
            unset($data['price_change_reason']);
        }

        if ($resource === 'work-orders' && isset($data['status']) && $data['status'] !== $instance->getAttribute('status')) {
            $oldStatus = (string) $instance->getAttribute('status');
            WorkOrderEvent::query()->create([
                'work_order_id' => $instance->getKey(),
                'user_id' => $request->user()->id,
                'event' => 'status_changed',
                'from_status' => $oldStatus,
                'to_status' => $data['status'],
                'description' => $data['observations'] ?? null,
            ]);
        }

        if ($resource === 'invoices' && isset($data['status'])) {
            $current = (string) $instance->status;
            $transitions = [
                'draft' => ['issued', 'void'],
                'issued' => ['void'],
                'partially_paid' => ['void'],
                'overdue' => ['void'],
                'paid' => [],
                'void' => [],
            ];
            abort_unless(
                in_array($data['status'], $transitions[$current] ?? [], true) || $data['status'] === $current,
                422,
                'Transición de factura no permitida.'
            );
            if ($data['status'] === 'void') {
                abort_if($instance->payments()->exists(), 422, 'No se puede anular una factura con pagos registrados.');
            }
            if ($data['status'] === 'issued') {
                abort_if((float) $instance->total <= 0, 422, 'La factura necesita conceptos antes de emitirse.');
            }
        }

        if ($resource === 'quotations' && isset($data['status'])) {
            $current = (string) $instance->status;
            $transitions = [
                'draft' => ['sent', 'rejected', 'expired'],
                'sent' => ['approved', 'rejected', 'expired'],
                'approved' => [],
                'rejected' => [],
                'expired' => [],
            ];
            abort_unless(
                in_array($data['status'], $transitions[$current] ?? [], true) || $data['status'] === $current,
                422,
                'Transición de cotización no permitida.'
            );
            if (in_array($data['status'], ['sent', 'approved'], true)) {
                abort_if((float) $instance->total <= 0, 422, 'La cotización necesita conceptos antes de enviarse o aprobarse.');
            }
            if ($data['status'] === 'approved') {
                $instance->approved_at = now();
            }
        }

        if ($resource === 'quotation-items') {
            $quote = $instance->quotation()->lockForUpdate()->firstOrFail();
            abort_unless($quote->status === 'draft', 422, 'Solo se pueden editar conceptos de cotizaciones en borrador.');
            abort_if(
                isset($data['quotation_id']) && (int) $data['quotation_id'] !== (int) $quote->id,
                422,
                'Un concepto no se puede mover a otra cotización.'
            );
            $data['line_total'] = $this->calculateLineTotal($data, $instance);
        } elseif ($resource === 'invoice-items') {
            $invoice = $instance->invoice()->lockForUpdate()->firstOrFail();
            abort_unless($invoice->status === 'draft', 422, 'Solo se pueden editar conceptos de facturas en borrador.');
            abort_if(
                isset($data['invoice_id']) && (int) $data['invoice_id'] !== (int) $invoice->id,
                422,
                'Un concepto no se puede mover a otra factura.'
            );
            $data['line_total'] = $this->calculateLineTotal($data, $instance);
        }

        $instance->fill($data);
        $instance->save();
        if ($resource === 'quotation-items') {
            $this->recalculateQuotation($instance->quotation_id);
        } elseif ($resource === 'invoice-items') {
            $this->recalculateInvoice($instance->invoice_id);
        }

        return $instance;
    }

    private function createInvoice(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            $quotation = null;
            if (isset($data['quotation_id'])) {
                $quotation = Quotation::query()
                    ->with('items')
                    ->lockForUpdate()
                    ->findOrFail($data['quotation_id']);
                abort_unless($quotation->status === 'approved', 422, 'Solo se pueden facturar cotizaciones aprobadas.');
                abort_unless((int) $quotation->client_id === (int) $data['client_id'], 422, 'La cotización pertenece a otro cliente.');
            }

            $invoice = Invoice::query()->create($data + ['uuid' => (string) Str::uuid()]);
            if ($quotation !== null) {
                foreach ($quotation->items as $item) {
                    $invoice->items()->create([
                        'part_id' => $item->part_id,
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'discount_amount' => $item->discount_amount,
                        'tax_rate' => $item->tax_rate,
                        'line_total' => $item->line_total,
                    ]);
                }
                $this->recalculateInvoice($invoice->id);
            }

            return $invoice;
        });
    }

    private function createQuotationItem(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            $quotation = Quotation::query()->lockForUpdate()->findOrFail($data['quotation_id']);
            abort_unless($quotation->status === 'draft', 422, 'Solo se pueden agregar conceptos a cotizaciones en borrador.');
            $data['quantity'] ??= 1;
            $data['discount_amount'] ??= 0;
            $data['tax_rate'] ??= 0;
            $data['line_total'] = $this->calculateLineTotal($data);
            $item = QuotationItem::query()->create($data);
            $this->recalculateQuotation($quotation->id);

            return $item;
        });
    }

    private function createInvoiceItem(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($data['invoice_id']);
            abort_unless($invoice->status === 'draft', 422, 'Solo se pueden agregar conceptos a facturas en borrador.');
            $data['quantity'] ??= 1;
            $data['tax_rate'] ??= 0;
            $data['line_total'] = $this->calculateLineTotal($data);
            $item = InvoiceItem::query()->create($data);
            $this->recalculateInvoice($invoice->id);

            return $item;
        });
    }

    private function calculateLineTotal(array $data, ?Model $existing = null): float
    {
        $quantity = (float) ($data['quantity'] ?? $existing?->quantity ?? 1);
        $unitPrice = (float) ($data['unit_price'] ?? $existing?->unit_price ?? 0);
        $discount = (float) ($data['discount_amount'] ?? $existing?->discount_amount ?? 0);
        $taxRate = (float) ($data['tax_rate'] ?? $existing?->tax_rate ?? 0);
        $subtotal = $quantity * $unitPrice;
        abort_if($discount > $subtotal, 422, 'El descuento no puede superar el valor del concepto.');

        return round(($subtotal - $discount) * (1 + $taxRate / 100), 2);
    }

    private function recalculateQuotation(int|string $quotationId): void
    {
        $quotation = Quotation::query()->findOrFail($quotationId);
        $items = $quotation->items;
        $subtotal = $items->sum(fn (QuotationItem $item): float => (float) $item->quantity * (float) $item->unit_price);
        $discount = $items->sum(fn (QuotationItem $item): float => (float) $item->discount_amount);
        $tax = $items->sum(function (QuotationItem $item): float {
            $base = ((float) $item->quantity * (float) $item->unit_price) - (float) $item->discount_amount;

            return $base * (float) $item->tax_rate / 100;
        });

        $quotation->update([
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discount, 2),
            'tax_total' => round($tax, 2),
            'total' => round($items->sum(fn (QuotationItem $item): float => (float) $item->line_total), 2),
        ]);
    }

    private function recalculateInvoice(int|string $invoiceId): void
    {
        $invoice = Invoice::query()->findOrFail($invoiceId);
        $items = $invoice->items;
        $subtotal = $items->sum(fn (InvoiceItem $item): float => (float) $item->quantity * (float) $item->unit_price);
        $discount = $items->sum(fn (InvoiceItem $item): float => (float) $item->discount_amount);
        $tax = $items->sum(function (InvoiceItem $item): float {
            $base = ((float) $item->quantity * (float) $item->unit_price) - (float) $item->discount_amount;

            return $base * (float) $item->tax_rate / 100;
        });

        $invoice->update([
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discount, 2),
            'tax_total' => round($tax, 2),
            'total' => round($items->sum(fn (InvoiceItem $item): float => (float) $item->line_total), 2),
        ]);
    }

    private function createTransactionalRecord(string $resource, string $modelClass, array $data): Model
    {
        return DB::transaction(function () use ($resource, $modelClass, $data): Model {
            if ($resource === 'payments') {
                $invoice = Invoice::query()->lockForUpdate()->findOrFail($data['invoice_id']);
                abort_unless(in_array($invoice->status, ['issued', 'partially_paid', 'overdue'], true), 422, 'La factura no acepta pagos en su estado actual.');
                $paid = (float) $invoice->payments()->sum('amount');
                $remaining = (float) $invoice->total - $paid;
                abort_if((float) $data['amount'] > $remaining, 422, 'El pago supera el saldo pendiente de la factura.');
                $payment = Payment::query()->create($data + ['uuid' => (string) Str::uuid()]);
                AccountMovement::query()->create([
                    'client_id' => $invoice->client_id,
                    'invoice_id' => $invoice->id,
                    'payment_id' => $payment->id,
                    'recorded_by' => $data['received_by'],
                    'movement_type' => 'payment',
                    'description' => 'Pago de factura '.$invoice->number,
                    'credit' => $payment->amount,
                    'occurred_at' => $payment->paid_at,
                ]);
                $newPaid = $paid + (float) $payment->amount;
                $invoice->update(['status' => $newPaid >= (float) $invoice->total ? 'paid' : 'partially_paid']);

                return $payment;
            }

            if ($resource === 'work-order-parts') {
                $part = Part::query()->lockForUpdate()->findOrFail($data['part_id']);
                abort_if((float) $part->stock_quantity < (float) $data['quantity'], 422, 'No hay existencias suficientes para este consumo.');
                $line = WorkOrderPart::query()->create($data);
                $part->decrement('stock_quantity', $line->quantity);
                StockMovement::query()->create([
                    'part_id' => $part->id,
                    'work_order_id' => $line->work_order_id,
                    'user_id' => $line->used_by,
                    'movement_type' => 'out',
                    'quantity' => $line->quantity,
                    'unit_cost' => $line->unit_price,
                    'reference' => 'work_order_part:'.$line->id,
                    'observations' => $line->observations,
                ]);

                return $line;
            }

            $part = Part::query()->lockForUpdate()->findOrFail($data['part_id']);
            $quantity = (float) $data['quantity'];
            $delta = match ($data['movement_type']) {
                'in', 'return' => $quantity,
                'out' => -$quantity,
                'adjustment' => $quantity - (float) $part->stock_quantity,
            };
            abort_if((float) $part->stock_quantity + $delta < 0, 422, 'El movimiento dejaría el inventario en negativo.');
            $movement = $modelClass::query()->create($data);
            $part->increment('stock_quantity', $delta);

            return $movement;
        });
    }

    private function createWorkOrderEvent(Request $request, array $data): Model
    {
        return DB::transaction(function () use ($request, $data): Model {
            $order = WorkOrder::query()->lockForUpdate()->findOrFail($data['work_order_id']);
            $data['user_id'] = $request->user()->id;
            $data['from_status'] = $order->status;
            if (isset($data['to_status'])) {
                $order->status = $data['to_status'];
                $order->save();
            }

            return WorkOrderEvent::query()->create($data);
        });
    }

    private function assertEquipmentMatchesSensor(string $resource, array $data): void
    {
        if ($resource === 'iot/readings') {
            $sensor = IotSensor::query()->findOrFail($data['iot_sensor_id']);
            abort_unless(
                (int) $sensor->device->equipment_id === (int) $data['equipment_id'],
                422,
                'El sensor no pertenece al equipo indicado.'
            );
        } elseif (isset($data['iot_device_id'])) {
            $device = IotDevice::query()->findOrFail($data['iot_device_id']);
            abort_unless(
                (int) $device->equipment_id === (int) $data['equipment_id'],
                422,
                'El dispositivo no pertenece al equipo indicado.'
            );
        }
    }

    private function assertWorkOrderRelations(array $data): void
    {
        $building = Building::query()->findOrFail($data['building_id']);
        abort_unless(
            (int) $building->client_id === (int) $data['client_id'],
            422,
            'El edificio no pertenece al cliente indicado.'
        );

        $equipment = Equipment::query()->findOrFail($data['equipment_id']);
        abort_unless(
            (int) $equipment->building_id === (int) $building->id,
            422,
            'El equipo no pertenece al edificio indicado.'
        );
    }

    private function assertConversationOwnership(Request $request, int|string $conversationId): void
    {
        AiConversation::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($conversationId);
    }

    private function assertAiDocumentOwnership(Request $request, int|string $documentId): void
    {
        AiDocument::query()
            ->where('uploaded_by', $request->user()->id)
            ->findOrFail($documentId);
    }

    private function scopedQuery(Request $request, string $resource, string $modelClass): Builder
    {
        $query = $modelClass::query();

        if ($resource === 'ai/conversations') {
            $query->where('user_id', $request->user()->id);
        } elseif ($resource === 'ai/messages') {
            $query->whereHas('conversation', fn (Builder $conversation) => $conversation->where('user_id', $request->user()->id));
        } elseif ($resource === 'ai/feedback') {
            $query->whereHas('message.conversation', fn (Builder $conversation) => $conversation->where('user_id', $request->user()->id));
        } elseif ($resource === 'ai/documents') {
            $query->where(function (Builder $documents) use ($request): void {
                $documents->whereNull('client_id')->orWhere('uploaded_by', $request->user()->id);
            });
        } elseif ($resource === 'ai/document-chunks') {
            $query->whereHas('document', function (Builder $documents) use ($request): void {
                $documents->whereNull('client_id')->orWhere('uploaded_by', $request->user()->id);
            });
        } elseif ($resource === 'ai/embeddings') {
            $query->whereHas('chunk.document', function (Builder $documents) use ($request): void {
                $documents->whereNull('client_id')->orWhere('uploaded_by', $request->user()->id);
            });
        } elseif ($resource === 'attachments' && ! $this->isStaff($request)) {
            $query->where('uploaded_by', $request->user()->id);
        }

        return $query;
    }

    private function isStaff(Request $request): bool
    {
        return $request->user()->role()
            ->whereIn('name', ['admin', 'coordinador', 'tecnico', 'contador', 'supervisor'])
            ->exists();
    }

    private function validated(Request $request, array $rules): array
    {
        return Validator::make($request->all(), $rules)->validate();
    }

    private function updateRules(array $rules, Model $instance): array
    {
        foreach ($rules as $field => $rule) {
            $parts = array_values(array_filter(
                explode('|', $rule),
                static fn (string $part): bool => $part !== 'required'
            ));

            foreach ($parts as $index => $part) {
                if (! str_starts_with($part, 'unique:')) {
                    continue;
                }

                $uniqueParts = explode(',', substr($part, 7));
                $parts[$index] = Rule::unique($uniqueParts[0], $uniqueParts[1] ?? $field)
                    ->ignore($instance->getKey());
            }

            $rules[$field] = ['sometimes', ...$parts];
        }

        return $rules;
    }

    private function resourceName(Request $request): string
    {
        $resource = (string) $request->route('resource');
        abort_unless(isset(self::RESOURCES[$resource]), 404);

        return $resource;
    }
}
