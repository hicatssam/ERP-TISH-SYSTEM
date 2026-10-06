@php
    $nativeMeta = app(\App\Support\ReportCatalog::class)->native()[$nativeReport] ?? null;
    $nativeFilters = array_merge(request()->except(['page']), $nativeParams ?? []);
@endphp
@if($nativeMeta && app(\App\Support\ReportCatalog::class)->allowed(auth()->user(), $nativeMeta))
@can('reports.export')
<div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem">
    <a class="btn btn-outline" href="{{ route('reports.native.export', array_merge($nativeFilters, ['report' => $nativeReport, 'format' => 'xlsx'])) }}">تصدير Excel</a>
    <a class="btn btn-outline" href="{{ route('reports.native.export', array_merge($nativeFilters, ['report' => $nativeReport, 'format' => 'pdf'])) }}">تصدير PDF</a>
    <a class="btn btn-outline" target="_blank" href="{{ route('reports.native.export', array_merge($nativeFilters, ['report' => $nativeReport, 'format' => 'print'])) }}">طباعة</a>
</div>
@endcan
@endif
