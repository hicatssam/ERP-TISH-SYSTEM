@extends('layouts.app')
@section('title', 'الفئات')
@section('content')
<div class="page-actions">
    <div class="page-actions-title">فئات المنتجات</div>
    <div class="action-btns">
        @can('products.create')
        <button type="button" class="btn btn-gold" onclick="openCategoryCreateModal()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            إضافة فئة
        </button>
        @endcan
    </div>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr><th style="width:64px">أيقونة المنيو</th><th>الاسم</th><th>الاسم بالعربي</th><th>الرابط</th><th>المنتجات</th><th>الترتيب</th><th>الحالة</th><th>الإجراءات</th></tr>
        </thead>
        <tbody>
        @forelse($categories as $cat)
            <tr>
                <td>
                    <span style="width:44px;height:44px;display:grid;place-items:center;border-radius:50%;background:#f6eadc;color:#b32017">
                        @include('customer-menu.partials.category-icon', ['category' => [
                            'icon_key' => $cat->icon_key ?: 'sparkles',
                            'image' => \App\Support\PublicImageUrl::url($cat->image),
                            'icon_explicit' => filled($cat->icon_key),
                        ], 'theme' => ['category_visual' => \App\Models\SystemSetting::get('customer_menu_category_visual', 'icons')]])
                    </span>
                </td>
                <td>{{ $cat->name }}</td>
                <td>{{ $cat->name_ar ?? '—' }}</td>
                <td><code>{{ $cat->slug }}</code></td>
                <td>{{ $cat->products_count }}</td>
                <td>{{ $cat->sort_order ?? 0 }}</td>
                <td><span class="badge {{ $cat->is_active ? 'badge-active' : 'badge-inactive' }}">{{ $cat->is_active ? 'نشطة' : 'معطلة' }}</span></td>
                <td>
                    <div class="actions">
                        <a href="{{ route('categories.edit', $cat) }}" class="btn btn-outline btn-sm">تعديل</a>
                        <form action="{{ route('categories.toggle-status', $cat) }}" method="POST" style="display:inline">@csrf @method('POST')
                            <button class="btn btn-ghost btn-sm">{{ $cat->is_active ? 'تعطيل' : 'تفعيل' }}</button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="8"><div class="empty-state-sm">لا توجد فئات.</div></td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@can('products.create')
<div id="categoryCreateModal" class="category-modal" aria-hidden="true" onclick="closeCategoryCreateModalFromBackdrop(event)">
    <div class="category-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="categoryCreateModalTitle">
        <div class="category-modal-header">
            <div>
                <span class="category-modal-eyebrow">الفئات</span>
                <h3 id="categoryCreateModalTitle">إضافة فئة جديدة</h3>
                <p>أضف الفئة بدون مغادرة صفحة الفئات.</p>
            </div>

            <button type="button" class="category-modal-close" onclick="closeCategoryCreateModal()" aria-label="إغلاق">×</button>
        </div>

        <form action="{{ route('categories.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="category-modal-body">
                @if($errors->any())
                    <div class="category-modal-errors">
                        <strong>تعذر حفظ الفئة:</strong>
                        <ul>
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="category-form-grid">
                    <div class="form-group">
                        <label class="form-label">الاسم (إنجليزي) *</label>
                        <input name="name" class="form-input" value="{{ old('name') }}" required maxlength="100">
                    </div>

                    <div class="form-group">
                        <label class="form-label">الاسم (عربي)</label>
                        <input name="name_ar" class="form-input" value="{{ old('name_ar') }}" maxlength="100">
                    </div>
                </div>

                <div class="category-form-grid category-form-grid-small">
                    <div class="form-group">
                        <label class="form-label">ترتيب العرض</label>
                        <input name="sort_order" type="number" class="form-input" value="{{ old('sort_order', 0) }}" min="0">
                    </div>

                    <div class="form-group">
                        <label class="form-label">صورة الفئة</label>
                        <input type="file" name="image" class="form-input" accept="image/jpeg,image/png,image/webp">
                        <small class="category-help">JPEG / PNG / WebP — حتى 2MB</small>
                    </div>
                </div>

                @include('admin.categories.partials.icon-picker')

                <div class="form-group">
                    <label class="form-label">الوصف</label>
                    <textarea name="description" class="form-textarea" rows="4">{{ old('description') }}</textarea>
                </div>
            </div>

            <div class="category-modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeCategoryCreateModal()">إلغاء</button>
                <button type="submit" class="btn btn-gold">حفظ الفئة</button>
            </div>
        </form>
    </div>
</div>
@endcan

@push('styles')
<style>
.category-modal{
    position:fixed;
    inset:0;
    z-index:99999;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:1rem;
    background:rgba(15,23,42,.58);
    backdrop-filter:blur(3px);
    opacity:0;
    visibility:hidden;
    pointer-events:none;
    transition:.18s ease;
}
.category-modal.is-open{
    opacity:1;
    visibility:visible;
    pointer-events:auto;
}
.category-modal-dialog{
    width:100%;
    max-width:720px;
    background:var(--card-bg,#fff);
    border:1px solid var(--border);
    border-radius:18px;
    box-shadow:0 24px 70px rgba(0,0,0,.24);
    overflow:hidden;
    transform:translateY(16px) scale(.985);
    transition:.18s ease;
}
.category-modal.is-open .category-modal-dialog{
    transform:translateY(0) scale(1);
}
.category-modal-header{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:1rem;
    padding:1.2rem 1.3rem;
    border-bottom:1px solid var(--border);
}
.category-modal-eyebrow{
    display:block;
    color:var(--gold);
    font-size:.7rem;
    font-weight:900;
    margin-bottom:.2rem;
}
.category-modal-header h3{
    margin:0;
    font-size:1.08rem;
    font-weight:900;
}
.category-modal-header p{
    margin:.35rem 0 0;
    color:var(--text-muted);
    font-size:.75rem;
}
.category-modal-close{
    width:36px;
    height:36px;
    border-radius:50%;
    border:1px solid var(--border);
    background:var(--surface,#fff);
    color:var(--text-muted);
    cursor:pointer;
    font-size:1.4rem;
    line-height:1;
}
.category-modal-body{
    display:grid;
    gap:1rem;
    padding:1.25rem 1.3rem;
}
.category-form-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:1rem;
}
.category-form-grid-small{
    grid-template-columns:minmax(140px,.7fr) minmax(250px,1.3fr);
}
.category-modal-footer{
    display:flex;
    justify-content:flex-end;
    gap:.65rem;
    padding:1rem 1.3rem;
    border-top:1px solid var(--border);
    background:var(--off-white);
}
.category-modal-errors{
    padding:.85rem 1rem;
    border:1px solid #fecaca;
    background:#fef2f2;
    color:#991b1b;
    border-radius:10px;
    font-size:.82rem;
}
.category-modal-errors ul{
    margin:.45rem 0 0;
    padding-inline-start:1.1rem;
}
.category-help{
    display:block;
    margin-top:.35rem;
    color:var(--text-muted);
    font-size:.7rem;
}
@media(max-width:680px){
    .category-form-grid,
    .category-form-grid-small{
        grid-template-columns:1fr;
    }
    .category-modal-dialog{
        max-height:92vh;
        overflow:auto;
    }
    .category-modal-footer{
        flex-direction:column-reverse;
    }
    .category-modal-footer .btn{
        width:100%;
    }
}
</style>
@endpush

@push('scripts')
<script>
function openCategoryCreateModal() {
    const modal = document.getElementById('categoryCreateModal');
    if (!modal) return;

    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';

    const input = modal.querySelector('input[name="name"]');
    if (input) setTimeout(() => input.focus(), 80);
}

function closeCategoryCreateModal() {
    const modal = document.getElementById('categoryCreateModal');
    if (!modal) return;

    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

function closeCategoryCreateModalFromBackdrop(event) {
    if (event.target.id === 'categoryCreateModal') {
        closeCategoryCreateModal();
    }
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeCategoryCreateModal();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    @if($errors->any())
        openCategoryCreateModal();
    @endif
});
</script>
@endpush

@endsection
