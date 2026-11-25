@extends('layouts.app')

@section('title', 'الوصفات')
@section('page-title', 'الوصفات')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-heading">الوصفات <span style="font-size:.7em;color:var(--text-muted);font-weight:500">BOM</span></h1>
        <p class="page-subheading">تصفّح وصفات المنتجات حسب الفئة والمنتج، وراجع المكونات قبل الاعتماد.</p>
    </div>
    @can('recipes.manage')
        <a href="{{ route('production.recipes.create') }}" class="btn btn-gold">+ إضافة وصفة</a>
    @endcan
</div>

<div class="recipe-stats">
    <div class="recipe-stat"><small>الوصفات المطابقة</small><strong>{{ $total }}</strong></div>
    <div class="recipe-stat"><small>المسودات</small><strong>{{ $draft }}</strong></div>
    <div class="recipe-stat {{ $missing ? 'recipe-stat-attention' : '' }}"><small>تحتاج إضافة مكونات</small><strong>{{ $missing }}</strong></div>
</div>

<div class="card recipe-filter-card">
    <div class="card-body">
        <form method="GET" class="recipe-filter-grid" id="recipeFilters">
            <div class="filter-group">
                <label class="filter-label" for="recipeSearch">بحث سريع</label>
                <input id="recipeSearch" class="form-input" name="q" value="{{ request('q') }}" placeholder="الاسم أو كود الوصفة">
            </div>
            <div class="filter-group">
                <label class="filter-label" for="recipeCategory">الفئة</label>
                <select id="recipeCategory" class="form-input" name="category_id">
                    <option value="">جميع الفئات</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string)request('category_id') === (string)$category->id)>{{ $category->name_ar ?: $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label" for="recipeProduct">المنتج</label>
                <select id="recipeProduct" class="form-input" name="product_id">
                    <option value="">جميع المنتجات</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" @selected((string)request('product_id') === (string)$product->id)>{{ $product->name_ar ?: $product->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label" for="recipeStatus">حالة الوصفة</label>
                <select id="recipeStatus" class="form-input" name="status">
                    <option value="">كل الحالات</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label" for="recipeVisibility">ظهور المنتج</label>
                <select id="recipeVisibility" class="form-input" name="visibility">
                    <option value="active" @selected($visibility === 'active')>المنتجات النشطة</option>
                    <option value="inactive" @selected($visibility === 'inactive')>المنتجات المخفية</option>
                    <option value="all" @selected($visibility === 'all')>الكل</option>
                </select>
            </div>
            <div class="recipe-filter-actions">
                <button type="submit" class="btn btn-gold">عرض النتائج</button>
                <a href="{{ route('production.recipes.index') }}" class="btn btn-outline">إعادة ضبط</a>
            </div>
        </form>
    </div>
</div>

<div class="card recipe-list-card">
    <div class="recipe-list-head">
        <div>
            <strong>قائمة الوصفات</strong>
            <span>{{ $recipes->total() }} نتيجة</span>
        </div>
        <span class="recipe-list-note">يمكنك تعديل المسودات قبل اعتمادها</span>
    </div>

    <div class="table-wrap">
        <table class="data-table recipe-table">
            <thead>
            <tr>
                <th>المنتج والوصفة</th>
                <th>الفئة</th>
                <th>المكونات</th>
                <th>الإصدار / الناتج</th>
                <th>الحالة</th>
                <th>الإجراءات</th>
            </tr>
            </thead>
            <tbody>
            @forelse($recipes as $recipe)
                <tr>
                    <td>
                        <div class="recipe-name">{{ $recipe->product?->name_ar ?: $recipe->product?->name ?: 'منتج غير متوفر' }}</div>
                        <div class="recipe-subtitle">{{ $recipe->name }}</div>
                        @if($recipe->productVariant)
                            <div class="recipe-subtitle">{{ $recipe->productVariant->displayName() }}</div>
                        @endif
                        <div class="recipe-code">{{ $recipe->code }}</div>
                    </td>
                    <td><span class="recipe-category">{{ $recipe->product?->category?->name_ar ?: $recipe->product?->category?->name ?: 'بدون فئة' }}</span></td>
                    <td>
                        @if($recipe->items_count)
                            <span class="recipe-ingredient-count">{{ $recipe->items_count }} مكون</span>
                        @else
                            <span class="recipe-needs-items">تحتاج مكونات</span>
                        @endif
                    </td>
                    <td><strong>V{{ $recipe->version }}</strong><div class="recipe-subtitle">ناتج {{ number_format((float)$recipe->yield_quantity, 3) }}</div></td>
                    <td>
                        <span class="badge {{ $recipe->status->value === 'approved' ? 'badge-active' : ($recipe->status->value === 'archived' ? 'badge-inactive' : 'badge-pending') }}">{{ $recipe->status->label() }}</span>
                        @if($recipe->is_active)<div class="recipe-subtitle">الإصدار الفعّال</div>@endif
                        @if(!$recipe->product?->is_active)<div class="recipe-subtitle">المنتج مخفي</div>@endif
                    </td>
                    <td>
                        <div class="recipe-actions">
                            <a class="btn btn-outline btn-sm" href="{{ route('production.recipes.show', $recipe) }}">عرض</a>
                            @can('recipes.update')
                                @if($recipe->isEditable())
                                    <a class="btn btn-gold btn-sm" href="{{ route('production.recipes.edit', $recipe) }}">تعديل</a>
                                @endif
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty-state"><h3>لا توجد وصفات بهذه الفلاتر</h3><p>جرّب فئة أخرى أو إعادة ضبط البحث.</p></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="recipe-pagination">{{ $recipes->links() }}</div>
</div>
@endsection

@push('styles')
<style>
.recipe-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:0 0 16px}
.recipe-stat{background:var(--surface,#fff);border:1px solid var(--border,#e8e1e4);border-radius:14px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px}
.recipe-stat small{color:var(--text-muted);font-weight:600}.recipe-stat strong{font-size:1.55rem;line-height:1.2}
.recipe-stat-attention strong{color:#b66a0b}
.recipe-filter-card{margin-bottom:16px}
.recipe-filter-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;align-items:end}
.recipe-filter-grid .filter-group{min-width:0}
.recipe-filter-grid .filter-label{display:block;margin-bottom:6px}
.recipe-filter-grid .form-input{width:100%;min-width:0}
.recipe-filter-actions{display:flex;gap:8px;align-items:end;flex-wrap:wrap}
.recipe-list-head{display:flex;justify-content:space-between;align-items:center;padding:17px 20px;gap:10px;border-bottom:1px solid var(--border,#e8e1e4)}
.recipe-list-head strong{font-size:1.06rem}.recipe-list-head span{font-size:.85rem;color:var(--text-muted);margin-inline-start:10px}
.recipe-list-note{font-size:.82rem}
.recipe-table td{vertical-align:middle;padding-top:13px;padding-bottom:13px}
.recipe-name{font-weight:750;font-size:.97rem}
.recipe-subtitle{font-size:.78rem;color:var(--text-muted);margin-top:4px}
.recipe-code{color:var(--text-muted);font-size:.70rem;direction:ltr;text-align:right;overflow-wrap:anywhere;margin-top:6px}
.recipe-category{display:inline-block;border:1px solid var(--border,#e8e1e4);padding:5px 9px;border-radius:20px;font-size:.8rem;white-space:nowrap}
.recipe-ingredient-count{font-weight:650;font-size:.88rem}
.recipe-needs-items{display:inline-block;border-radius:8px;background:#fff2e1;color:#9b5b00;padding:5px 9px;font-size:.83rem;font-weight:700}
.recipe-actions{display:flex;gap:6px;flex-wrap:wrap}
.recipe-pagination{padding:16px 20px}
@media(max-width:1100px){.recipe-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:700px){
.recipe-stats{gap:7px}.recipe-stat{display:block;padding:12px}.recipe-stat small{display:block;margin-bottom:8px;font-size:.72rem}.recipe-stat strong{font-size:1.28rem}
.recipe-filter-grid{grid-template-columns:minmax(0,1fr)}
.recipe-list-head{padding:14px}.recipe-list-note{display:none}
.recipe-table thead{display:none}
.recipe-table,.recipe-table tbody,.recipe-table tr,.recipe-table td{display:block;width:100%}
.recipe-table tr{border-bottom:1px solid var(--border,#e8e1e4);padding:12px 14px}
.recipe-table td{border:0!important;padding:5px 0!important}
.recipe-table td:nth-child(2),.recipe-table td:nth-child(3),.recipe-table td:nth-child(4),.recipe-table td:nth-child(5){display:inline-block;width:auto;margin-inline-end:12px;vertical-align:middle}
.recipe-actions{margin-top:8px}
.recipe-code{text-align:right}
}
</style>
@endpush
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const category = document.getElementById('recipeCategory');
    const product = document.getElementById('recipeProduct');
    const visibility = document.getElementById('recipeVisibility');
    category?.addEventListener('change', function () { if (product) product.value = ''; });
    visibility?.addEventListener('change', function () {
        if (category) category.value = '';
        if (product) product.value = '';
    });
});
</script>
@endpush
