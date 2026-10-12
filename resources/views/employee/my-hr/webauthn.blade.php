<section class="card myhr-punch-card" id="myWebauthn" data-register-options="{{ route('my-hr.webauthn.registration-options') }}"
    data-register="{{ route('my-hr.webauthn.register') }}" data-punch-options="{{ route('my-hr.webauthn.punch-options') }}"
    data-punch="{{ route('my-hr.webauthn.punch') }}">
    <div class="card-header"><span class="card-title">التحقق بالجوال</span></div>
    <div class="card-body">
        <p class="page-subheading">استخدم قفل جوالك لتأكيد الحضور أو الانصراف: بصمة إصبع، وجه أو رمز حسب الجهاز. لا تُرسل بيانات البصمة للنظام. هذا الخيار لا يثبت وجودك داخل الفرع.</p>
        @if($webauthnPunchEnabled)
            <div style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:end">
                <label>اسم المفتاح<input id="myWebauthnLabel" class="form-input" maxlength="100" placeholder="مثال: جوالي الشخصي" autocomplete="off"></label>
                <button class="btn btn-outline" type="button" id="myWebauthnRegister" disabled>تسجيل مفتاح الجوال</button>
                <button class="btn btn-gold" type="button" id="myWebauthnPunch" disabled>تسجيل الحضور / الانصراف</button>
            </div>
            <p id="myWebauthnStatus" role="status" aria-live="polite">جارٍ فحص دعم التحقق بالجوال…</p>
            <ul>
                @forelse($webauthnCredentials as $credential)
                    <li>{{ $credential->label }} — {{ match($credential->status) { 'active' => 'معتمد', 'pending' => 'بانتظار اعتماد مسؤول الحضور', default => 'ملغى' } }}</li>
                @empty<li>سجّل مفتاحًا أولًا، ثم اطلب من مسؤول الحضور اعتماده.</li>@endforelse
            </ul>
        @else
            <p>الخيار غير مفعّل. يمكن لمسؤول النظام تفعيله من إعدادات الحضور والرواتب.</p>
        @endif
    </div>
</section>
@if($webauthnPunchEnabled)
    <script src="{{ asset('js/employee-webauthn.js') }}" defer></script>
@endif
