@extends('layouts.app')
@section('title', 'طلبات المعرض')

@section('content')
<div class="page-actions">
    <div class="page-actions-title">طلبات كيك المعرض</div>
    <div class="action-btns">
        @can('showroom_cake_daily_reports.view')
            <a
                href="{{ route('showroom-cake-daily-reports.index') }}"
                class="btn btn-outline"
            >
                تقرير قسم الكيك
            </a>
        @endcan

        @can('reports.view')
            <a
                href="{{ route('reports.show', 'cake-production') }}"
                class="btn btn-outline"
            >
                ▤ تقرير إنتاج الكيك الموحد
            </a>
        @endcan

        <a href="{{ route('showroom-cake-requests.create') }}" class="btn btn-gold">+ طلب جديد</a>
    </div>
</div>

{{-- Filters --}}
<div class="filter-row">
    <form method="GET" class="filter-grid">
        <div class="filter-group">
            <label class="filter-label">الحالة</label>
            <select name="status" class="form-select">
                <option value="">الكل</option>
                @foreach($statusEnum as $s)
                    <option value="{{ $s->value }}" {{ request('status') === $s->value ? 'selected' : '' }}>
                        {{ $s->label() }}
                    </option>
                @endforeach
            </select>
        </div>
        @if($canViewAll)
        <div class="filter-group">
            <label class="filter-label">المعرض / الفرع</label>
            <select name="location_id" class="form-select">
                <option value="">الكل</option>
                @foreach($locations as $loc)
                    <option value="{{ $loc->id }}" {{ request('location_id') == $loc->id ? 'selected' : '' }}>
                        {{ $loc->name }}
                    </option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="filter-group" style="justify-content:flex-end">
            <button class="btn btn-outline btn-sm" type="submit">تصفية</button>
            <a href="{{ route('showroom-cake-requests.index') }}" class="btn btn-ghost btn-sm">مسح</a>
        </div>
    </form>
</div>


@if(auth()->user()->isAdmin())
<div class="card" style="margin-bottom:1rem">
    <div class="card-body" style="display:flex;gap:1rem;align-items:end;flex-wrap:wrap">
        <form id="cakeBulkArchive" method="POST" action="{{ route('showroom-branch-requests.bulk-delete', ['type' => 'cake']) }}"
              onsubmit="return confirm('أرشفة طلبات الكيك الملغاة التي اخترتها؟ لا يمكن عكس العملية من هذه الشاشة.')">
            @csrf
            <input type="hidden" name="mode" value="selected">
            <button type="submit" class="btn btn-outline btn-sm">حذف الطلبات المحددة</button>
        </form>
        <form method="POST" action="{{ route('showroom-branch-requests.bulk-delete', ['type' => 'cake']) }}"
              onsubmit="return confirm('تحذير: سيتم أرشفة جميع طلبات كيك الفروع الملغاة وغير المرتبطة بحركات مالية أو مخزنية. هل تؤكد؟')"
              style="display:flex;gap:.5rem;align-items:end;flex-wrap:wrap">
            @csrf
            <input type="hidden" name="mode" value="all_eligible">
            <label style="font-size:.85rem">لتأكيد الحذف الجماعي اكتب: حذف الطلبات الملغاة
                <input name="confirmation" class="form-input" required placeholder="حذف الطلبات الملغاة" autocomplete="off">
            </label>
            <button type="submit" class="btn btn-danger btn-sm">حذف جميع الطلبات الملغاة المؤهلة</button>
        </form>
        <small class="form-help" style="width:100%">لا يمكن حذف طلب قيد التنفيذ أو مكتمل أو مرتبط بشحنة أو بطلب مواد خام. تبقى السجلات محفوظة للتدقيق.</small>
    </div>
</div>
@endif
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                @if(auth()->user()->isAdmin())
                    <th><input type="checkbox" aria-label="تحديد جميع الطلبات المؤهلة في الصفحة" onclick="document.querySelectorAll('.cake-bulk-checkbox').forEach(el => el.checked = this.checked)"></th>
                @endif
                <th>رقم الطلب</th>
                <th>المعرض / الفرع</th>
                <th>عدد الأصناف</th>
                <th>تاريخ الحاجة</th>
                <th>الحالة</th>
                <th>تاريخ الإنشاء</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
        @forelse($requests as $req)
            <tr>
                @if(auth()->user()->isAdmin())
                    <td>
                        @if($req->status->workflowValue() === 'cancelled' && ! $req->branchShipment && ! $req->rawMaterialStockRequest)
                            <input class="cake-bulk-checkbox" type="checkbox" form="cakeBulkArchive" name="ids[]" value="{{ $req->id }}" aria-label="تحديد {{ $req->request_number }}">
                        @endif
                    </td>
                @endif
                <td><strong>{{ $req->request_number }}</strong></td>
                <td>{{ $req->requestingLocation?->name ?? '—' }}</td>
                <td>{{ $req->items->count() }} صنف</td>
                <td>{{ $req->needed_by?->format('Y-m-d') ?? '—' }}</td>
                <td>
                    <span class="badge {{ $req->status->badgeClass() }}" style="font-size:.65rem">
                        {{ \App\Support\BranchOrderMilestones::label($req) }}
                    </span>
                </td>
                <td>{{ $req->created_at->format('Y-m-d') }}</td>
                <td>
                    <div class="actions">
                        <a href="{{ route('showroom-cake-requests.show', $req) }}" class="btn btn-ghost btn-sm">عرض</a>

                        @if(in_array($req->id, $editableIds, true))
                            <a href="{{ route('showroom-cake-requests.edit', $req) }}" class="btn btn-outline btn-sm">تعديل</a>
                        @endif
                        @if(in_array($req->id, $cancelableIds, true))
                            <a href="{{ route('showroom-cake-requests.show', $req) }}#cancel-branch-request" class="btn btn-danger btn-sm">إلغاء مع السبب</a>
                        @endif
                        @if(auth()->user()->isAdmin() && $req->status->workflowValue() === 'cancelled' && ! $req->branchShipment && ! $req->rawMaterialStockRequest)
                            <form action="{{ route('showroom-cake-requests.destroy', $req) }}" method="POST" onsubmit="return confirm('أرشفة هذا الطلب الملغى؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">حذف</button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ auth()->user()->isAdmin() ? 8 : 7 }}">
                    <div class="empty-state-sm">لا توجد طلبات معرض بعد.</div>
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>

<div>
    {{ $requests->withQueryString()->links() }}
</div>
@endsection
