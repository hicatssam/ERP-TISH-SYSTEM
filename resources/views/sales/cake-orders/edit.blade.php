@extends('layouts.app')
@section('title', 'تعديل طلب الكيك')

@section('content')
@php
    $orderNumber = $cakeOrder->order_number ?? $cakeOrder->id;
@endphp

<div class="page-header cake-page-header">
    <div>
        <h1 class="page-heading">تعديل طلب الكيك</h1>
        <p class="page-subheading">
            <a href="{{ route('cake-orders.index') }}">طلبات الكيك</a>
            <span>‹</span>
            <a href="{{ route('cake-orders.show', $cakeOrder) }}">#{{ $orderNumber }}</a>
            <span>‹ تعديل</span>
        </p>
    </div>
    <a href="{{ route('cake-orders.show', $cakeOrder) }}" class="btn btn-ghost btn-sm">العودة للطلب</a>
</div>

@if($errors->any())
    <div class="cake-alert cake-alert-danger">
        <strong>تعذر حفظ التعديلات:</strong>
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
@if(session('error'))<div class="cake-alert cake-alert-danger">{{ session('error') }}</div>@endif
@if(session('success'))<div class="cake-alert cake-alert-success">{{ session('success') }}</div>@endif

<form action="{{ route('cake-orders.update', $cakeOrder) }}" method="POST" id="cakeEditForm">
    @csrf
    @method('PUT')

    <div class="cake-form-wrap">
        <section class="card">
            <div class="card-header"><span class="card-title">بيانات العميل والتسليم</span></div>
            <div class="card-body cake-grid cake-grid-3">
                <div class="form-group">
                    <label class="form-label">العميل</label>
                    <input class="form-input" value="{{ $cakeOrder->customer?->name }} — {{ $cakeOrder->customer?->phone }}" disabled>
                    <small class="form-help">لا يمكن تغيير العميل بعد إنشاء الطلب.</small>
                </div>
                <div class="form-group">
                    <label class="form-label">تاريخ التسليم *</label>
                    <input type="date"
                           id="requiredDate"
                           name="required_date"
                           min="{{ old('is_urgent', $cakeOrder->is_urgent) ? today()->toDateString() : today()->addDay()->toDateString() }}"
                           class="form-input @error('required_date') is-invalid @enderror"
                           value="{{ old('required_date', $cakeOrder->required_date?->format('Y-m-d')) }}"
                           required>
                    @error('required_date')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">وقت التسليم</label>
                    <input type="time" name="required_time" class="form-input @error('required_time') is-invalid @enderror"
                           value="{{ old('required_time', $cakeOrder->required_time ? \Carbon\Carbon::parse($cakeOrder->required_time)->format('H:i') : '') }}">
                    @error('required_time')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group urgent-booking" style="grid-column:1/-1">
                    <label class="urgent-toggle" for="isUrgent">
                        <input
                            type="checkbox"
                            id="isUrgent"
                            name="is_urgent"
                            value="1"
                            @checked(old('is_urgent', $cakeOrder->is_urgent))
                        >
                        <span class="urgent-toggle-icon">!</span>
                        <span>
                            <strong>طلب طارئ / تسليم اليوم</strong>
                            <small>
                                بدون هذا الخيار لا يسمح النظام بموعد تسليم اليوم.
                            </small>
                        </span>
                    </label>

                    <div
                        id="urgentReasonGroup"
                        class="urgent-reason"
                        @if(! old('is_urgent', $cakeOrder->is_urgent)) hidden @endif
                    >
                        <label class="form-label" for="urgentReason">
                            سبب الطلب الطارئ *
                        </label>
                        <textarea
                            id="urgentReason"
                            name="urgent_reason"
                            class="form-textarea @error('urgent_reason') is-invalid @enderror"
                            rows="2"
                        >{{ old('urgent_reason', $cakeOrder->urgent_reason) }}</textarea>
                        @error('urgent_reason')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><span class="card-title">مواصفات الكيك</span></div>
            <div class="card-body">
                <div class="cake-grid cake-grid-3">
                    <div class="form-group">
                        <label class="form-label">نوع الكيك</label>
                        <select name="cake_type" class="form-select @error('cake_type') is-invalid @enderror">
                            <option value="">اختر النوع</option>
                            @foreach(['chocolate'=>'شوكولاتة','vanilla'=>'فانيلا','red_velvet'=>'ريد فيلفيت','caramel'=>'كراميل','fruit'=>'فاكهة','other'=>'أخرى'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('cake_type', $cakeOrder->cake_type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('cake_type')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group" id="sizeGroup">
                        <label class="form-label">الأحجام (يمكن اختيار أكثر من حجم)</label>
                        <input type="hidden" name="cake_sizes_submitted" value="1">
                        <input type="hidden" name="cake_size" id="cakeSize" value="{{ old('cake_size', $cakeOrder->cake_size) }}">
                        <div id="cakeSizeChoices" class="cake-size-choices" role="group" aria-label="الأحجام المطلوبة"></div>
                        <small class="form-help">حدد حجمًا أو أكثر. الاختيارات تتغير حسب شكل الكيك.</small>
                        @error('cake_sizes')<span class="form-error">{{ $message }}</span>@enderror
                        @error('cake_sizes.*')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">الوزن (كغم)</label>
                        <input type="number" min="0" step="0.1" name="cake_weight" class="form-input @error('cake_weight') is-invalid @enderror" value="{{ old('cake_weight', $cakeOrder->cake_weight) }}">
                        @error('cake_weight')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">عدد الأشخاص</label>
                        <input type="number" min="1" name="persons_count" class="form-input @error('persons_count') is-invalid @enderror" value="{{ old('persons_count', $cakeOrder->persons_count) }}">
                        @error('persons_count')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">النكهة</label>
                        <input name="flavor" class="form-input @error('flavor') is-invalid @enderror" value="{{ old('flavor', $cakeOrder->flavor) }}">
                        @error('flavor')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">الحشو</label>
                        <input name="filling" class="form-input @error('filling') is-invalid @enderror" value="{{ old('filling', $cakeOrder->filling) }}">
                        @error('filling')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">الشكل</label>
                        <select name="shape" id="cakeShape" class="form-select @error('shape') is-invalid @enderror">
                            <option value="">اختر الشكل</option>
                            @foreach(['round'=>'دائري','slab'=>'بلاطة','wedding_tiers'=>'طوابق أفراح','standard_tiers'=>'طوابق ستاندر'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('shape', $cakeOrder->shape) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('shape')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group" id="cakeLayersGroup" @if(old('shape', $cakeOrder->shape) !== 'wedding_tiers') hidden @endif>
                        <label class="form-label" for="cakeLayersCount">عدد الطبقات (طوابق أفراح)</label>
                        <select name="layers_count" id="cakeLayersCount" class="form-select @error('layers_count') is-invalid @enderror">
                            <option value="">اختر عدد الطبقات</option>
                            @foreach([3, 4, 5, 6] as $tierCount)
                                <option value="{{ $tierCount }}" @selected((string) old('layers_count', $cakeOrder->layers_count) === (string) $tierCount)>{{ $tierCount }} طوابق</option>
                            @endforeach
                        </select>
                        @error('layers_count')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">اللون</label>
                        <input name="color" class="form-input @error('color') is-invalid @enderror" value="{{ old('color', $cakeOrder->color) }}">
                        @error('color')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">الثيم / الموضوع</label>
                        <input name="theme" class="form-input @error('theme') is-invalid @enderror" value="{{ old('theme', $cakeOrder->theme) }}">
                        @error('theme')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="form-group cake-space-top">
                    <label class="form-label">النص على الكيك</label>
                    <input name="cake_text" class="form-input @error('cake_text') is-invalid @enderror" value="{{ old('cake_text', $cakeOrder->cake_text) }}">
                    @error('cake_text')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group cake-space-top">
                    <label class="form-label">تعليمات خاصة</label>
                    <textarea name="special_instructions" class="form-textarea @error('special_instructions') is-invalid @enderror" rows="4">{{ old('special_instructions', $cakeOrder->special_instructions) }}</textarea>
                    @error('special_instructions')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><span class="card-title">السعر</span></div>
            <div class="card-body cake-grid cake-grid-2">
                <div class="form-group">
                    <label class="form-label">السعر الإجمالي (₪) *</label>
                    <input type="number" min="0" step="0.01" name="total_price" id="cakeTotalPrice" class="form-input @error('total_price') is-invalid @enderror"
                           value="{{ old('total_price', $cakeOrder->total_price) }}" required>
                    @error('total_price')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="price-summary">
                    <span>السعر بعد الخصم (معاينة)</span>
                    <strong id="cakeNetPreview">₪{{ number_format((float) $cakeOrder->net_price ?? (float) $cakeOrder->total_price, 2) }}</strong>
                    <small id="cakeDiscountPreview" class="form-help"></small>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><span class="card-title">سبب التعديل</span></div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="cakeEditType">نوع التعديل *</label>
                    <select id="cakeEditType" name="edit_type" class="form-select" required>
                        <option value="normal" @selected(old('edit_type', in_array($cakeOrder->status, [\App\Enums\CakeOrderStatus::InProgress], true) ? 'exceptional' : 'normal') === 'normal')>تعديل عادي</option>
                        <option value="exceptional" @selected(old('edit_type', in_array($cakeOrder->status, [\App\Enums\CakeOrderStatus::InProgress], true) ? 'exceptional' : 'normal') === 'exceptional')>تعديل استثنائي</option>
                    </select>
                    @error('edit_type')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="cakeEditReason">سبب التعديل *</label>
                    <textarea id="cakeEditReason" name="edit_reason" rows="3" minlength="5" maxlength="1000" class="form-textarea" required placeholder="اشرح السبب الذي استدعى تعديل الطلب">{{ old('edit_reason') }}</textarea>
                    @error('edit_reason')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <p class="form-help">بعد بدء الإنتاج يُشترط التعديل الاستثنائي، ويتم تسجيل السبب والتغييرات في سجل النشاط.</p>
            </div>
        </section>
        <div class="sticky-actions">
            <a href="{{ route('cake-orders.show', $cakeOrder) }}" class="btn btn-ghost">إلغاء</a>
            <button class="btn btn-gold" type="submit">حفظ التعديلات</button>
        </div>
    </div>
</form>

<style>
.cake-page-header{display:flex;align-items:center;justify-content:space-between;gap:1rem}.cake-form-wrap{display:grid;gap:1.5rem;max-width:1000px}.cake-grid{display:grid;gap:1rem}.cake-grid-3{grid-template-columns:repeat(3,minmax(0,1fr))}.cake-grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}.cake-space-top{margin-top:1rem}.form-help{display:block;margin-top:.35rem;color:var(--text-muted);font-size:.78rem}.form-error{display:block;margin-top:.35rem;color:#dc3545;font-size:.82rem}.is-invalid{border-color:#dc3545!important}.cake-alert{max-width:1000px;margin-bottom:1.25rem;padding:1rem 1.2rem;border-radius:12px}.cake-alert ul{margin:.6rem 0 0;padding-inline-start:1.2rem}.cake-alert-danger{color:#dc3545;background:rgba(220,53,69,.1);border:1px solid rgba(220,53,69,.45)}.cake-alert-success{color:#198754;background:rgba(25,135,84,.1);border:1px solid rgba(25,135,84,.4)}.price-summary{display:flex;align-items:center;justify-content:space-between;padding:1rem;color:var(--text-muted);background:rgba(212,175,55,.08);border:1px solid rgba(212,175,55,.3);border-radius:10px}.price-summary strong{color:var(--gold);font-size:1.15rem}.sticky-actions{position:sticky;bottom:0;z-index:20;display:flex;justify-content:flex-end;gap:.75rem;padding:1rem;background:color-mix(in srgb,var(--surface) 94%,transparent);border:1px solid var(--border);border-radius:12px;backdrop-filter:blur(10px)}
@media(max-width:800px){.cake-page-header{align-items:flex-start;flex-direction:column}.cake-grid-3,.cake-grid-2{grid-template-columns:1fr}.sticky-actions{position:static}}
.urgent-booking{padding:.85rem;border:1px solid #fed7aa;border-radius:12px;background:#fff7ed}.urgent-toggle{display:flex;align-items:center;gap:.75rem;cursor:pointer}.urgent-toggle input{width:18px;height:18px;accent-color:#ea580c}.urgent-toggle-icon{display:grid;place-items:center;width:34px;height:34px;flex:0 0 34px;border-radius:10px;color:#fff;background:#ea580c;font-weight:900}.urgent-toggle>span:last-child{display:grid;gap:.15rem}.urgent-toggle strong{color:#9a3412;font-size:.84rem}.urgent-toggle small{color:#9a3412;font-size:.72rem}.urgent-reason{margin-top:.8rem;padding-top:.8rem;border-top:1px dashed #fdba74}
.cake-size-choices{display:flex;flex-wrap:wrap;gap:.6rem;margin:.4rem 0}
.cake-size-option{display:inline-flex;align-items:center;gap:.45rem;padding:.55rem .85rem;border:1px solid var(--border);border-radius:10px;background:var(--surface);font-size:.84rem;cursor:pointer}
.cake-size-option input{accent-color:var(--gold);width:17px;height:17px}
</style>

@php
    $initialCakeSizes = old('cake_sizes', $cakeOrder->cake_sizes ?? array_values(array_filter([(string) $cakeOrder->cake_size])));
@endphp

<script>
document.addEventListener('DOMContentLoaded', () => {
    const urgentToggle = document.getElementById('isUrgent');
    const urgentReasonGroup = document.getElementById('urgentReasonGroup');
    const urgentReason = document.getElementById('urgentReason');
    const requiredDate = document.getElementById('requiredDate');
    const todayValue = @json(today()->toDateString());
    const tomorrowValue = @json(today()->addDay()->toDateString());
    const priceInput = document.getElementById('cakeTotalPrice');
    const netPreview = document.getElementById('cakeNetPreview');
    const discountPreview = document.getElementById('cakeDiscountPreview');
    const discountType = @json($cakeOrder->discount_type);
    const discountValue = Number(@json((float) $cakeOrder->discount_value));
    const financialDocumentsExist = @json($cakeOrder->payments()->exists() || $cakeOrder->invoice()->exists());
    const originalTotal = Number(@json((float) $cakeOrder->total_price));

    function updatePricePreview() {
        if (!priceInput || !netPreview) return;
        const total = Number(priceInput.value);
        if (!Number.isFinite(total) || total < 0) {
            netPreview.textContent = '—';
            return;
        }
        const discount = discountType === 'percentage'
            ? Math.round(total * discountValue) / 100
            : (discountType === 'fixed' ? discountValue : 0);
        const valid = discount <= total;
        netPreview.textContent = valid ? '₪' + (total - discount).toFixed(2) : 'الخصم أكبر من السعر';
        if (discountPreview) {
            discountPreview.textContent = financialDocumentsExist && Math.abs(total - originalTotal) >= 0.005
                ? 'لا يمكن تغيير السعر بعد تسجيل دفعة أو إصدار فاتورة.'
                : 'الخصم: ₪' + discount.toFixed(2);
        }
    }
    priceInput?.addEventListener('input', updatePricePreview);
    updatePricePreview();

    function syncUrgentDelivery() {
        const urgent = Boolean(urgentToggle?.checked);

        if (requiredDate) {
            requiredDate.min = urgent
                ? todayValue
                : tomorrowValue;
        }

        if (urgentReasonGroup) {
            urgentReasonGroup.hidden = !urgent;
        }

        if (urgentReason) {
            urgentReason.required = urgent;
        }
    }

    const cakeShape = document.getElementById('cakeShape');
    const sizeGroup = document.getElementById('sizeGroup');
    const sizeChoices = document.getElementById('cakeSizeChoices');
    const primarySize = document.getElementById('cakeSize');
    let selectedSizes = @json($initialCakeSizes);
    const sizesByShape = {
        round: {small:'صغير',medium:'وسط',large:'كبير'},
        slab: {'30x30':'30×30','40x30':'40×30','60x40':'60×40','120x30':'120×30','120x80':'120×80'},
        wedding_tiers: null,
        standard_tiers: {small:'صغير',medium:'وسط',large:'كبير'},
    };

    function syncSizes() {
        const picked = [...sizeChoices.querySelectorAll('input[type="checkbox"]:checked')];
        primarySize.value = picked[0]?.value ?? (cakeShape.value === 'wedding_tiers' ? 'standard' : '');
    }

    function renderSizes() {
        sizeChoices.replaceChildren();
        const options = sizesByShape[cakeShape.value] ?? null;
        sizeGroup.hidden = cakeShape.value === 'wedding_tiers';
        const previous = Array.isArray(selectedSizes) ? selectedSizes.map(String) : [];

        if (options) {
            Object.entries(options).forEach(([value,label]) => {
                const wrapper = document.createElement('label');
                wrapper.className = 'cake-size-option';
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.name = 'cake_sizes[]';
                checkbox.value = value;
                checkbox.checked = previous.includes(value);
                checkbox.addEventListener('change', syncSizes);
                wrapper.append(checkbox, document.createTextNode(' ' + label));
                sizeChoices.append(wrapper);
            });
        }

        syncSizes();
    }

    cakeShape.addEventListener('change', () => { selectedSizes = []; renderSizes(); });
    renderSizes();

    urgentToggle?.addEventListener('change', syncUrgentDelivery);
    syncUrgentDelivery();
});
document.getElementById('cakeShape')?.addEventListener('change', function () {
    const group = document.getElementById('cakeLayersGroup');
    const select = document.getElementById('cakeLayersCount');
    if (!group || !select) return;
    group.hidden = this.value !== 'wedding_tiers';
    select.required = this.value === 'wedding_tiers';
    if (group.hidden) select.value = '';
});
document.getElementById('cakeShape')?.dispatchEvent(new Event('change'));
</script>
@endsection