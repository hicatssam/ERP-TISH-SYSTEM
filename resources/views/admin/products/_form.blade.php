@php
    $currencySymbol = app(\App\Services\PrintThemeService::class)->settings()['currency_symbol'];
    $editing = isset($product) && $product !== null;

    $selectedUnitId = old(
        'unit_id',
        $editing
            ? (
                $product->unit_id
                ?: optional($units->firstWhere('code', $product->unit))->id
            )
            : optional($units->firstWhere('code', 'piece'))->id
    );

    $selectedLocationIds = collect(
        old(
            'location_ids',
            $editing
                ? $product->locationProducts
                    ->where('is_available', true)
                    ->pluck('location_id')
                    ->all()
                : []
        )
    )
        ->map(fn ($id) => (string) $id)
        ->unique()
        ->values()
        ->all();
@endphp

@push('styles')
<style>
    .product-form-shell {
        display: grid;
        gap: 1rem;
    }

    .product-form-section {
        display: grid;
        gap: 1rem;
        padding: 1rem;
        border: 1px solid var(--border);
        border-radius: 12px;
        background: var(--surface);
    }

    .product-form-section-title {
        padding-bottom: .6rem;
        border-bottom: 1px solid var(--border);
        font-size: .82rem;
        font-weight: 900;
        color: var(--text);
    }

    .product-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }

    .product-form-grid.three {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .product-location-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: .7rem;
    }

    .product-location-option {
        display: flex;
        align-items: center;
        gap: .65rem;
        padding: .9rem;
        border: 1px solid var(--border);
        border-radius: 10px;
        background: var(--card-bg, #fff);
        cursor: pointer;
    }

    .product-location-option span {
        display: grid;
        gap: .15rem;
    }

    .product-form-help {
        display: block;
        margin-top: .35rem;
        color: var(--text-muted);
        font-size: .72rem;
    }

    .product-auto-code {
        background: var(--off-white);
    }

    @media (max-width: 760px) {
        .product-form-grid,
        .product-form-grid.three {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@if($errors->any())
    <div data-product-errors role="alert" tabindex="-1" style="margin-bottom:1rem;padding:1rem;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;color:#991b1b">
        <strong>تعذر حفظ المنتج:</strong>
        <ul style="margin:.5rem 0 0;padding-inline-start:1.2rem">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="product-form-shell">

    <section class="product-form-section">
        <div class="product-form-section-title">البيانات الأساسية</div>

        <div class="product-form-grid">
            <div class="form-group">
                <label class="form-label">الاسم (إنجليزي) *</label>
                <input
                    name="name"
                    class="form-input"
                    value="{{ old('name', $product->name ?? '') }}"
                    maxlength="150"
                    required
                >
            </div>

            <div class="form-group">
                <label class="form-label">الاسم (عربي)</label>
                <input
                    name="name_ar"
                    class="form-input"
                    value="{{ old('name_ar', $product->name_ar ?? '') }}"
                    maxlength="150"
                >
            </div>
        </div>
    </section>

    <section class="product-form-section">
        <div class="product-form-section-title">التصنيف والبيع</div>

        <div class="product-form-grid">
            <div class="form-group">
                <label class="form-label">الفئة *</label>
                <select name="category_id" class="form-select" required>
                    <option value="">اختر فئة</option>
                    @foreach($categories as $cat)
                        <option
                            value="{{ $cat->id }}"
                            {{ (string) old('category_id', $product->category_id ?? '') === (string) $cat->id ? 'selected' : '' }}
                        >
                            {{ $cat->name_ar ?? $cat->name }}
                        </option>
                    @endforeach
                </select>

                @if($categories->isEmpty())
                    <small class="product-form-help" style="color:#b42318">
                        لا توجد فئات فعالة. أضف فئة أولًا.
                    </small>
                @endif
            </div>

            <div class="form-group">
                <label class="form-label">وحدة القياس *</label>
                <select name="unit_id" class="form-select" required>
                    <option value="">اختر الوحدة</option>
                    @foreach($units as $unit)
                        <option
                            value="{{ $unit->id }}"
                            {{ (string) $selectedUnitId === (string) $unit->id ? 'selected' : '' }}
                        >
                            {{ $unit->displayName() }}
                            @if($unit->symbol)
                                ({{ $unit->symbol }})
                            @endif
                        </option>
                    @endforeach
                </select>

                @if($units->isEmpty())
                    <small class="product-form-help" style="color:#b42318">
                        لا توجد وحدات قياس فعالة.
                    </small>
                @endif
            </div>

            <fieldset class="form-group" style="border:0;padding:0;margin:0">
                <legend class="form-label">نوع المنتج *</legend>
                <div style="display:flex;gap:.75rem;flex-wrap:wrap">
                    @foreach(\App\Enums\ProductType::cases() as $type)
                        <label class="product-location-option">
                            <input
                                type="radio"
                                name="product_type"
                                value="{{ $type->value }}"
                                {{ (string) old('product_type', $product->product_type?->value ?? 'standard') === $type->value ? 'checked' : '' }}
                                required
                            >
                            <strong>{{ $type->label() }}</strong>
                        </label>
                    @endforeach
                </div>
                <small class="product-form-help">
                    للمنتج بمتغيرات: احفظ المنتج أولًا، ثم أضف متغيراته بأسعار وباركود مستقلين.
                </small>
                @error('product_type')<small class="product-form-help" style="color:#b42318">{{ $message }}</small>@enderror
            </fieldset>
        </div>

        <div class="product-form-grid">
            <div class="form-group">
                <label class="form-label">السعر الأساسي ({{ $currencySymbol }}) *</label>
                <input
                    name="base_selling_price"
                    type="number"
                    min="0"
                    step="0.01"
                    class="form-input"
                    value="{{ old('base_selling_price', $product->base_selling_price ?? '') }}"
                    required
                >
            </div>

        </div>
    </section>

    <section class="product-form-section">
        <div class="product-form-section-title">الترميز التلقائي</div>

        <div class="product-form-grid">
            <div class="form-group">
                <label class="form-label">SKU</label>
                <input
                    class="form-input product-auto-code"
                    value="{{ $editing ? $product->sku : 'سيُنشأ تلقائيًا عند الحفظ' }}"
                    disabled
                    dir="ltr"
                >
            </div>

            <div class="form-group">
                <label class="form-label">الباركود EAN-13</label>
                <input
                    class="form-input product-auto-code"
                    value="{{ $editing ? $product->barcode : 'سيُنشأ تلقائيًا عند الحفظ' }}"
                    disabled
                    dir="ltr"
                >
            </div>
        </div>
    </section>

    <section class="product-form-section">
        <div class="product-form-section-title">أماكن توفر المنتج</div>

        <div class="form-group">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;margin-bottom:.75rem">
                <label class="form-label" style="margin:0">الفروع / المصنع *</label>

                @if($canManageLocations && $locations->isNotEmpty())
                    <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                        <button
                            type="button"
                            class="btn btn-ghost btn-sm"
                            onclick="toggleProductLocations(true)"
                        >
                            تحديد الكل
                        </button>

                        <button
                            type="button"
                            class="btn btn-ghost btn-sm"
                            onclick="toggleProductLocations(false)"
                        >
                            إلغاء الكل
                        </button>
                    </div>
                @endif
            </div>

            @if($canManageLocations)
                @if($locations->isNotEmpty())
                    <div class="product-location-grid">
                        @foreach($locations as $location)
                            <label class="product-location-option">
                                <input
                                    type="checkbox"
                                    name="location_ids[]"
                                    value="{{ $location->id }}"
                                    {{ in_array((string) $location->id, $selectedLocationIds, true) ? 'checked' : '' }}
                                >

                                <span>
                                    <strong>{{ $location->name_ar ?: $location->name }}</strong>
                                    <small style="color:var(--text-muted)">
                                        {{ $location->type?->label() ?? 'موقع' }}
                                    </small>
                                </span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <div class="alert alert-danger">
                        لا توجد فروع أو مصانع فعالة لربط المنتج بها.
                    </div>
                @endif
            @else
                <input
                    class="form-input"
                    value="{{ $managedLocation?->name_ar ?: ($managedLocation?->name ?? '—') }}"
                    disabled
                >

                @if($managedLocation)
                    <input
                        type="hidden"
                        name="location_ids[]"
                        value="{{ $managedLocation->id }}"
                    >
                @endif
            @endif
        </div>
    </section>

    <section class="product-form-section">
        <div class="product-form-section-title">التتبع والتشغيل</div>

        <div style="display:flex;gap:1.25rem;flex-wrap:wrap;padding:.8rem 1rem;background:var(--card-bg,#fff);border:1px solid var(--border);border-radius:10px">
            <label style="display:flex;gap:.5rem;align-items:center">
                <input
                    type="checkbox"
                    name="tracks_batch"
                    value="1"
                    {{ old('tracks_batch', $product->tracks_batch ?? false) ? 'checked' : '' }}
                >
                تتبع الدُفعات
            </label>

            <label style="display:flex;gap:.5rem;align-items:center">
                <input
                    type="checkbox"
                    name="tracks_expiry"
                    value="1"
                    {{ old('tracks_expiry', $product->tracks_expiry ?? false) ? 'checked' : '' }}
                >
                تتبع تاريخ الصلاحية
            </label>
        </div>

        <small class="product-form-help">
            تفعيل تتبع الصلاحية يفعّل تتبع الدُفعات تلقائيًا.
        </small>
    </section>

    <section class="product-form-section">
        <div class="product-form-section-title">تفاصيل إضافية</div>

        <div class="form-group">
            <label class="form-label">الوصف</label>
            <textarea
                name="description"
                class="form-textarea"
                rows="3"
            >{{ old('description', $product->description ?? '') }}</textarea>
        </div>

        <div class="product-form-grid">
            <div class="form-group">
                <label class="form-label">صورة المنتج</label>
                <input
                    type="file"
                    name="image"
                    data-product-image
                    aria-describedby="product-image-help product-image-error"
                    aria-invalid="{{ $errors->has('image') ? 'true' : 'false' }}"
                    class="form-input"
                    accept="image/jpeg,image/png,image/webp"
                >
                <small id="product-image-help" class="product-form-help">JPEG / PNG / WebP — حتى 2MB. إذا تعذر الحفظ أعد اختيار الصورة.</small>
                <div id="product-image-error" data-product-image-error role="alert"
                     style="color:#b42318;margin-top:.5rem"
                     @if(!$errors->has('image')) hidden @endif>{{ $errors->first('image') }}</div>

                @if($editing && $product->image)
                    <label style="display:flex;gap:.5rem;align-items:center;margin-top:.5rem">
                        <input type="checkbox" name="remove_image" value="1">
                        حذف الصورة الحالية
                    </label>
                @endif
            </div>

            <div class="form-group">
                <label class="form-label">وقت التحضير المتوقع (دقائق)</label>
                <input
                    type="number"
                    name="prep_time_minutes"
                    min="0"
                    max="600"
                    class="form-input"
                    value="{{ old('prep_time_minutes', $product->prep_time_minutes ?? '') }}"
                    placeholder="مثال: 10"
                >
                <small class="product-form-help">
                    اتركه فارغًا لاستخدام القيمة الافتراضية للفرع.
                </small>
            </div>
        </div>
    </section>

</div>

@push('scripts')
<script>
function toggleProductLocations(checked) {
    const modal = document.getElementById('productCreateModal');
    const root = modal || document;

    root.querySelectorAll('input[name="location_ids[]"]').forEach(function(input) {
        input.checked = checked;
    });
}
</script>
@endpush

@push('scripts')
<script src="{{ asset('assets/js/product-form.js') }}?v=1"></script>
@endpush
