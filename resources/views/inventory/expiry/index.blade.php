@extends('layouts.app')

@section('title', 'صلاحية المخزون')

@section('content')
@include('reports.partials.native-actions', ['nativeReport' => 'expiry'])
<style>
    .expiry-page{max-width:1400px;width:100%;min-width:0;margin:0 auto;container-type:inline-size}
    .expiry-page *{box-sizing:border-box}
    .expiry-header{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:.8rem;margin-bottom:1rem}
    .expiry-actions{display:flex;gap:.5rem;flex-wrap:wrap}
    .expiry-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(145px,100%),1fr));gap:.65rem;margin-bottom:1rem}
    .expiry-card{display:block;min-width:0;padding:.8rem 1rem;border:1px solid var(--border);border-radius:12px;background:var(--surface);color:var(--text);text-decoration:none}
    .expiry-card small{display:block;color:var(--text-muted);margin-bottom:.35rem;font-size:.8rem}
    .expiry-card strong{font-size:1.5rem;font-variant-numeric:tabular-nums}
    .expiry-card[aria-current="true"]{border-color:var(--primary,#a91f17);box-shadow:inset 0 -3px 0 var(--primary,#a91f17)}
    .expiry-filters{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.7rem;align-items:end;padding:.9rem;border:1px solid var(--border);border-radius:12px;background:var(--surface);margin-bottom:1rem}
    .expiry-filters .form-group{min-width:0;margin:0}
    .expiry-filters .form-input{width:100%;min-width:0}
    .expiry-filter-search{grid-column:1/-1}
    .expiry-filter-actions{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap}
    .expiry-table-card{border:1px solid var(--border);border-radius:12px;overflow:hidden;background:var(--surface)}
    .expiry-table-heading{display:flex;justify-content:space-between;align-items:center;gap:.6rem;padding:.8rem 1rem;border-bottom:1px solid var(--border)}
    .expiry-table-heading strong{font-size:.95rem}
    .expiry-table-heading small{color:var(--text-muted);font-size:.76rem}
    .expiry-table{width:100%;min-width:0;table-layout:fixed;border-collapse:collapse}
    .expiry-table th,.expiry-table td{padding:.75rem .7rem;border-bottom:1px solid var(--border);text-align:right;vertical-align:middle;overflow-wrap:anywhere}
    .expiry-table th{font-size:.75rem;color:var(--text-muted);background:color-mix(in srgb,var(--surface) 92%,var(--background))}
    .expiry-table tr:last-child td{border-bottom:0}
    .expiry-table tbody tr:hover{background:color-mix(in srgb,var(--surface) 95%,var(--text))}
    .expiry-table tbody tr[data-urgency="expired"],.expiry-table tbody tr[data-urgency="today"]{background:color-mix(in srgb,#be123c 7%,var(--surface))}
    .expiry-product{font-size:.93rem;font-weight:750;display:block}
    .expiry-meta{display:block;margin-top:.25rem;font-size:.73rem;color:var(--text-muted);line-height:1.55}
    .expiry-code{display:block;direction:ltr;text-align:right;font-size:.7rem;color:var(--text-muted);overflow-wrap:anywhere}
    .expiry-quantity{display:flex;align-items:baseline;gap:.3rem;flex-wrap:wrap;font-variant-numeric:tabular-nums}
    .expiry-quantity strong{font-size:1.1rem;font-weight:800}
    .expiry-quantity small{color:var(--text-muted);font-size:.75rem}
    .expiry-quantity .unit-unknown{color:#a16207}
    .expiry-progress{display:block;width:100%;max-width:150px;height:5px;margin-top:.45rem;background:color-mix(in srgb,var(--text-muted) 18%,var(--surface));border-radius:10px;overflow:hidden}
    .expiry-progress span{display:block;height:100%;background:var(--primary,#9f2346);border-radius:10px}
    .expiry-date{display:block;font-size:.82rem;font-variant-numeric:tabular-nums;unicode-bidi:isolate;margin-top:.3rem}
    .expiry-badge{display:inline-flex;align-items:center;gap:.3rem;padding:.26rem .55rem;border-radius:8px;font-size:.75rem;font-weight:750}
    .expiry-badge.expired,.expiry-badge.critical{background:#fff0f2;color:#a80d37}
    .expiry-badge.warning{background:#fff7ed;color:#9a3412}
    .expiry-badge.soon{background:#fefce8;color:#854d0e}
    .expiry-badge.safe{background:#ecfdf5;color:#047857}
    .expiry-scan-result{border:1px solid var(--border);border-inline-start:4px solid #15803d;background:var(--surface);border-radius:12px;padding:.9rem 1rem;margin-bottom:1rem}
    .expiry-scan-result[data-tone="warning"]{border-inline-start-color:#c27803}
    .expiry-scan-result p{margin:.4rem 0 0;font-size:.82rem;color:var(--text-muted)}
    .expiry-scan-counts{display:flex;gap:.6rem 1.3rem;flex-wrap:wrap;margin-top:.65rem;font-size:.82rem}
    .expiry-empty{text-align:center!important;padding:2rem!important;color:var(--text-muted)}
    @container(min-width:850px){.expiry-filters{grid-template-columns:minmax(0,1.65fr) repeat(3,minmax(0,1fr)) auto}.expiry-filter-search{grid-column:auto}}
    @container(max-width:650px){
        .expiry-table,.expiry-table tbody{display:block}
        .expiry-table thead{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap}
        .expiry-table tbody tr{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:.65rem .8rem;padding:.8rem;border-bottom:1px solid var(--border)}
        .expiry-table tbody tr:last-child{border-bottom:0}
        .expiry-table td{display:block;padding:0;border:0}
        .expiry-table .expiry-product-cell{grid-column:1/-1}
        .expiry-table .expiry-location-cell{grid-column:1/-1;order:4;border-top:1px dashed var(--border);padding-top:.45rem}
        .expiry-table .expiry-empty{grid-column:1/-1}
        .expiry-table td[data-label]::before{content:attr(data-label);display:block;font-size:.69rem;color:var(--text-muted);margin-bottom:.25rem}
        .expiry-filters{grid-template-columns:minmax(0,1fr)}
        .expiry-filter-actions{grid-column:1/-1}
    }
</style>

<div class="expiry-page">
    <div class="expiry-header">
        <div>
            <h1 class="page-heading">صلاحية المخزون</h1>
            <p class="page-subheading">متابعة الدفعات وتواريخ الصلاحية والكميات المتبقية حسب الموقع.</p>
        </div>

        <div class="expiry-actions">
            <a href="{{ route('inventory.expiry.print', request()->query()) }}" target="_blank" class="btn btn-outline">طباعة التقرير</a>
            @can('inventory.expiry-alerts.run')
                <form method="POST" action="{{ route('inventory.expiry.scan') }}">
                    @csrf
                    <button class="btn btn-gold" type="submit">فحص الصلاحية والتنبيهات</button>
                </form>
            @endcan
            <a href="{{ url('/inventory') }}" class="btn btn-outline">العودة للمخزون</a>
        </div>
    </div>

    @if(session()->has('expiry_scan_result'))
        @php($scan = session('expiry_scan_result'))
        @php($scanWarning = $scan['failed'] > 0 || ($scan['qualifying'] > 0 && $scan['sent'] === 0))
        <section class="expiry-scan-result" role="status" data-tone="{{ $scanWarning ? 'warning' : 'success' }}">
            <strong>اكتمل فحص الصلاحية</strong>
            <div class="expiry-scan-counts">
                <span><b>{{ $scan['scanned'] }}</b> دفعة مفحوصة</span>
                <span><b>{{ $scan['qualifying'] }}</b> تستدعي التنبيه</span>
                <span><b>{{ $scan['sent'] }}</b> إشعار جديد مرسل</span>
                <span><b>{{ $scan['skipped'] }}</b> إرسال متجاوز</span>
                <span><b>{{ $scan['failed'] }}</b> إرسال فاشل</span>
            </div>
            @if($scan['qualifying'] === 0)
                <p>لا توجد دفعات تستدعي التنبيه ضمن إعدادات المراقبة الحالية.</p>
            @elseif($scan['recipients'] === 0)
                <p>لم يُعثر على مستلمين مؤهلين للتنبيه. راجع صلاحيات المستلمين وتوزيعهم على المواقع.</p>
            @elseif($scan['sent'] === 0)
                <p>لم تُرسل إشعارات جديدة. قد تكون الإشعارات أُرسلت سابقًا، أو القنوات غير مفعلة أو غير مهيأة. راجع إعدادات التنبيهات وسجل الإرسال.</p>
            @endif
            @if($scan['failed'] > 0)
                <p>بعض عمليات الإرسال فشلت. راجع سجل الإرسال قبل إعادة المحاولة.</p>
            @endif
        </section>
    @endif

    <div class="expiry-cards">
        <a class="expiry-card" aria-current="{{ $status === 'expired' ? 'true' : 'false' }}" href="{{ route('inventory.expiry.index', array_merge(request()->except(['page','status']), ['status' => 'expired'])) }}">
            <small>منتهي الصلاحية</small>
            <strong>{{ number_format($stats['expired']) }}</strong>
        </a>

        <a class="expiry-card" aria-current="{{ $status === '7' ? 'true' : 'false' }}" href="{{ route('inventory.expiry.index', array_merge(request()->except(['page','status']), ['status' => '7'])) }}">
            <small>خلال 7 أيام</small>
            <strong>{{ number_format($stats['days_7']) }}</strong>
        </a>

        <a class="expiry-card" aria-current="{{ $status === '30' ? 'true' : 'false' }}" href="{{ route('inventory.expiry.index', array_merge(request()->except(['page','status']), ['status' => '30'])) }}">
            <small>8–30 يومًا</small>
            <strong>{{ number_format($stats['days_30']) }}</strong>
        </a>

        <a class="expiry-card" aria-current="{{ $status === '60' ? 'true' : 'false' }}" href="{{ route('inventory.expiry.index', array_merge(request()->except(['page','status']), ['status' => '60'])) }}">
            <small>31–60 يومًا</small>
            <strong>{{ number_format($stats['days_60']) }}</strong>
        </a>

        <a class="expiry-card" aria-current="{{ $status === '' ? 'true' : 'false' }}" href="{{ route('inventory.expiry.index', request()->except(['page','status'])) }}">
            <small>إجمالي الدفعات</small>
            <strong>{{ number_format($stats['all']) }}</strong>
        </a>
    </div>

    <form method="GET" action="{{ route('inventory.expiry.index') }}" class="expiry-filters">
        <div class="form-group expiry-filter-search">
            <label class="form-label">بحث</label>
            <input class="form-input" name="q" value="{{ $search }}" placeholder="اسم المنتج أو رقم الدفعة">
        </div>

        <div class="form-group">
            <label class="form-label">الحالة</label>
            <select class="form-input" name="status">
                <option value="">الكل</option>
                <option value="expired" @selected($status === 'expired')>منتهي</option>
                <option value="7" @selected($status === '7')>خلال 7 أيام</option>
                <option value="30" @selected($status === '30')>8–30 يومًا</option>
                <option value="60" @selected($status === '60')>31–60 يومًا</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">الموقع</label>
            <select class="form-input" name="location_id">
                <option value="">كل المواقع</option>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}" @selected((int)$locationId === (int)$location->id)>
                        {{ $location->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">المنتج</label>
            <select class="form-input" name="product_id">
                <option value="">كل المنتجات</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}" @selected((int)$productId === (int)$product->id)>
                        {{ $product->name_ar ?: $product->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="expiry-filter-actions">
            <button class="btn btn-gold" type="submit">تصفية</button>
            <a class="btn btn-outline" href="{{ route('inventory.expiry.index') }}">مسح</a>
        </div>
    </form>

    <div class="expiry-table-card">
        <div class="expiry-table-heading">
            <strong>دفعات المخزون</strong>
            <small>{{ $batches->total() }} دفعة مطابقة</small>
        </div>
        <table class="expiry-table" aria-label="دفعات صلاحية المخزون">
            <thead><tr>
                <th scope="col" style="width:32%">المنتج والدفعة</th>
                <th scope="col" style="width:24%">الكمية المتبقية</th>
                <th scope="col" style="width:24%">الصلاحية</th>
                <th scope="col" style="width:20%">الموقع والإنتاج</th>
            </tr></thead>
            <tbody>
                @forelse($batches as $batch)
                    @php
                        $quantityDisplay = \App\Support\InventoryQuantityDisplay::forBatch($batch);
                        $received = (float) $batch->received_quantity;
                        $remaining = (float) $batch->available_quantity;
                        $ratio = $received > 0 && $remaining >= 0 && $remaining <= $received ? $remaining / $received * 100 : null;
                        $percent = $ratio === null ? null : rtrim(rtrim(number_format($ratio, 1, '.', ''), '0'), '.');
                        $expiryDate = \Illuminate\Support\Carbon::parse($batch->expiry_date)->format('d/m/Y');
                        $productionDate = $batch->manufacturing_date ? \Illuminate\Support\Carbon::parse($batch->manufacturing_date)->format('d/m/Y') : null;
                        $days = (int) $batch->days_left;
                        $urgencyText = match(true) {
                            $days < 0 => 'منتهي منذ '.abs($days).' يوم',
                            $days === 0 => 'ينتهي اليوم',
                            $days === 1 => 'ينتهي غدًا',
                            $days === 2 => 'ينتهي بعد يومين',
                            $days <= 10 => 'متبقي '.$days.' أيام',
                            default => 'متبقي '.$days.' يومًا',
                        };
                    @endphp
                    <tr data-urgency="{{ $days < 0 ? 'expired' : ($days === 0 ? 'today' : $batch->expiry_status) }}">
                        <td class="expiry-product-cell">
                            <strong class="expiry-product">{{ $batch->product_name_ar ?: ($batch->product_name ?? ('#'.$batch->product_id)) }}</strong>
                            <span class="expiry-code" title="معرّف الدفعة: {{ $batch->id }}">{{ $batch->batch_number ?: ('#'.$batch->id) }}</span>
                        </td>
                        <td data-label="الكمية المتبقية">
                            <span class="expiry-quantity" title="{{ $quantityDisplay['original'] }}">
                                <strong dir="ltr">{{ $quantityDisplay['amount'] }}</strong>
                                <small @class(['unit-unknown' => !$quantityDisplay['known']])>{{ $quantityDisplay['unit'] }}</small>
                            </span>
                            @if($percent !== null)
                                <span class="expiry-progress" role="progressbar" aria-label="المتبقي من الكمية المستلمة" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}">
                                    <span style="width:{{ $percent }}%"></span>
                                </span>
                                <small class="expiry-meta"><bdi>{{ $percent }}%</bdi> من المستلم</small>
                            @endif
                        </td>
                        <td data-label="الصلاحية">
                            <span class="expiry-badge {{ $batch->expiry_status }}">{{ $urgencyText }}</span>
                            <time class="expiry-date" dir="ltr" datetime="{{ \Illuminate\Support\Carbon::parse($batch->expiry_date)->toDateString() }}">{{ $expiryDate }}</time>
                        </td>
                        <td class="expiry-location-cell">
                            <span>{{ $batch->location_name ?? '—' }}</span>
                            <small class="expiry-meta">الإنتاج: <bdi>{{ $productionDate ?? 'غير محدد' }}</bdi></small>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="expiry-empty">لا توجد دفعات مطابقة.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:1rem">
        {{ $batches->links() }}
    </div>
</div>
@endsection
