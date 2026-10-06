@extends('layouts.app')

@section('title', 'تقرير Take Away')
@section('page-title', 'تقرير Take Away')

@section('content')
<div class="ta-page">
    <div class="page-header">
        <div>
            <h1 class="page-heading">تقرير Take Away اليومي</h1>
            <p class="page-subheading">
                تقرير مستقل لأصناف Take Away المختارة من منيو الفرع، ويقرأ المبيعات تلقائيًا من طلبات Take Away.
            </p>
        </div>

        @can('restaurant_menu.manage')
            <a
                href="{{ route('restaurant.menu.index', ['location_id' => $location->id]) }}"
                class="btn btn-outline"
            >
                إدارة منتجات Take Away
            </a>
        @endcan
    </div>

    <form method="GET" action="{{ route('restaurant.takeaway-report.index') }}" class="card ta-filter">
        <div class="card-body ta-filter-grid">
            <div class="form-group">
                <label class="form-label">الفرع</label>
                <select name="location_id" class="form-select">
                    @foreach($locations as $branch)
                        <option value="{{ $branch->id }}" @selected((int) $branch->id === (int) $location->id)>
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">التاريخ</label>
                <input type="date" name="date" class="form-input" value="{{ $date }}" max="{{ now()->toDateString() }}">
            </div>

            <button type="submit" class="btn btn-gold">عرض التقرير</button>
        </div>
    </form>

    <div class="stats-grid ta-stats">
        <div class="stat-card">
            <div class="stat-info">
                <div class="stat-value">{{ $totals['products'] }}</div>
                <div class="stat-label">منتجات Take Away</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-info">
                <div class="stat-value">{{ number_format($totals['sold_quantity'], 3) }}</div>
                <div class="stat-label">إجمالي الكمية المباعة</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-info">
                <div class="stat-value">{{ $totals['orders_count'] }}</div>
                <div class="stat-label">مرات الظهور في الطلبات</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-info">
                <div class="stat-value">{{ number_format($totals['sales_total'], 2) }}</div>
                <div class="stat-label">إجمالي مبيعات Take Away</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">مبيعات منتجات Take Away</div>
                <small class="text-muted">{{ $location->name }} — {{ $date }}</small>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table ta-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>المنتج</th>
                        <th>SKU</th>
                        <th>سعر الوحدة</th>
                        <th>الكمية المباعة</th>
                        <th>عدد الطلبات</th>
                        <th>إجمالي المبيعات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                <strong>{{ $row['name'] }}</strong>
                            </td>
                            <td dir="ltr">{{ $row['sku'] ?: '—' }}</td>
                            <td>{{ number_format($row['unit_price'], 2) }}</td>
                            <td>
                                <strong>{{ number_format($row['sold_quantity'], 3) }}</strong>
                            </td>
                            <td>{{ $row['orders_count'] }}</td>
                            <td>{{ number_format($row['sales_total'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state-sm">
                                    لا توجد منتجات محددة كـ Take Away لهذا الفرع.
                                    @can('restaurant_menu.manage')
                                        حدّد المنتجات من صفحة المنيو.
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if($rows->isNotEmpty())
                    <tfoot>
                        <tr>
                            <th colspan="4">الإجمالي</th>
                            <th>{{ number_format($totals['sold_quantity'], 3) }}</th>
                            <th>{{ $totals['orders_count'] }}</th>
                            <th>{{ number_format($totals['sales_total'], 2) }}</th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.ta-page{display:grid;gap:1rem}
.ta-filter-grid{display:grid;grid-template-columns:minmax(180px,1fr) minmax(180px,1fr) auto;gap:.75rem;align-items:end}
.ta-stats{grid-template-columns:repeat(4,minmax(0,1fr))}
.ta-table tfoot th{background:#fff8df;color:#9a6a00;font-weight:900}
@media(max-width:900px){.ta-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:700px){.ta-filter-grid,.ta-stats{grid-template-columns:1fr}.ta-filter-grid .btn{width:100%}}
</style>
@endpush
