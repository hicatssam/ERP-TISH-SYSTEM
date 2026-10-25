@extends('layouts.app')
@section('title', 'التقارير')
@section('page-title', 'التقارير')
@section('content')
<style>
    .report-cake-v2 {
        grid-column: span 2;
        border: 1px solid #d6b76d;
        border-right: 5px solid #c99420;
        background: linear-gradient(135deg, #fffaf0 0%, #fff 75%);
        border-radius: 15px;
        padding: 16px 18px;
        box-shadow: 0 4px 12px rgba(17, 39, 65, .06);
        display: flex;
        flex-direction: column;
        gap: 12px;
        min-width: 0;
    }
    .report-cake-v2__heading { display: flex; align-items: center; gap: 9px; color: #132d47; font-weight: 800; font-size: 1.03rem; }
    .report-cake-v2__hint { color: #64748b; font-size: .82rem; line-height: 1.7; }
    .report-cake-v2__actions { display: flex; flex-wrap: wrap; gap: 9px; }
    .report-cake-v2__link {
        display: inline-flex; align-items: center; justify-content: center;
        text-align: center; text-decoration: none;
        min-height: 40px; border-radius: 9px; padding: 9px 13px;
        font-weight: 700; font-size: .82rem;
        border: 1px solid #c99420; color: #172e47; background: #fff;
    }
    .report-cake-v2__link--main { color: #fff; background: #b88313; border-color: #b88313; }
    .report-cake-v2__link:hover, .report-cake-v2__link:focus-visible { box-shadow: 0 0 0 3px rgba(201,148,32,.18); outline: 2px solid transparent; }
    @media (max-width: 650px) {
        .report-cake-v2 { grid-column: 1 / -1; }
        .report-cake-v2__actions { flex-direction: column; }
        .report-cake-v2__link { width: 100%; }
    }
    @media (prefers-reduced-motion: no-preference) {
        .report-cake-v2__link { transition: box-shadow .16s ease, transform .16s ease; }
        .report-cake-v2__link:hover { transform: translateY(-1px); }
    }
</style>
<div class="page-header">
    <div><h1 class="page-heading">التقارير</h1><p class="page-subheading">جميع تقارير النظام حسب الصلاحيات، مع الفلاتر والتصدير والطباعة.</p></div>
    @can('reports.export')
        <a href="{{ route('report-schedules.index') }}" class="btn btn-outline">التقارير المجدولة</a>
    @endcan
</div>
@foreach($reportGroups as $group => $reports)
<section style="margin-bottom:1.5rem">
    <h2 style="font-size:1rem;border-bottom:1px solid var(--border);padding-bottom:.75rem">{{ $group }}</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:.75rem">
        @foreach($reports as $report)
            @if(($report['type'] ?? null) === 'cake-production')
                <div class="report-cake-v2" aria-label="تقرير إنتاج الكيك الموحد">
                    <div class="report-cake-v2__heading">تقرير إنتاج الكيك الموحد</div>
                    <div class="report-cake-v2__hint">عرض الإنتاج مجمّعًا حسب النوع والحجم والنكهة، مع كميات المعرض والمحجوز والطلبات الخاصة. اختر طريقة العرض مباشرة:</div>
                    <div class="report-cake-v2__actions">
                        <a class="report-cake-v2__link report-cake-v2__link--main"
                           href="{{ route('reports.show', ['type' => 'cake-production', 'mode' => 'plan']) }}">
                            خطة الإنتاج المفتوحة
                        </a>
                        <a class="report-cake-v2__link"
                           href="{{ route('reports.show', ['type' => 'cake-production', 'mode' => 'all']) }}">
                            سجل جميع الطلبات
                        </a>
                    </div>
                </div>
            @else
                <a href="{{ $report['url'] }}" class="stat-card stat-gold" style="text-decoration:none">
                    <div class="stat-info">
                        <strong>{{ $report['title'] }}</strong>
                        <div style="font-size:.75rem;color:var(--text-muted);margin-top:.5rem">{{ $report['description'] }}</div>
                    </div>
                </a>
            @endif
        @endforeach
    </div>
</section>
@endforeach
@endsection
