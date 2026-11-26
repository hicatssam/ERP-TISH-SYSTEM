


@php
    $menuCurrencySymbol = app(\App\Services\PrintThemeService::class)->settings()['currency_symbol'];
@endphp
<!doctype html>
<html lang="{{ \App\Support\CustomerMenuLanguage::current() }}" dir="{{ \App\Support\CustomerMenuLanguage::direction() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>إتمام الطلب - {{ $branding['name'] ?? config('app.name') }}</title>
@if(!empty($branding['favicon']))<link rel="icon" href="{{ $branding['favicon'] }}">@endif
@include('customer-menu.partials.styles')
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-checkout-paper.css') }}?v=5">
@include('customer-menu.partials.pwa-head')
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-drive-ui-fixes-v1.css') }}?v=1">
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-paper-ux-v2.css') }}?v=2">
<style>
/* Table is selected once per customer browser visit, with cashier correction later. */
.table-once-hint{margin:10px 0 0;padding:10px 13px;border-radius:11px;border:1px solid #efc6cd;background:#fff7f8;color:#5b1420;font-size:.86rem;line-height:1.7}
.table-card.table-once-locked:not(.selected){opacity:.43;pointer-events:none}
.table-card.selected.table-once-locked{outline:3px solid #c7102b;outline-offset:2px}
</style>


<style>
/* TISH-VEHICLE-EXACT-PAPER-V3 - PATH FIX */
body.tish-paper.tish-checkout-page #checkoutForm #vehicleField[hidden] {
    display: none !important;
}

body.tish-paper.tish-checkout-page #checkoutForm #vehicleField input {
    display: block !important;
    box-sizing: border-box !important;
    width: 100% !important;
    min-width: 0 !important;
    min-height: 60px !important;
    height: 60px !important;
    padding: 12px 17px 15px !important;
    margin: 0 !important;
    border: 0 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    appearance: none !important;

    background-color: transparent !important;
    background-image: url('/assets/tish-paper/paper/category-ivory.webp') !important;
    background-position: center !important;
    background-size: 100% 100% !important;
    background-repeat: no-repeat !important;

    color: #252525 !important;
    font-family: inherit !important;
    font-size: 16px !important;
    font-weight: 800 !important;
}

body.tish-paper.tish-checkout-page #checkoutForm #vehicleField input::placeholder {
    color: #71665d !important;
    opacity: 1 !important;
}

body.tish-paper.tish-checkout-page #checkoutForm #vehicleField input:focus {
    outline: 2px solid #a91f17 !important;
    outline-offset: 2px;
}
</style>


<style>
/* TISH-VEHICLE-NO-RED-BOX-V1 */
body.tish-paper.tish-checkout-page #checkoutForm #vehicleField input,
body.tish-paper.tish-checkout-page #checkoutForm #vehicleField input:focus,
body.tish-paper.tish-checkout-page #checkoutForm #vehicleField input:focus-visible,
body.tish-paper.tish-checkout-page #checkoutForm #vehicleField input:invalid {
    border: 0 !important;
    outline: none !important;
    box-shadow: none !important;
}
</style>

</head>
<body class="crisp-customer-menu tish-paper tish-checkout-page">
<div class="app crisp-menu-app">
@include('customer-menu.partials.topbar', ['pageTitle' => 'إتمام الطلب', 'pageSubtitle' => $location->name, 'backUrl' => route('customer-menu.cart', $location->code)])

<main class="menu-area">
 <div class="shell">
  <form id="checkoutForm" enctype="multipart/form-data">
   <input type="hidden" name="qr_token" value="{{ $tableQrToken ?? '' }}">
   <section class="checkout-sheet checkout-details" aria-labelledby="checkoutDetailsTitle">
   <h2 id="checkoutDetailsTitle">بيانات الطلب</h2>
   <div class="checkout-grid">
    <label class="full" id="checkoutServiceFallback">نوع الطلب
     <select name="service_type" id="serviceType" required>
      @foreach(($serviceOptions ?? []) as $option)
       <option value="{{ $option['value'] ?? $option }}">{{ $option['label'] ?? $option }}</option>
      @endforeach
     </select>
    </label>
    <div class="full checkout-service-picker" id="checkoutServicePicker" role="group" aria-labelledby="checkoutServiceTitle" hidden>
     <span id="checkoutServiceTitle">نوع الطلب</span>
     <div class="checkout-service-options">
      @foreach(($serviceOptions ?? []) as $option)
       @php
           $serviceValue = $option['value'] ?? $option;
       @endphp
       <button class="checkout-paper-choice" type="button" data-service-choice="{{ $serviceValue }}" aria-pressed="false">
        <i class="fa-solid {{ match($serviceValue) { 'dine_in' => 'fa-utensils', 'delivery' => 'fa-motorcycle', 'outdoor' => 'fa-car-side', default => 'fa-bag-shopping' } }}" aria-hidden="true"></i>
        <span>{{ $option['label'] ?? $option }}</span>
       </button>
      @endforeach
     </div>
    </div>
    <label>الاسم<input name="name" autocomplete="name" required maxlength="120"></label>
    <label>رقم الجوال<input name="phone" type="tel" autocomplete="tel" required maxlength="20" inputmode="tel" dir="ltr"></label>
    <div id="tableField" class="full" role="group" aria-labelledby="checkoutTableTitle" hidden>
     {{-- TISH-QR-CHECKOUT-LOCK-V2 --}}
      <span id="checkoutTableTitle">
       @if($selectedTable ?? null)
        طاولتك: {{ $selectedTable['name'] }}
       @else
        الطلب من الطاولة يتطلب مسح QR
       @endif
      </span>
     <div class="table-legend">
      <span><span class="dot" style="background:#22a35c"></span> متاحة</span>
      <span><span class="dot" style="background:#d64545"></span> مشغولة</span>
     </div>
     <div class="table-grid" id="tableGrid" style="display:none!important">
      @forelse(($tables ?? []) as $table)
       @php
           $isAvailable = (bool) ($table['available'] ?? true);
       @endphp
       <button type="button"
               class="table-card {{ $isAvailable ? 'available' : 'occupied' }}"
               data-table-id="{{ $table['id'] }}" aria-pressed="false"
               {{ $isAvailable ? '' : 'disabled' }}>
        <span class="dot {{ $isAvailable ? 'available' : 'occupied' }}"></span>
        {{ $table['name'] ?? ('طاولة '.$table['id']) }}
        <small>{{ $isAvailable ? 'متاحة' : 'مشغولة' }}</small>
       </button>
      @empty
       <div class="empty" style="padding:18px">لا توجد طاولات مفعّلة لهذا الفرع.</div>
      @endforelse
     </div>
     <input type="hidden" name="restaurant_table_id" id="tableIdInput">
     <p id="tableOneTimeHint" class="table-once-hint" role="status" hidden></p>
    </div>
    <label class="full" id="addressField" hidden>عنوان التوصيل<textarea name="address" autocomplete="street-address" maxlength="500"></textarea></label>
    <div class="full" id="vehicleField" hidden>
     <div class="checkout-grid">
      <label>نوع السيارة
       <input name="car_type" maxlength="80" placeholder="مثال: Toyota Corolla">
      </label>
      <label>لون السيارة
       <input name="car_color" maxlength="80" placeholder="مثال: أسود">
      </label>
      <label class="full">مكان الوقوف
       <input name="parking_spot" maxlength="120" placeholder="مثال: موقف 3">
      </label>
     </div>
    </div>
    <label class="full">ملاحظات الطلب<textarea name="notes" maxlength="700"></textarea></label>
   </div>

   </section>

   <section class="checkout-sheet checkout-payment" aria-labelledby="checkoutPaymentTitle">
   <h2 id="checkoutPaymentTitle">طريقة الدفع</h2>
   <div class="payment-methods" id="paymentMethods">جاري تحميل طرق الدفع...</div>
   <div id="paymentAccountDetails"></div>
   <input type="hidden" name="payment_method_id" id="paymentMethodId">
   <input type="hidden" name="payment_account_id" id="paymentAccountId">

   <div id="paymentReferenceField" hidden>
    <label>رقم مرجع العملية (اختياري)
     <input type="text" name="payment_reference" id="paymentReferenceInput" maxlength="150" placeholder="مثال: TX123456">
    </label>
   </div>

   <div id="paymentProofField" hidden>
    <label class="upload-field" for="paymentProofInput">
     <i class="fa-solid fa-cloud-arrow-up"></i>
     <span>إرفاق إشعار إثبات الدفع (صورة أو PDF) <span class="required-star" id="proofRequiredStar" hidden>*</span></span>
     <input type="file" id="paymentProofInput" name="payment_proof" accept=".jpg,.jpeg,.png,.webp,.pdf">
    </label>
    <div class="upload-preview" id="uploadPreview" hidden></div>
   </div>

   </section>
   <section class="checkout-sheet checkout-receipt" aria-labelledby="checkoutSummaryTitle">
  <div class="eta-banner" id="etaBanner" hidden>
   <i class="fa-solid fa-clock"></i>
   <span id="etaText"></span>
  </div>

  <div class="order-summary">
   <h2 id="checkoutSummaryTitle">ملخص الطلب</h2>
   <a class="checkout-edit-cart" href="{{ route('customer-menu.cart', $location->code) }}">تعديل السلة</a>
   <div id="summaryItems"></div>
   <div class="cart-total"><span>الإجمالي</span><span id="summaryTotal">0.00 {{ $menuCurrencySymbol }}</span></div>
  </div>
   </section>
   <div class="checkout-confirm">
   <div class="error" id="checkoutError" role="alert" tabindex="-1"></div>
   <button class="submit-order" id="checkoutSubmit" type="submit" disabled>تأكيد وإرسال الطلب</button>
   </div>
  </form>
  <div class="checkout-install">@include('customer-menu.partials.pwa-install')</div>
 </div>
</main>
</div>

@include('customer-menu.partials.bottom-nav', ['activeNav' => 'cart'])
@include('customer-menu.partials.cart-engine')

<script>
const CM = window.CustomerMenu;
const menuT = text => window.CustomerMenuI18n.t(text);
const menuF = (key, values) => window.CustomerMenuI18n.format(key, values);
const ORDER_URL = @json(route('customer-menu.orders.store', $location->code));
const PAYMENT_URL = @json(route('customer-menu.payment-options', $location->code));
const QUEUE_URL = @json(route('customer-menu.queue-status', $location->code));
const CART_URL = @json(route('customer-menu.cart', $location->code));
const TRACK_BASE = @json(route('customer-menu.show', $location->code));
const REQUEST_TOKEN = @json($requestToken ?? '');
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
let methods = [];
let checkoutSubmitting = false;
let checkoutSucceeded = false;
let paymentProofPreviewUrl = null;

function renderSummary() {
    const rows = CM.cartRows();
    if (!rows.length) { window.location.href = CART_URL; return; }
    document.getElementById('summaryItems').innerHTML = rows.map(x => {
        const modifierLine = (x.modifiers || []).length
            ? x.modifiers.map(m => `${m.name}${m.quantity > 1 ? ' ×' + m.quantity : ''}`).join('، ')
            : '';
        const subtitle = [x.variant_name, modifierLine].filter(Boolean).join(' — ');
        return `
      <div class="cart-row">
        <div class="cart-thumb">${x.image ? `<img src="${CM.esc(x.image)}" alt="">` : ''}</div>
        <div class="row-copy">
          <strong>${CM.esc(String(x.name || 'صنف').split(' - ')[0])}</strong>
          ${subtitle ? `<span style="display:block;font-size:.74rem;color:var(--muted)">${CM.esc(subtitle)}</span>` : ''}
          <small class="cart-line-meta">${x.quantity} × ${CM.money(x.price)}</small>
        </div>
        <strong class="cart-line-total">${CM.money(x.price * x.quantity)}</strong>
      </div>`;
    }).join('');
    document.getElementById('summaryTotal').textContent = CM.money(CM.cartTotal());
}

// TISH-QR-ONLY-JS-V3
// Only the server-resolved QR table may be used.
const qrTable = @json($selectedTable ?? null);

function showLockedTable() {
    const hint = document.getElementById('tableOneTimeHint');
    if (!hint) return;

    hint.hidden = !qrTable?.id;
    hint.textContent = qrTable?.id
        ? 'الطاولة محددة تلقائياً: ' + qrTable.name
        : '';
}

function preselectQrTable() {
    const service = document.getElementById('serviceType');
    const tableInput = document.getElementById('tableIdInput');

    tableInput.value = '';
    if (!qrTable?.id) return;

    if (![...service.options].some(option => option.value === 'dine_in')) {
        return;
    }

    tableInput.value = String(qrTable.id);
    service.value = 'dine_in';
    showLockedTable();
}

function serviceFields() {
    const value = document.getElementById('serviceType').value;
    const table = document.getElementById('tableField');
    const address = document.getElementById('addressField');
    const vehicle = document.getElementById('vehicleField');
    const tableInput = document.getElementById('tableIdInput');

    table.hidden = value !== 'dine_in';
    tableInput.disabled = value !== 'dine_in';

    if (value === 'dine_in') {
        tableInput.value = qrTable?.id ? String(qrTable.id) : '';
    }

    vehicle.hidden = value !== 'outdoor';
    vehicle.querySelectorAll('input').forEach(input => {
        input.disabled = vehicle.hidden;
        input.required = !vehicle.hidden;
    });

    address.hidden = value !== 'delivery';
    address.querySelector('textarea').disabled = address.hidden;

    document.querySelectorAll('[data-service-choice]').forEach(button => {
        button.setAttribute(
            'aria-pressed',
            String(button.dataset.serviceChoice === value)
        );
    });
}

async function loadPayments() {
    try {
        const response = await fetch(PAYMENT_URL, { headers: { Accept: 'application/json' } });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || 'تعذر تحميل طرق الدفع');
        methods = Array.isArray(data.payment_methods || data.methods)
            ? (data.payment_methods || data.methods)
            : [];
        renderPayments();
        document.getElementById('checkoutSubmit').disabled = methods.length === 0;
    } catch (e) {
        methods = [];
        document.getElementById('checkoutSubmit').disabled = true;
        document.getElementById('paymentMethods').innerHTML =
            '<div class="error">' + menuT('تعذر تحميل طرق الدفع. حدّث الصفحة أو راجع إعدادات الفرع.') + '</div>';
    }
}

function paymentIcon(type) {
    switch (String(type || '').toLowerCase()) {
        case 'cash': return 'fa-solid fa-money-bill-wave';
        case 'card_pos': return 'fa-solid fa-credit-card';
        case 'bank_transfer': return 'fa-solid fa-building-columns';
        case 'electronic_wallet': return 'fa-solid fa-wallet';
        default: return 'fa-solid fa-circle-dollar-to-slot';
    }
}

document.getElementById('paymentMethods').addEventListener('error', event => {
    if (!event.target.matches('.payment-method-logo')) return;
    const fallback = event.target.nextElementSibling;
    event.target.remove();
    fallback.hidden = false;
}, true);

function renderPayments() {
    document.getElementById('paymentMethods').innerHTML = methods.length
        ? methods.map(m => `
          <button class="payment-card" type="button" aria-pressed="false" data-pay="${m.id}">
            <span class="radio"></span>
            <span class="p-icon" aria-hidden="true">${m.logo ? `<img class="payment-method-logo" src="${CM.esc(m.logo)}" alt="" decoding="async">` : ''}<i class="${paymentIcon(m.type)}" ${m.logo ? 'hidden' : ''}></i></span>
            <span class="p-copy"><strong>${CM.esc(m.name)}</strong><small>${CM.esc(menuT(({cash:'نقد',card_pos:'بطاقة',bank_transfer:'تحويل بنكي',electronic_wallet:'محفظة إلكترونية'})[m.type] || m.type || ''))}</small></span>
          </button>`).join('')
        : 'لا توجد طرق دفع مفعلة.';
}

function choosePayment(id) {
    const method = methods.find(x => String(x.id) === String(id));
    if (!method) return;
    const methodInput = document.getElementById('paymentMethodId');
    if (methodInput.value === String(method.id)) return;
    methodInput.value = method.id;
    document.getElementById('paymentAccountId').value = '';
    document.getElementById('paymentReferenceInput').required = !!method.requires_reference;
    document.querySelectorAll('[data-pay]').forEach(button => {
        const selected = String(button.dataset.pay) === String(id);
        button.classList.toggle('active', selected);
        button.setAttribute('aria-pressed', String(selected));
    });
    const accounts = method.accounts || [];
    document.getElementById('paymentAccountDetails').innerHTML = accounts.length
        ? `<label>الحساب<select id="paymentAccountSelect" aria-required="true"><option value="">اختر الحساب</option>${accounts.map(a => `<option value="${a.id}">${CM.esc(a.name || a.provider_name || a.account_number || 'حساب')}</option>`).join('')}</select></label>`
        : '';

    document.getElementById('paymentAccountDetails').insertAdjacentHTML('beforeend', '<div id="selectedPaymentAccount" dir="auto"></div>');

    const isPureCash = String(method.type || '').toLowerCase().includes('cash') && !method.requires_reference && !method.requires_verification;

    const refField = document.getElementById('paymentReferenceField');
    const refInput = document.getElementById('paymentReferenceInput');
    refField.hidden = isPureCash;
    if (isPureCash) refInput.value = '';

    const proofField = document.getElementById('paymentProofField');
    const proofInput = document.getElementById('paymentProofInput');
    const proofStar = document.getElementById('proofRequiredStar');
    proofField.hidden = isPureCash;
    proofInput.required = !!method.requires_verification;
    proofStar.hidden = !method.requires_verification;
    if (isPureCash) { proofInput.value = ''; document.getElementById('uploadPreview').hidden = true; }
    if (!window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches) {
        document.querySelector('.payment-card.active')?.animate?.(
            [{scale: '.975'}, {scale: '1.012', offset: .65}, {scale: '1'}],
            {duration: 240, easing: 'ease-out'}
        );
        const details = document.getElementById('paymentAccountDetails');
        for (const field of [accounts.length ? details : null, refField, proofField]) {
            if (field && !field.hidden) field.animate?.(
                [{opacity: .6, translate: '0 -5px'}, {opacity: 1, translate: '0 0'}],
                {duration: 220, easing: 'ease-out'}
            );
        }
    }
}

function renderSelectedPaymentAccount() {
    const method = methods.find(item => String(item.id) === String(document.getElementById('paymentMethodId').value));
    const account = (method?.accounts || []).find(item => String(item.id) === String(document.getElementById('paymentAccountId').value));
    const target = document.getElementById('selectedPaymentAccount');
    if (!target) return;
    const fields = account ? [
        ['البنك / الجهة', account.provider_name],
        ['اسم صاحب الحساب', account.account_holder_name],
        ['رقم الحساب', account.account_number],
        ['IBAN', account.iban],
        ['رقم المحفظة', account.phone_number],
        ['تعليمات التحويل', account.instructions],
    ] : [];
    target.innerHTML = fields.filter(([, value]) => value).map(([label, value]) =>
        `<p><strong>${CM.esc(label)}:</strong> <span style="white-space:pre-wrap">${CM.esc(value)}</span></p>`
    ).join('');
}

document.getElementById('paymentProofInput').addEventListener('change', e => {
    const file = e.target.files[0];
    const preview = document.getElementById('uploadPreview');
    if (paymentProofPreviewUrl) { URL.revokeObjectURL(paymentProofPreviewUrl); paymentProofPreviewUrl = null; }
    if (!file) { preview.hidden = true; preview.replaceChildren(); return; }
    const isImage = file.type.startsWith('image/');
    preview.hidden = false;
    if (isImage) paymentProofPreviewUrl = URL.createObjectURL(file);
    preview.innerHTML = `${isImage ? `<img src="${paymentProofPreviewUrl}" alt="">` : `<i class="fa-solid fa-file-pdf" style="font-size:1.6rem;color:var(--primary)"></i>`}<span>${CM.esc(file.name)}</span>`;
});

document.addEventListener('click', e => {
    const pay = e.target.closest('[data-pay]');
    if (pay) choosePayment(pay.dataset.pay);
});

document.addEventListener('change', e => {
    if (e.target.id === 'paymentAccountSelect') {
        document.getElementById('paymentAccountId').value = e.target.value;
        renderSelectedPaymentAccount();
    }
});

const serviceSelect = document.getElementById('serviceType');
const servicePicker = document.getElementById('checkoutServicePicker');
if (servicePicker.querySelector('[data-service-choice]')) {
    document.getElementById('checkoutServiceFallback').hidden = true;
    servicePicker.hidden = false;
    servicePicker.addEventListener('click', event => {
        const option = event.target.closest('[data-service-choice]');
        if (!option) return;
        serviceSelect.value = option.dataset.serviceChoice;
        serviceFields();
    });
}
serviceSelect.addEventListener('change', serviceFields);

document.getElementById('checkoutForm').addEventListener('submit', async e => {
    e.preventDefault();
    if (checkoutSubmitting) return;
    const rows = CM.cartRows();
    if (!rows.length) return;

    const serviceType = document.getElementById('serviceType').value;
    const paymentMethodId = document.getElementById('paymentMethodId').value;
    const tableId = document.getElementById('tableIdInput').value;
    const address = e.currentTarget.elements.address?.value?.trim() || '';
    const error = document.getElementById('checkoutError');

    if (serviceType === 'dine_in' && !tableId) {
        error.textContent = 'اختر طاولة متاحة لطلب داخل المطعم.';
        document.getElementById('tableField').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }
    if (serviceType === 'delivery' && !address) {
        error.textContent = 'أدخل عنوان التوصيل.';
        document.getElementById('addressField').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }
    if (!paymentMethodId) {
        error.textContent = 'اختر طريقة الدفع قبل تأكيد الطلب.';
        document.getElementById('paymentMethods').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }
    const selectedMethod = methods.find(method => String(method.id) === String(paymentMethodId));
    const configuredAccounts = selectedMethod?.accounts || [];
    if (configuredAccounts.length > 0 && !document.getElementById('paymentAccountId').value) {
        error.textContent = 'اختر حساب الدفع أو التحويل قبل تأكيد الطلب.';
        document.getElementById('paymentAccountSelect')?.focus();
        document.getElementById('paymentAccountDetails').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }


    const proof = document.getElementById('paymentProofInput').files[0];
    if (proof && proof.size > 5 * 1024 * 1024) {
        error.textContent = 'حجم إثبات الدفع يجب ألا يتجاوز 5 ميجابايت.';
        error.focus();
        return;
    }

    error.textContent = '';
    checkoutSubmitting = true;
    const button = document.getElementById('checkoutSubmit');
    button.disabled = true;
    button.textContent = 'جاري إرسال الطلب...';

    try {
        const formData = new FormData(e.currentTarget);
        formData.append('request_token', CM.checkoutToken(REQUEST_TOKEN));
        rows.forEach((row, i) => {
            formData.append(`items[${i}][product_id]`, row.product_id);
            formData.append(`items[${i}][quantity]`, row.quantity);
            if (row.variant_id) {
                formData.append(`items[${i}][product_variant_id]`, row.variant_id);
            }
            (row.modifiers || []).forEach((m, j) => {
                formData.append(`items[${i}][modifiers][${j}][modifier_id]`, m.modifier_id);
                formData.append(`items[${i}][modifiers][${j}][quantity]`, m.quantity);
            });
        });

        const response = await fetch(ORDER_URL, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: formData,
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const fallback = response.status === 413
                ? 'حجم إثبات الدفع أكبر من المسموح على السيرفر. جرّب ملفاً أصغر.'
                : response.status === 419
                    ? 'انتهت صلاحية الصفحة. حدّث الصفحة وحاول مرة أخرى.'
                    : 'تعذر إرسال الطلب. حاول مرة أخرى.';
            throw new Error(menuT(Object.values(data.errors || {}).flat()[0] || data.message || fallback));
        }
        if (!data.track_url) throw new Error(menuT('تعذر قراءة نتيجة الطلب. أعد المحاولة بنفس الصفحة.'));
        checkoutSucceeded = true;

        CM.pushOrder({
            order_id: data.order_id || null,
            order_number: data.order_number,
            location: CM.LOCATION_CODE,
            url: data.track_url,
            status_url: data.status_url || null,
            state: data.state || 'received',
            status_label: data.status_label || 'تم استلام الطلب',
            created_at: data.created_at || new Date().toLocaleString(window.CustomerMenuI18n.isEnglish ? 'en' : 'ar'),
        });

        CM.clearCart();
        CM.toast('تم استلام طلبك بنجاح');
        setTimeout(() => { window.location.href = data.track_url || TRACK_BASE; }, 700);
    } catch (err) {
        error.textContent = menuT(err.message);
        error.focus();
    } finally {
        if (!checkoutSucceeded) {
            checkoutSubmitting = false;
            button.disabled = false;
            button.textContent = 'تأكيد وإرسال الطلب';
        }
    }
});

// Start checkout infrastructure first. Even if a malformed legacy cart row
// slips through, tables and payment methods must never remain stuck loading.
preselectQrTable();
showLockedTable();
serviceFields();
loadPayments();
renderSummary();

(async function loadEta() {
    try {
        const response = await fetch(QUEUE_URL, { headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (!response.ok) return;

        const cartMax = Math.max(0, ...CM.cartRows().map(r => Number(r.prep_time_minutes) || 0));
        const basePrep = cartMax || Number(data.default_prep_minutes) || 12;
        const totalMinutes = basePrep + (Number(data.queue_count) || 0) * (Number(data.queue_minutes_per_order) || 0);

        document.getElementById('etaText').textContent = menuF('الوقت المتوقع لتجهيز طلبك: حوالي {minutes} دقيقة', {minutes:totalMinutes});
        document.getElementById('etaBanner').hidden = false;
    } catch (e) {
        // Silently skip the ETA banner if the queue endpoint is unreachable —
        // it's a nice-to-have, not something that should block checkout.
    }
})();
</script>
</body>
</html>

