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
        <span class="card-title">مسار التوصيل</span>
        <span class="badge badge-blue">{{ $shipment->statusLabel() }}</span>
    </div>
    <div class="card-body">
        <div class="delivery-track">
            @php
                $steps = [
                    ['key'=>'loaded_at','label'=>'تم التحميل','user'=>$shipment->loadedBy],
                    ['key'=>'dispatched_at','label'=>'خرج للتوصيل','user'=>$shipment->dispatchedBy],
                    ['key'=>'arrived_at','label'=>'وصل الفرع','user'=>$shipment->arrivedBy],
                    ['key'=>'received_at','label'=>'استلم الفرع','user'=>$shipment->receivedBy],
                ];
            @endphp
            @foreach($steps as $step)
                @php $done = (bool) $shipment->{$step['key']}; @endphp
                <div class="delivery-step {{ $done ? 'is-done' : '' }}">
                    <div class="delivery-node">{{ $done ? '✓' : $loop->iteration }}</div>
                    <strong>{{ $step['label'] }}</strong>
                    <small>{{ $shipment->{$step['key']}?->format('Y-m-d H:i') ?? 'بانتظار التنفيذ' }}</small>
                    @if($step['user'])
                        <small>{{ $step['user']->display_name }}</small>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin-bottom:1rem">
    <div class="stat-card"><div class="stat-info"><div class="stat-label">من</div><div class="stat-value" style="font-size:.95rem">{{ $shipment->fromLocation?->name }}</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-label">إلى</div><div class="stat-value" style="font-size:.95rem">{{ $shipment->toLocation?->name }}</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-label">الموصّل</div><div class="stat-value" style="font-size:.95rem">{{ $shipment->driver?->display_name ?? 'لم يحدد بعد' }}</div></div></div>
    <div class="stat-card"><div class="stat-info"><div class="stat-label">فاتورة الاستلام</div><div class="stat-value" style="font-size:.95rem">{{ $invoice?->invoice_number ?? '—' }}</div></div></div>
</div>

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

@if($shipment->status === \App\Models\BranchShipment::LOADED && ($canFactoryOperate || $canDriverOperate))
<div class="card" style="margin-bottom:1rem">
    <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;gap:1rem">
        <div>
            <strong>الطلبية محملة وجاهزة للخروج</strong>
            <div class="text-muted">الموصّل: {{ $shipment->driver?->display_name ?? '—' }}</div>
        </div>
        <form method="POST" action="{{ route('branch-shipments.dispatch', $shipment) }}">
            @csrf
            <button class="btn btn-gold" type="submit">خرجت للتوصيل</button>
        </form>
    </div>
</div>
@endif

@if($shipment->status === \App\Models\BranchShipment::OUT_FOR_DELIVERY && $canDriverOperate)
<div class="card" style="margin-bottom:1rem">
    <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;gap:1rem">
        <div>
            <strong>الطلبية في الطريق إلى الفرع</strong>
            <div class="text-muted">عند الوصول أكد وصولها للفرع.</div>
        </div>
        <form method="POST" action="{{ route('branch-shipments.arrive', $shipment) }}">
            @csrf
            <button class="btn btn-gold" type="submit">وصلت إلى الفرع</button>
        </form>
    </div>
</div>
@endif

<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">محتويات الطلبية</div>
            <small class="text-muted">هذه نسخة ثابتة من محتويات الطلب وقت فتح الشحنة.</small>
        </div>
    </div>

    @if($shipment->status === \App\Models\BranchShipment::ARRIVED && $canReceive)
        <form method="POST" action="{{ route('branch-shipments.receive', $shipment) }}">
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
                                <td>{{ $item->description }}</td>
                                <td>{{ $item->unit ?? '—' }}</td>
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
                <button class="btn btn-success" type="submit" onclick="return confirm('تأكيد استلام هذه الطلبية وإصدار فاتورة الاستلام؟')">
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
                            <td>{{ $item->description }}</td>
                            <td>{{ $item->unit ?? '—' }}</td>
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

<style>
.delivery-track{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}
.delivery-step{display:grid;justify-items:center;gap:.25rem;padding:.75rem;border:1px solid var(--border);border-radius:12px;background:var(--off-white);text-align:center}
.delivery-node{width:34px;height:34px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:#e5e7eb;color:#64748b;font-weight:900}
.delivery-step.is-done .delivery-node{background:#16a34a;color:#fff}
.delivery-step small{color:var(--text-muted);font-size:.68rem}
@media(max-width:800px){.delivery-track{grid-template-columns:1fr 1fr}}
@media(max-width:520px){.delivery-track{grid-template-columns:1fr}}
</style>
@endsection
