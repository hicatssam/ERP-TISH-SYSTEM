{{-- Optional raw material line items for daily branch orders. --}}
<section class="card" aria-labelledby="daily-materials-title">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:.75rem;flex-wrap:wrap">
        <div>
            <span class="card-title" id="daily-materials-title">طلب المواد الخام مع الطلب اليومي</span>
            <p class="form-help">اختياري — تتم مراجعة المواد الخام وصرفها بشكل منفصل عن تجهيز الحلويات.</p>
        </div>
        <button type="button" class="btn btn-outline btn-sm" id="addRawMaterial" @disabled($rawMaterials->isEmpty())>+ إضافة مادة خام</button>
    </div>
    <div class="card-body">
        @if($rawMaterials->isEmpty())
            <p class="form-help">لا توجد مواد خام مفعّلة. فعّل «مادة خام للمخزون» في إعدادات المنتج أولاً.</p>
        @endif
        <div id="rawMaterialRows" style="display:grid;gap:.75rem"></div>
        <p id="rawMaterialEmpty" class="form-help">يمكن إرسال الطلب دون مواد خام.</p>
        @error('raw_materials')<small class="form-error">{{ $message }}</small>@enderror
    </div>
</section>
<template id="rawMaterialTemplate">
    <div class="raw-material-line" style="display:grid;grid-template-columns:minmax(0,2fr) minmax(100px,1fr) minmax(0,1fr) auto;gap:.6rem;padding:.8rem;border:1px solid var(--border);border-radius:10px">
        <label><span class="form-label">المادة الخام *</span>
            <select name="raw_materials[__N__][product_id]" class="form-select raw-product" required>
                <option value="">اختر مادة</option>
                @foreach($rawMaterials as $material)
                    <option value="{{ $material->id }}">{{ $material->name_ar ?: $material->name }} ({{ $material->unitDefinition?->symbol ?: $material->unit }})</option>
                @endforeach
            </select>
        </label>
        <label><span class="form-label">الكمية *</span>
            <input type="number" name="raw_materials[__N__][quantity]" min=".001" step=".001" class="form-input raw-quantity" required>
        </label>
        <label><span class="form-label">ملاحظة</span>
            <input type="text" name="raw_materials[__N__][note]" maxlength="500" class="form-input raw-note">
        </label>
        <button type="button" class="btn btn-ghost btn-sm raw-remove">حذف</button>
    </div>
</template>
<style>@media(max-width:700px){.raw-material-line{grid-template-columns:1fr !important}}</style>
<script>
(() => {
    const host = document.getElementById('rawMaterialRows');
    const button = document.getElementById('addRawMaterial');
    const empty = document.getElementById('rawMaterialEmpty');
    const tpl = document.getElementById('rawMaterialTemplate');
    const saved = {{ \Illuminate\Support\Js::from(old('raw_materials', [])) }};
    let next = 0;
    function refresh() {
        empty.hidden = host.children.length !== 0;
        button.disabled = host.children.length >= 30 || @json($rawMaterials->isEmpty());
    }
    function add(item = {}) {
        const content = tpl.content.cloneNode(true);
        const row = content.querySelector('.raw-material-line');
        row.querySelectorAll('[name]').forEach(input => input.name = input.name.replace('__N__', String(next)));
        next++;
        row.querySelector('.raw-product').value = item.product_id || '';
        row.querySelector('.raw-quantity').value = item.quantity || '';
        row.querySelector('.raw-note').value = item.note || '';
        row.querySelector('.raw-remove').addEventListener('click', () => {row.remove();refresh()});
        host.appendChild(content);
        refresh();
    }
    button.addEventListener('click', () => add());
    (Array.isArray(saved) ? saved : Object.values(saved || {})).forEach(add);
    refresh();
})();
</script>
