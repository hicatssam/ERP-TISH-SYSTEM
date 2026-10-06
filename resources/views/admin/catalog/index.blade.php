@extends('layouts.app')

@section('title', 'إعدادات المنتجات')
@section('page-title', 'إعدادات المنتجات')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-heading">إعدادات المنتجات</h1>
        <p class="page-subheading">
            إعدادات المنتجات الخاصة بالمخبز والحلويات والصالة.
        </p>
    </div>

    <div class="page-header-actions">
        <a href="{{ route('products.index') }}" class="btn btn-ghost btn-sm">
            المنتجات
        </a>
        <a href="{{ route('catalog.units.index') }}" class="btn btn-outline btn-sm">
            وحدات القياس
        </a>
    </div>
</div>

<div class="stats-grid" style="grid-template-columns:minmax(220px,360px);margin-bottom:1rem">
    <div class="stat-card stat-gold">
        <div class="stat-info" style="width:100%">
            <div class="stat-value">{{ $stats['units'] }}</div>
            <div class="stat-label">وحدات القياس</div>
            <div style="margin-top:.6rem">
                <a href="{{ route('catalog.units.index') }}" class="btn btn-ghost btn-sm">
                    إدارة الوحدات
                </a>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">كتالوج دهب</span>
    </div>

    <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem">
            <div>
                <strong>المخبز والحلويات</strong>
                <p style="color:var(--text-muted);margin:.4rem 0 0">
                    أصناف الإنتاج والحلويات والكيك مرتبطة بالفئات ووحدات القياس.
                </p>
            </div>

            <div>
                <strong>الصالة والمنيو</strong>
                <p style="color:var(--text-muted);margin:.4rem 0 0">
                    نفس المنتجات يمكن ربطها بالمنيو ونقطة البيع حسب الفرع.
                </p>
            </div>

            <div>
                <strong>المخزون والإنتاج</strong>
                <p style="color:var(--text-muted);margin:.4rem 0 0">
                    وحدات القياس والدُفعات والصلاحية تبقى متوافقة مع المخزون والوصفات.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
