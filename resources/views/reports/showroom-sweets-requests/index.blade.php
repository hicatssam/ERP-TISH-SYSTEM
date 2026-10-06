@extends('layouts.app')

@section('title', 'تقرير طلبات حلويات الفروع')
@section('page-title', 'تقرير طلبات حلويات الفروع')

@section('content')
<div class="ssr-report-page">
    <div class="page-header">
        <div>
            <h1 class="page-heading">تقرير طلبات حلويات الفروع</h1>
            <p class="page-subheading">
                تقرير شامل لكل طلبات حلويات الفروع مع تفاصيل الأصناف والكميات وحالة كل طلب.
            </p>
        </div>

        <div class="ssr-report-actions">
            <a
                href="{{ route('reports.showroom-sweets-requests.print', request()->query()) }}"
                target="_blank"
                class="btn btn-outline"
            >
                طباعة
            </a>

            <a
                href="{{ route('reports.showroom-sweets-requests.pdf', request()->query()) }}"
                class="btn btn-gold"
            >
                تحميل PDF
            </a>
        </div>
    </div>

    <form
        method="GET"
        action="{{ route('reports.showroom-sweets-requests.index') }}"
        class="card ssr-filter-card"
    >
        <div class="card-body ssr-filter-grid">
            <div class="form-group">
                <label class="form-label">الفرع</label>
                <select name="location_id" class="form-select">
                    <option value="">كل الفروع</option>
                    @foreach($locations as $location)
                        <option
                            value="{{ $location->id }}"
                            @selected(
                                (string) ($filters['location_id'] ?? '')
                                === (string) $location->id
                            )
                        >
                            {{ $location->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">المنتج</label>
                <select name="product_id" class="form-select">
                    <option value="">كل المنتجات</option>
                    @foreach($products as $product)
                        <option
                            value="{{ $product->id }}"
                            @selected(
                                (string) ($filters['product_id'] ?? '')
                                === (string) $product->id
                            )
                        >
                            {{ $product->name_ar ?: $product->name }}
                            @if($product->sku)
                                — {{ $product->sku }}
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">الحالة</label>
                <select name="status" class="form-select">
                    <option value="">كل الحالات</option>
                    @foreach($statusEnum as $status)
                        <option
                            value="{{ $status->value }}"
                            @selected(
                                ($filters['status'] ?? '')
                                === $status->value
                            )
                        >
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">من تاريخ</label>
                <input
                    type="date"
                    name="date_from"
                    class="form-input"
                    value="{{ $filters['date_from'] ?? '' }}"
                >
            </div>

            <div class="form-group">
                <label class="form-label">إلى تاريخ</label>
                <input
                    type="date"
                    name="date_to"
                    class="form-input"
                    value="{{ $filters['date_to'] ?? '' }}"
                >
            </div>

            <div class="form-group">
                <label class="form-label">رقم الطلب</label>
                <input
                    type="text"
                    name="request_number"
                    class="form-input"
                    value="{{ $filters['request_number'] ?? '' }}"
                    placeholder="SSR-2026-..."
                >
            </div>

            <div class="ssr-filter-actions">
                <button type="submit" class="btn btn-gold">
                    تطبيق الفلترة
                </button>

                <a
                    href="{{ route('reports.showroom-sweets-requests.index') }}"
                    class="btn btn-outline"
                >
                    إعادة تعيين
                </a>
            </div>
        </div>
    </form>

    <div class="stats-grid ssr-summary-grid">
        <div class="stat-card">
            <div class="stat-info">
                <div class="stat-value">{{ $summary['requests_count'] }}</div>
                <div class="stat-label">إجمالي الطلبات</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-info">
                <div class="stat-value">{{ $summary['completed_count'] }}</div>
                <div class="stat-label">طلبات مكتملة</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-info">
                <div class="stat-value">{{ $summary['items_count'] }}</div>
                <div class="stat-label">إجمالي بنود الأصناف</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-info">
                <div class="stat-value">{{ number_format($summary['requested_quantity'], 3) }}</div>
                <div class="stat-label">إجمالي الكمية المطلوبة</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">الطلبات</div>
                <small class="text-muted">
                    {{ $summary['requests_count'] }} طلب حسب الفلاتر المحددة
                </small>
            </div>
        </div>

        <div class="table-responsive ssr-table-wrap">
            <table class="data-table ssr-table">
                <thead>
                    <tr>
                        <th>رقم الطلب</th>
                        <th>الفرع</th>
                        <th>المصنع</th>
                        <th>تاريخ الطلب</th>
                        <th>مطلوب بتاريخ</th>
                        <th>الحالة</th>
                        <th>الأصناف</th>
                        <th>إجمالي الكمية</th>
                        <th>المحجوز</th>
                        <th>منشئ الطلب</th>
                        <th>الاستلام / الإكمال</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($requests as $showroomRequest)
                        <tr>
                            <td dir="ltr">
                                <strong>{{ $showroomRequest->request_number }}</strong>
                            </td>

                            <td>
                                {{ $showroomRequest->requestingLocation?->name ?? '—' }}
                            </td>

                            <td>
                                {{ $showroomRequest->factoryLocation?->name ?? '—' }}
                            </td>

                            <td>
                                {{ $showroomRequest->created_at?->format('Y-m-d H:i') ?? '—' }}
                            </td>

                            <td>
                                {{ $showroomRequest->needed_by?->format('Y-m-d') ?? '—' }}
                            </td>

                            <td>
                                <span class="badge {{ $showroomRequest->status->badgeClass() }}">
                                    {{ $showroomRequest->status->label() }}
                                </span>
                            </td>

                            <td class="ssr-products-cell">
                                @foreach($showroomRequest->items->take(4) as $item)
                                    <span class="ssr-product-chip">
                                        {{ $item->displayProductName() }}
                                        <b>{{ number_format((float) $item->quantity, 3) }}</b>
                                        <small>{{ $item->requested_unit }}</small>
                                    </span>
                                @endforeach

                                @if($showroomRequest->items->count() > 4)
                                    <span class="ssr-product-more">
                                        +{{ $showroomRequest->items->count() - 4 }}
                                    </span>
                                @endif
                            </td>

                            <td>
                                <strong>
                                    {{ number_format((float) $showroomRequest->requested_quantity_total, 3) }}
                                </strong>
                            </td>

                            <td>
                                {{ number_format((float) $showroomRequest->reserved_quantity_total, 3) }}
                            </td>

                            <td>
                                {{ $showroomRequest->creator?->display_name ?? '—' }}
                            </td>

                            <td>
                                {{ $showroomRequest->fulfilled_at?->format('Y-m-d H:i')
                                    ?? $showroomRequest->received_at?->format('Y-m-d H:i')
                                    ?? '—' }}
                            </td>

                            <td>
                                @can('showroom_sweets_requests.view')
                                    <a
                                        href="{{ route('showroom-sweets-requests.show', $showroomRequest) }}"
                                        class="btn btn-outline btn-sm"
                                    >
                                        فتح
                                    </a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12">
                                <div class="empty-state-sm">
                                    لا توجد طلبات حلويات فروع تطابق الفلاتر المحددة.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if($requests->count())
                    <tfoot>
                        <tr>
                            <th colspan="6">إجمالي النتائج</th>
                            <th>{{ $summary['items_count'] }} بند</th>
                            <th>{{ number_format($summary['requested_quantity'], 3) }}</th>
                            <th>{{ number_format($summary['reserved_quantity'], 3) }}</th>
                            <th colspan="3"></th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        @if($requests->hasPages())
            <div class="card-footer">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@push('styles')
<style>
.ssr-report-page{display:grid;gap:1rem}
.ssr-report-actions{display:flex;gap:.55rem;flex-wrap:wrap}
.ssr-filter-grid{display:grid;grid-template-columns:repeat(6,minmax(145px,1fr)) auto;gap:.75rem;align-items:end}
.ssr-filter-actions{display:flex;gap:.5rem;align-items:center}
.ssr-summary-grid{grid-template-columns:repeat(4,minmax(0,1fr))}
.ssr-table-wrap{overflow-x:auto}
.ssr-table{min-width:1500px}
.ssr-table th,.ssr-table td{text-align:center;vertical-align:middle}
.ssr-products-cell{min-width:280px;text-align:right!important}
.ssr-product-chip{display:inline-flex;align-items:center;gap:.3rem;padding:.22rem .42rem;margin:.12rem;border:1px solid var(--border);border-radius:999px;background:var(--off-white);font-size:.68rem;white-space:nowrap}
.ssr-product-chip b{color:var(--gold-dark,#8a6500)}
.ssr-product-chip small{color:var(--text-muted)}
.ssr-product-more{display:inline-flex;padding:.2rem .4rem;font-size:.65rem;font-weight:800;color:var(--text-muted)}
.ssr-table tfoot th{background:#fff8df;color:#8c6818;font-weight:900}
@media(max-width:1100px){.ssr-filter-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.ssr-summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:700px){.ssr-filter-grid,.ssr-summary-grid{grid-template-columns:1fr}.ssr-filter-actions,.ssr-report-actions{width:100%}.ssr-filter-actions .btn,.ssr-report-actions .btn{flex:1}.page-header{align-items:stretch}}
</style>
@endpush
