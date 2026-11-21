@extends('layouts.app')
@section('title', 'طلب مخزون جديد')
@section('content')
<div class="page-header">
    <h1 class="page-heading">طلب مخزون جديد</h1>
    <p style="color:var(--text-muted)">اطلب مواد المخزون من فرع توريد إلى فرع مستلم. لا يتطلب هذا الإجراء مصنعاً.</p>
</div>

@if($errors->any())
    <div class="alert alert-danger" role="alert">
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

@if($branches->count() < 2)
    <div class="alert alert-warning" role="alert" style="margin-bottom:1rem">
        يلزم وجود فرعين نشطين على الأقل لإجراء طلب نقل داخلي. إذا كان المطعم فرعاً واحداً، استخدم أوامر الشراء واستلام البضاعة من المورد بدلاً من تحويل بين الفروع.
    </div>
@endif

<form action="{{ route('stock-requests.store') }}" method="POST">
    @csrf
    <div class="card" style="max-width:900px">
        <div class="card-header"><span class="card-title">الأطراف</span></div>
        <div class="card-body">
            @if(auth()->user()->isAdmin())
                <div class="form-group">
                    <label class="form-label" for="branch_id">الفرع المستلم</label>
                    <select name="branch_id" id="branch_id" class="form-select" required>
                        <option value="">اختر الفرع المستلم</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <div class="form-group">
                    <label class="form-label">الفرع المستلم</label>
                    <div class="form-input">{{ $destination?->name ?? 'لم يتم تعيين فرع لهذا المستخدم' }}</div>
                </div>
            @endif
            <div class="form-group">
                <label class="form-label" for="source_location_id">فرع التوريد</label>
                <select name="source_location_id" id="source_location_id" class="form-select" required>
                    <option value="">اختر فرع التوريد</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(old('source_location_id') == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
                <small style="color:var(--text-muted)">يجب أن يكون فرع التوريد مختلفاً عن الفرع المستلم، ويُراجع الطلب في فرع التوريد قبل إنشاء التحويل.</small>
            </div>
        </div>
    </div>

    <div class="card mt-4" style="max-width:900px">
        <div class="card-header"><span class="card-title">المواد المطلوبة</span></div>
        <div class="card-body">
            @if($products->isEmpty())
                <div class="alert alert-warning">لا توجد مواد خام نشطة. أضف المواد الخام أولاً من قسم المنتجات.</div>
            @endif
            <div id="items-container">
                <div class="item-row" style="display:grid;grid-template-columns:minmax(0,1fr) minmax(105px,150px) 40px;gap:.75rem;margin-bottom:.75rem;align-items:center">
                    <select name="items[0][product_id]" class="form-select" required>
                        <option value="">اختر مادة خام</option>
                        @foreach($products as $p)
                            <option value="{{ $p->id }}" @selected(old('items.0.product_id') == $p->id)>{{ $p->name_ar ?? $p->name }} ({{ $p->sku }})</option>
                        @endforeach
                    </select>
                    <input type="number" name="items[0][quantity]" class="form-input" value="{{ old('items.0.quantity') }}" placeholder="الكمية" min="0.001" step="0.001" required>
                    <span></span>
                </div>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" id="addItem">+ إضافة مادة</button>
        </div>
    </div>

    <div class="card mt-4" style="max-width:900px">
        <div class="card-body">
            <div class="form-group">
                <label class="form-label" for="stock-request-notes">ملاحظات</label>
                <textarea id="stock-request-notes" name="notes" class="form-textarea" maxlength="500">{{ old('notes') }}</textarea>
            </div>
            <div style="margin-top:1rem;display:flex;gap:.75rem;flex-wrap:wrap">
                <button class="btn btn-gold" type="submit" @disabled($branches->count() < 2 || $products->isEmpty() || (!auth()->user()->isAdmin() && (!$destination || !$destination->is_active)))>إرسال الطلب إلى فرع التوريد</button>
                <a href="{{ route('stock-requests.index') }}" class="btn btn-ghost">إلغاء</a>
            </div>
        </div>
    </div>
</form>

<template id="stock-request-row-template">
    <div class="item-row" style="display:grid;grid-template-columns:minmax(0,1fr) minmax(105px,150px) 40px;gap:.75rem;margin-bottom:.75rem;align-items:center">
        <select data-field="product" class="form-select" required>
            <option value="">اختر مادة خام</option>
            @foreach($products as $p)
                <option value="{{ $p->id }}">{{ $p->name_ar ?? $p->name }} ({{ $p->sku }})</option>
            @endforeach
        </select>
        <input data-field="quantity" type="number" class="form-input" placeholder="الكمية" min="0.001" step="0.001" required>
        <button type="button" class="remove-item" aria-label="حذف المادة" style="background:none;border:none;color:var(--error);font-size:1.25rem;cursor:pointer">×</button>
    </div>
</template>
<script>
(() => {
    let index = 1;
    const items = document.getElementById('items-container');
    const template = document.getElementById('stock-request-row-template');
    document.getElementById('addItem').addEventListener('click', () => {
        const row = template.content.firstElementChild.cloneNode(true);
        row.querySelector('[data-field="product"]').name = `items[${index}][product_id]`;
        row.querySelector('[data-field="quantity"]').name = `items[${index}][quantity]`;
        row.querySelector('.remove-item').addEventListener('click', () => row.remove());
        items.appendChild(row);
        index++;
    });
    const receiving = document.getElementById('branch_id');
    const source = document.getElementById('source_location_id');
    const fixedDestination = @json($destination?->id);
    const refreshSource = () => {
        const chosen = receiving ? receiving.value : String(fixedDestination ?? '');
        for (const option of source.options) {
            option.disabled = chosen !== '' && option.value === chosen;
        }
        if (source.selectedOptions[0]?.disabled) source.value = '';
    };
    receiving?.addEventListener('change', refreshSource);
    refreshSource();
})();
</script>
@endsection
