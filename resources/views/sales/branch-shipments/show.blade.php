@extends('layouts.app')
@section('title', 'توصيل الطلبية — ' . $sourceNumber)
@section('page-title', 'التوصيل والاستلام')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h1 class="page-heading">توصيل {{ $sourceLabel }}</h1>
        <p class="page-subheading">
            {{ $sourceNumber }} · {{ $shipment->fromLocation?->name }} ← {{ $shipment->toLocation?->name }}
        </p>
    </div>
    <div class="page-header-actions">
        @if($invoice?->isConfirmed())
            <a href="{{ route('branch-receiving-invoices.show', $invoice) }}" class="btn btn-outline btn-sm">
                فاتورة الاستلام
            </a>
        @endif
        <a href="{{ $sourceUrl }}" class="btn btn-ghost btn-sm">العودة للطلب</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:1rem">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:1rem">
        <ul style="margin:0;padding-inline-start:1.2rem">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="card" style="margin-bottom:1rem">
    <div class="card-header">
        <span class="card-title">مراحل الطلبية</span>
        <span class="badge badge-blue">{{ \App\Support\BranchOrderMilestones::label($shipment->source) }}</span>
    </div>
    <div class="card-body">
        <div class="delivery-track">
            @php
                $steps = [
                    ['key'=>'executed','label'=>'تم التنفيذ','user'=>null,
                     'at'=>$shipment->created_at,'done'=>true],
                    ['key'=>'loaded','label'=>'تم التحميل','user'=>$shipment->loadedBy,
                     'at'=>$shipment->loaded_at,
                     'done'=>in_array($shipment->status, ['loaded','out_for_delivery','arrived','received','closed_short'], true)],
                    ['key'=>'received','label'=>'تم الاستلام','user'=>$shipment->receivedBy,
                     'at'=>$shipment->received_at,'done'=>(bool) $invoice?->isConfirmed()],
                ];
            @endphp
            @foreach($steps as $step)
                @php $done = $step['done']; @endphp
                <div class="delivery-step {{ $done ? 'is-done' : '' }}">
                    <div class="delivery-node">{{ $done ? '✓' : $loop->iteration }}</div>
                    <strong>{{ $step['label'] }}</strong>
                    <small>{{ $step['at']?->format('Y-m-d H:i') ?? 'بانتظار التنفيذ' }}</small>
                    @if($step['user'])
                        <small>{{ $step['user']->display_name }}</small>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>

@if($invoice?->isConfirmed())
    @if($shipment->status === \App\Models\BranchShipment::CLOSED_SHORT)
        <div class="alert alert-warning" style="margin-bottom:1rem"><strong>أُغلقت بنقص:</strong> {{ $shipment->closure_reason }} — بواسطة {{ $shipment->closedBy?->display_name ?? '—' }} · {{ $shipment->closed_at?->format('Y-m-d H:i') }}</div>
    @endif
    <div class="card" style="margin-bottom:1rem">
        <div class="card-header">
            <span class="card-title">{{ $shipment->status === \App\Models\BranchShipment::CLOSED_SHORT ? 'فاتورة الاستلام الناقص المعتمدة' : 'فاتورة الاستلام المعتمدة' }}</span>
            <a href="{{ route('branch-receiving-invoices.show', $invoice) }}" class="btn btn-outline btn-sm">
                عرض فاتورة {{ $invoice->invoice_number }}
            </a>
        </div>
        <div class="card-body" style="display:flex;gap:1.5rem;flex-wrap:wrap">
            <div>{{ $shipment->status === \App\Models\BranchShipment::CLOSED_SHORT ? 'المطلوب' : 'المرسل' }}: <strong>{{ number_format((float) $invoice->total_items_sent, 3) }}</strong></div>
            <div>المستلم: <strong>{{ number_format((float) $invoice->total_items_received, 3) }}</strong></div>
            <div>الفرق: <strong>{{ number_format((float) $invoice->total_difference, 3) }}</strong></div>
            @if($invoice->notes)
                <div>ملاحظات: {{ $invoice->notes }}</div>
            @endif
        </div>
    </div>
@endif


<div class="card" style="margin-bottom:1rem">
    <div class="card-header">
        <div>
            <span class="card-title">جدول التحميل والاستلام للفرع</span>
            <p class="form-help">المواعيد المتوقعة يحددها المصنع قبل أول تحميل. الأوقات الفعلية يسجلها النظام عند تأكيد التنفيذ ولا يمكن تعديلها من هنا.</p>
        </div>
    </div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(185px,1fr));gap:.85rem;margin-bottom:1rem">
            <div><small class="text-muted">موعد التحميل المخطط</small><div><strong>{{ $shipment->planned_loading_at?->format('Y-m-d H:i') ?? 'لم يحدد' }}</strong></div></div>
            <div><small class="text-muted">موعد الاستلام المخطط</small><div><strong>{{ $shipment->planned_receiving_at?->format('Y-m-d H:i') ?? 'لم يحدد' }}</strong></div></div>
            <div><small class="text-muted">{{ $shipment->multi_trip_enabled ? 'آخر تحميل فعلي (راجع النقلات)' : 'التحميل الفعلي' }}</small><div><strong>{{ $shipment->loaded_at?->format('Y-m-d H:i') ?? 'لم ينفذ' }}</strong></div></div>
            <div><small class="text-muted">الاستلام النهائي الفعلي</small><div><strong>{{ $shipment->received_at?->format('Y-m-d H:i') ?? 'بانتظار الاستلام' }}</strong></div></div>
        </div>
        @if($canFactoryOperate && $shipment->status === \App\Models\BranchShipment::PENDING_LOADING && !$shipment->trips->count())
            <form method="POST" action="{{ route('branch-shipments.schedule', $shipment) }}">
                @csrf
                @method('PATCH')
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.75rem">
                    <label class="form-group">
                        <span class="form-label">التحميل المتوقع</span>
                        <input class="form-input" type="datetime-local" name="planned_loading_at"
                            value="{{ old('planned_loading_at', $shipment->planned_loading_at?->format('Y-m-d\\TH:i')) }}">
                    </label>
                    <label class="form-group">
                        <span class="form-label">استلام الفرع المتوقع</span>
                        <input class="form-input" type="datetime-local" name="planned_receiving_at"
                            value="{{ old('planned_receiving_at', $shipment->planned_receiving_at?->format('Y-m-d\\TH:i')) }}">
                    </label>
                </div>
                <p class="form-help">يمكن ترك الموعدين فارغين. يجب ألا يكون موعد الاستلام قبل التحميل.</p>
                <button class="btn btn-gold" type="submit">حفظ مواعيد الفرع</button>
            </form>
        @endif
    </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin-bottom:1rem">
    <div class="stat-card"><div class="stat-info"><div class="stat-label">من</div><div class="stat-value" style="font-size:.95rem">{{ $shipment->fromLocation?->name }}</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-label">إلى</div><div class="stat-value" style="font-size:.95rem">{{ $shipment->toLocation?->name }}</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-label">الموصّل</div><div class="stat-value" style="font-size:.95rem">{{ $shipment->driver?->display_name ?? 'لم يحدد بعد' }}</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-label">فاتورة الاستلام</div><div class="stat-value" style="font-size:.95rem">{{ $invoice?->invoice_number ?? '—' }}</div></div></div>
</div>

@if($shipment->multi_trip_enabled)
    @include('sales.branch-shipments.partials.multi-trip')
@else
@if($shipment->status === \App\Models\BranchShipment::PENDING_LOADING && $canFactoryOperate)
<div class="card" style="margin-bottom:1rem">
    <div class="card-header"><span class="card-title">تأكيد التحميل وتحديد الموصّل</span></div>
    <form method="POST" action="{{ route('branch-shipments.loaded', $shipment) }}">
        @csrf
        <div class="card-body" style="display:grid;grid-template-columns:minmax(220px,1fr) minmax(260px,2fr);gap:1rem">
            <div class="form-group">
                <label class="form-label">الموصّل</label>
                <select name="driver_id" class="form-select" required>
                    <option value="">اختر الموصّل</option>
                    @foreach($drivers as $driver)
                        <option value="{{ $driver->id }}">
                            {{ $driver->display_name }}
                            @if($driver->employee?->job_title) — {{ $driver->employee->job_title }} @endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">ملاحظات التحميل</label>
                <input type="text" name="notes" class="form-input" maxlength="2000" placeholder="اختياري">
            </div>
        </div>
        <div class="card-footer" style="display:flex;justify-content:flex-end">
            <button type="submit" class="btn btn-gold">تم التحميل</button>
        </div>
    </form>
</div>
@endif

{{-- The previous "dispatched" and "arrived" buttons remain in the
     backend for historic shipments; day-to-day receiving starts after loading. --}}
<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">محتويات الطلبية</div>
            <small class="text-muted">سجّل المستلم فعليًا لكل صنف، حتى لو كان أقل من المرسل أو صفرًا. يظهر الفرق والملاحظات في فاتورة الاستلام.</small>
        </div>
    </div>

    @if(in_array($shipment->status, [
        \App\Models\BranchShipment::LOADED,
        \App\Models\BranchShipment::OUT_FOR_DELIVERY,
        \App\Models\BranchShipment::ARRIVED,
    ], true) && $canReceive && ! $invoice?->isConfirmed())
        <form
            method="POST"
            action="{{ route('branch-shipments.receive', $shipment) }}"
            data-confirm-type="success"
            data-confirm-receipt="1"
            data-confirm-invoice-number="{{ $invoice->invoice_number }}"
            data-confirm-title="اعتماد استلام الطلبية"
            data-confirm-text="اعتماد الاستلام وإصدار الفاتورة"
            data-confirm="سيتم اعتماد الكميات التي سجّلتها لكل صنف، وتوثيق أي نقص أو فروقات وملاحظات في فاتورة الاستلام الرسمية. يُرجى مراجعة التفاصيل قبل الاعتماد."
        >
            @csrf
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الصنف</th>
                            <th>الوحدة</th>
                            <th>المرسل</th>
                            <th>المستلم</th>
                            <th>ملاحظة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->items as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $item->displayDescription() }}</td>
                                <td>{{ $item->displayUnit() ?? '—' }}</td>
                                <td>{{ number_format((float)$item->sent_quantity, 3) }}</td>
                                <td>
                                    <input
                                        type="number"
                                        name="items[{{ $item->id }}][received_quantity]"
                                        min="0"
                                        max="{{ $item->sent_quantity }}"
                                        step="0.001"
                                        value="{{ old("items.{$item->id}.received_quantity", $item->sent_quantity) }}"
                                        class="form-input"
                                        required
                                    >
                                </td>
                                <td>
                                    <input type="text" name="items[{{ $item->id }}][note]" class="form-input" maxlength="1000">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">ملاحظات الاستلام</label>
                    <textarea name="notes" class="form-textarea" rows="3" maxlength="2000"></textarea>
                </div>
            </div>

            <div class="card-footer" style="display:flex;justify-content:flex-end">
                <button class="btn btn-success" type="submit">
                    تأكيد الاستلام وإصدار الفاتورة
                </button>
            </div>
        </form>
    @else
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>#</th><th>الصنف</th><th>الوحدة</th><th>المرسل</th><th>المستلم</th><th>الفرق</th></tr>
                </thead>
                <tbody>
                    @foreach($invoice->items as $item)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $item->displayDescription() }}</td>
                            <td>{{ $item->displayUnit() ?? '—' }}</td>
                            <td>{{ number_format((float)$item->sent_quantity, 3) }}</td>
                            <td>{{ $item->received_quantity === null ? '—' : number_format((float)$item->received_quantity, 3) }}</td>
                            <td>{{ $item->difference_quantity === null ? '—' : number_format((float)$item->difference_quantity, 3) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endif {{-- legacy single-transfer form --}}

<style>
.delivery-track{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem}
.delivery-step{display:grid;justify-items:center;gap:.25rem;padding:.75rem;border:1px solid var(--border);border-radius:12px;background:var(--off-white);text-align:center}
.delivery-node{width:34px;height:34px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:#e5e7eb;color:#64748b;font-weight:900}
.delivery-step.is-done .delivery-node{background:#16a34a;color:#fff}
.delivery-step small{color:var(--text-muted);font-size:.68rem}
@media(max-width:800px){.delivery-track{grid-template-columns:1fr 1fr}}
@media(max-width:520px){.delivery-track{grid-template-columns:1fr}}
</style>
@endsection
