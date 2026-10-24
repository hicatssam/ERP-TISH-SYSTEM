@extends('layouts.app')
@section('title', 'طلب كيك معرض جديد')

@section('content')
<div class="page-header">
    <h1 class="page-heading">طلب كيك معرض جديد</h1>
    <p class="page-subheading">
        <a href="{{ route('showroom-cake-requests.index') }}">طلبات المعرض</a> &laquo; جديد
    </p>
</div>

@if($errors->any())
<div class="alert alert-danger scr-errors" role="alert">
    <strong>يرجى تصحيح الأخطاء التالية:</strong>
    <ul>
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form action="{{ route('showroom-cake-requests.store') }}" method="POST" id="scrForm" data-daily-order-form>
    @csrf
    <div style="display:grid;gap:1.5rem;max-width:1000px">

        {{-- Header info --}}
        <div class="card">
            <div class="card-header"><span class="card-title">بيانات الطلب</span></div>
            <div class="card-body scr-grid scr-grid-2">
                <div class="form-group">
                    <label class="form-label">المعرض / الفرع *</label>

                    @if($canChooseBranch)
                        <select
                            name="requesting_location_id"
                            class="form-select @error('requesting_location_id') is-invalid @enderror"
                            required
                        >
                            <option value="">اختر الفرع الطالب</option>
                            @foreach($branches as $branchOption)
                                <option
                                    value="{{ $branchOption->id }}"
                                    @selected(old('requesting_location_id') == $branchOption->id)
                                >
                                    {{ $branchOption->name }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <input
                            type="text"
                            class="form-input"
                            value="{{ $branch?->name ?? 'غير محدد' }}"
                            disabled
                        >
                        <small class="form-help">سيتم إنشاء الطلب باسم فرعك الحالي.</small>
                    @endif

                    @error('requesting_location_id')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">المصنع *</label>
                    <select
                        name="factory_location_id"
                        class="form-select @error('factory_location_id') is-invalid @enderror"
                        required
                    >
                        <option value="">اختر المصنع</option>
                        @foreach($factories as $factory)
                            <option
                                value="{{ $factory->id }}"
                                @selected(old('factory_location_id') == $factory->id)
                            >
                                {{ $factory->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('factory_location_id')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">تاريخ الحاجة (اختياري)</label>
                    <input type="date"
                           name="needed_by"
                           class="form-input @error('needed_by') is-invalid @enderror"
                           value="{{ old('needed_by', now()->addDay()->toDateString()) }}">
                    @error('needed_by')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group" style="grid-column:1/-1">
                    <label class="form-label">ملاحظات عامة</label>
                    <textarea name="notes"
                              class="form-textarea @error('notes') is-invalid @enderror"
                              rows="2"
                              placeholder="أي ملاحظة إضافية للمصنع...">{{ old('notes') }}</textarea>
                    @error('notes')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            </div>
        </div>

        {{-- Catalog-driven cake lines; only selected rows become order items. --}}
        <div class="card">
            <div class="card-header"><span class="card-title">منتجات الكيك المتاحة للطلب</span></div>
            <div class="card-body">
                <p class="scr-catalog-help">جميع منتجات فئة الكيك تظهر تلقائيًا. اختر الحجم وحدد كمية المعرض والمحجوز للعملاء لكل صنف. الأصناف غير المختارة لن تُرسل.</p>
                <input id="cakeCatalogSearch" class="form-input" type="search" placeholder="بحث باسم المنتج..." aria-label="بحث في منتجات الكيك" style="margin-bottom:1rem">
                <p class="scr-catalog-muted">لكل حجم: حدّد كمية المعرض، المحجوز للعملاء، وعدد الطبقات (اختياري).</p>
                <div id="cakeCatalogRows" class="scr-catalog-list">
                    @forelse($cakeProducts as $product)
                        @php
                            $cakeName = $product->name_ar ?: $product->name;
                            $cakeImage = $product->image
                                ? (\Illuminate\Support\Str::startsWith($product->image, ['http://', 'https://', '/'])
                                    ? $product->image : asset('storage/' . $product->image))
                                : null;
                            $cakeVariants = $product->activeVariants;
                        @endphp
                        <section class="scr-catalog-product" data-catalog-product data-name="{{ mb_strtolower($cakeName) }}">
                            <div class="scr-catalog-title">
                                @if($cakeImage)
                                    <img src="{{ $cakeImage }}" alt="" loading="lazy" width="56" height="56" class="scr-catalog-image">
                                @else
                                    <span class="scr-catalog-image scr-catalog-image-empty" aria-hidden="true">🎂</span>
                                @endif
                                <div>
                                    <strong>{{ $cakeName }}</strong>
                                    <div class="scr-catalog-muted">{{ $cakeVariants->isNotEmpty() ? 'اختر الحجم المطلوب' : 'الحجم الافتراضي' }}</div>
                                </div>
                            </div>
                            @if($cakeVariants->isNotEmpty())
                                @foreach($cakeVariants as $variant)
                                    <div class="scr-catalog-line" data-cake-line data-product-id="{{ $product->id }}" data-variant-id="{{ $variant->id }}" data-cake-type="{{ $cakeName }}" data-cake-size="{{ $variant->size?->displayName() ?: $variant->displayName() }}">
                                        <label class="scr-catalog-size">
                                            <input type="checkbox" class="scr-catalog-check" aria-label="اختيار {{ $cakeName }} - {{ $variant->size?->displayName() ?: $variant->displayName() }}">
                                            <span>{{ $variant->size?->displayName() ?: $variant->displayName() }}</span>
                                        </label>
                                        <label>للمعرض <input type="number" class="form-input scr-qty-showroom" value="0" min="0" max="99999" inputmode="numeric" aria-label="كمية المعرض {{ $cakeName }}"></label>
                                        <label>محجوز للعملاء <input type="number" class="form-input scr-qty-reserved" value="0" min="0" max="99999" inputmode="numeric" aria-label="الكمية المحجوزة {{ $cakeName }}"></label>
                                        <label>عدد الطبقات <input type="number" class="form-input scr-layer-count" min="1" max="20" placeholder="اختياري"></label>
                                    </div>
                                @endforeach
                            @else
                                <div class="scr-catalog-line" data-cake-line data-product-id="{{ $product->id }}" data-variant-id="" data-cake-type="{{ $cakeName }}" data-cake-size="">
                                    <label class="scr-catalog-size"><input type="checkbox" class="scr-catalog-check"> <span>افتراضي</span></label>
                                    <label>للمعرض <input type="number" class="form-input scr-qty-showroom" value="0" min="0" max="99999" inputmode="numeric"></label>
                                    <label>محجوز للعملاء <input type="number" class="form-input scr-qty-reserved" value="0" min="0" max="99999" inputmode="numeric"></label>
                                    <label>عدد الطبقات <input type="number" class="form-input scr-layer-count" min="1" max="20" placeholder="اختياري"></label>
                                </div>
                            @endif
                        </section>
                    @empty
                        <p>لا توجد منتجات نشطة ضمن فئة الكيك. أضف منتجات الكيك من الكتالوج أولاً.</p>
                    @endforelse
                </div>
                <div class="scr-catalog-summary" role="status" aria-live="polite">
                    <span>الأصناف المختارة: <strong id="cakeChosenCount">0</strong></span>
                    <span>إجمالي المعرض: <strong id="cakeShowroomCount">0</strong></span>
                    <span>إجمالي المحجوز: <strong id="cakeReservedCount">0</strong></span>
                </div>
                @error('items')<span class="form-error">{{ $message }}</span>@enderror
            </div>
        </div>

        @can('create', \App\Models\StockRequest::class)
            @include('sales.partials.daily-raw-materials-form')
        @endcan
        <div class="alert alert-danger" data-order-error role="alert" tabindex="-1" hidden></div>

        <div style="display:flex;gap:.75rem">
            <button class="btn btn-gold" type="submit">إرسال الطلب</button>
            <a href="{{ route('showroom-cake-requests.index') }}" class="btn btn-ghost">إلغاء</a>
        </div>
    </div>
</form>

<style>
.scr-catalog-help,.scr-catalog-muted{font-size:.83rem;color:var(--text-muted)}
.scr-catalog-list{display:grid;gap:.85rem}
.scr-catalog-product{padding:1rem;border:1px solid var(--border-light);border-radius:14px;background:var(--surface,#fff)}
.scr-catalog-title{display:flex;align-items:center;gap:.8rem;margin-bottom:.7rem}
.scr-catalog-image{height:56px;width:56px;object-fit:cover;border-radius:10px;flex-shrink:0}
.scr-catalog-image-empty{display:grid;place-items:center;background:rgba(212,160,23,.1)}
.scr-catalog-line{display:grid;grid-template-columns:minmax(140px,1.2fr) minmax(100px,1fr) minmax(100px,1fr);align-items:center;gap:.75rem;padding:.65rem .75rem;border-top:1px solid var(--border-light)}
.scr-catalog-line label{font-size:.8rem;font-weight:700;display:flex;align-items:center;gap:.55rem}
.scr-catalog-line input[type=number]{min-width:0;max-width:110px}
.scr-catalog-size{min-width:0}
.scr-catalog-size input{accent-color:#b68c2b;width:18px;height:18px}
.scr-catalog-line:has(.scr-catalog-check:checked){background:rgba(212,160,23,.05);border-radius:8px}
.scr-catalog-summary{display:flex;flex-wrap:wrap;gap:1rem;padding:1rem 0 0;font-size:.84rem}
.scr-catalog-summary strong{color:var(--gold-deep,#a77b16)}
.scr-errors{max-width:1000px;margin-bottom:1.25rem;padding:1rem 1.25rem;border:1px solid #dc3545;border-radius:12px;background:rgba(220,53,69,.12);color:#dc3545}
.scr-grid{display:grid;gap:1rem}.scr-grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}
.form-error{display:block;color:#dc3545;margin-top:.35rem;font-size:.82rem}
@media(max-width:680px){.scr-grid-2{grid-template-columns:1fr}.scr-catalog-line{grid-template-columns:1fr 1fr}.scr-catalog-size{grid-column:1/-1}}
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('scrForm');
    const rows = Array.from(document.querySelectorAll('[data-cake-line]'));
    const error = form.querySelector('[data-order-error]');
    const search = document.getElementById('cakeCatalogSearch');
    function updateSummary() {
        let count=0, showroom=0, reserved=0;
        rows.forEach(row => {
            if (!row.querySelector('.scr-catalog-check').checked) return;
            count++;
            showroom += Number(row.querySelector('.scr-qty-showroom').value)||0;
            reserved += Number(row.querySelector('.scr-qty-reserved').value)||0;
        });
        document.getElementById('cakeChosenCount').textContent=count;
        document.getElementById('cakeShowroomCount').textContent=showroom;
        document.getElementById('cakeReservedCount').textContent=reserved;
    }
    rows.forEach(row => {
        row.addEventListener('input', () => {
            if (Number(row.querySelector('.scr-qty-showroom').value)>0 || Number(row.querySelector('.scr-qty-reserved').value)>0) row.querySelector('.scr-catalog-check').checked=true;
            updateSummary();
        });
        row.addEventListener('change',updateSummary);
    });
    search.addEventListener('input',()=>{
        const q=search.value.trim().toLocaleLowerCase();
        document.querySelectorAll('[data-catalog-product]').forEach(product => {
            product.hidden=!product.dataset.name.includes(q);
        });
    });
    form.addEventListener('submit',event=>{
        form.querySelectorAll('[data-generated-cake-item]').forEach(node=>node.remove());
        let index=0;
        const append=(name,value)=>{
            const input=document.createElement('input');
            input.type='hidden';input.name='items['+index+']['+name+']';input.value=value;
            input.setAttribute('data-generated-cake-item','');form.appendChild(input);
        };
        for(const row of rows){
            if(!row.querySelector('.scr-catalog-check').checked) continue;
            const showroom=Number(row.querySelector('.scr-qty-showroom').value);
            const reserved=Number(row.querySelector('.scr-qty-reserved').value);
            if(!Number.isSafeInteger(showroom)||!Number.isSafeInteger(reserved)||showroom<0||reserved<0||showroom+reserved<1||showroom+reserved>99999){
                event.preventDefault();error.textContent='أدخل كميات صحيحة للأصناف المختارة، على الأقل قطعة واحدة لكل حجم.';error.hidden=false;error.focus();return;
            }
            append('product_id',row.dataset.productId);
            if(row.dataset.variantId) append('product_variant_id',row.dataset.variantId);
            append('cake_type',row.dataset.cakeType);
            append('cake_size',row.dataset.cakeSize);
            append('quantity',showroom+reserved);
            append('reserved_quantity',reserved);
            const layer = row.querySelector('.scr-layer-count');
            if (layer && layer.value !== '') append('layer_count', layer.value);
            index++;
        }
        if(!index){event.preventDefault();error.textContent='اختر منتج كيك واحدًا على الأقل وحدد الكمية المطلوبة.';error.hidden=false;error.focus();return;}
        error.hidden=true;
    });
    updateSummary();
});
</script>
@endsection


@push('scripts')
<script src="{{ asset('assets/js/daily-order-form.js') }}"></script>
@endpush
