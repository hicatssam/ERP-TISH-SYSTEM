@props([
    'source',
    'shipment' => null,
    'sourceType',
])

@php
    $statusValue = $source->status instanceof \BackedEnum
        ? $source->status->value
        : (string) $source->status;

    $workflowStatus = match ($sourceType) {
        'special-cake' => \App\Models\SpecialCakeOrder::normalizeLegacyWorkflowStatus($statusValue),
        'showroom-cake' => $source->status->workflowValue(),
        'showroom-sweets' => $source->status->workflowValue(),
        default => $statusValue,
    };

    $canStart = match ($sourceType) {
        'special-cake' => auth()->user()->isAdmin() || auth()->user()->can('cake_orders.dispatch'),
        'showroom-cake' => auth()->user()->isAdmin() || auth()->user()->can('showroom_cake_requests.update_status'),
        'showroom-sweets' => auth()->user()->isAdmin()
            || auth()->user()->can('showroom_sweets_requests.dispatch')
            || auth()->user()->can('showroom_sweets_requests.update_status'),
        default => false,
    };
@endphp

@if($shipment || $workflowStatus === 'ready')
    <div class="card" style="margin-top:1.25rem">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;gap:1rem">
            <div>
                <div class="card-title">التوصيل والاستلام</div>
                <small class="text-muted">تم التنفيذ ثم التحميل ثم الاستلام، مع حفظ فاتورة الاستلام والكميات الفعلية.</small>
            </div>

            @if($shipment)
                <span class="badge badge-blue">{{ \App\Support\BranchOrderMilestones::label($source) }}</span>
            @endif
        </div>

        <div class="card-body">
            @if($shipment)
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:.75rem;margin-bottom:1rem">
                    <div>
                        <small class="text-muted">وقت التحميل المخطط</small>
                        <div style="font-weight:800">{{ $shipment->planned_loading_at?->format('Y-m-d H:i') ?? 'لم يحدد' }}</div>
                    </div>
                    <div>
                        <small class="text-muted">وقت الاستلام المخطط</small>
                        <div style="font-weight:800">{{ $shipment->planned_receiving_at?->format('Y-m-d H:i') ?? 'لم يحدد' }}</div>
                    </div>
                    <div>
                        <small class="text-muted">الموصّل</small>
                        <div style="font-weight:800">{{ $shipment->driver?->display_name ?? 'لم يحدد بعد' }}</div>
                    </div>
                    <div>
                        <small class="text-muted">وقت التحميل</small>
                        <div style="font-weight:800">{{ $shipment->loaded_at?->format('Y-m-d H:i') ?? '—' }}</div>
                    </div>

                    <div>
                        <small class="text-muted">وقت الاستلام</small>
                        <div style="font-weight:800">{{ $shipment->received_at?->format('Y-m-d H:i') ?? '—' }}</div>
                    </div>
                </div>

                <a href="{{ route('branch-shipments.show', $shipment) }}" class="btn btn-gold btn-sm">
                    فتح التوصيل والاستلام
                </a>

                @if($shipment->receivingInvoice?->isConfirmed())
                    <a href="{{ route('branch-receiving-invoices.show', $shipment->receivingInvoice) }}" class="btn btn-outline btn-sm">
                        فاتورة الاستلام {{ $shipment->receivingInvoice->invoice_number }}
                    </a>
                @endif
            @elseif($canStart)
                <form
                    method="POST"
                    action="{{ route('branch-shipments.create', ['sourceType' => $sourceType, 'sourceId' => $source->id]) }}"
                >
                    @csrf
                    <button type="submit" class="btn btn-gold btn-sm">
                        تجهيز الطلبية للتحميل
                    </button>
                </form>
            @else
                <div class="text-muted">الطلب جاهز، وبانتظار المصنع لفتح شحنة التوصيل.</div>
            @endif
        </div>
    </div>
@endif
