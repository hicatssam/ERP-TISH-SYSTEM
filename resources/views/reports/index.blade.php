@extends('layouts.app')
@section('title', 'التقارير')
@section('page-title', 'التقارير')
@section('content')
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
            <a href="{{ $report['url'] }}" class="stat-card stat-gold" style="text-decoration:none">
                <div class="stat-info">
                    <strong>{{ $report['title'] }}</strong>
                    <div style="font-size:.75rem;color:var(--text-muted);margin-top:.5rem">{{ $report['description'] }}</div>
                </div>
            </a>
        @endforeach
    </div>
</section>
@endforeach
@endsection
