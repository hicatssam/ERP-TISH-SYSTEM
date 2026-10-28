@php
    $copyValue = static fn (string $key, string $default = ''): string =>
        (string) old($key, \App\Models\SystemSetting::get($key, $default));
@endphp

<section class="menu-copy-editor" id="menuCopyEditor" aria-labelledby="menuCopyHeading">
    <div class="menu-copy-editor-heading">
        <div>
            <h3 id="menuCopyHeading">نصوص واجهة الطلب — عربي / English</h3>
            <p>عدّل العنوان والوصف وعبارة البحث لكل لغة بشكل مستقل. تظهر التغييرات في صفحة طلب العميل والمنيو الكامل بعد الضغط على «حفظ الإعدادات».</p>
        </div>
        <div class="menu-copy-preview-switch" role="group" aria-label="لغة المعاينة">
            <button type="button" class="active" data-menu-copy-lang="ar" aria-pressed="true">عربي</button>
            <button type="button" data-menu-copy-lang="en" aria-pressed="false">English</button>
        </div>
    </div>

    <div class="menu-copy-editor-grid">
        @foreach([
            ['title', 'العنوان الرئيسي', 'customer_menu_title', 'customer_menu_title_en', 'أهلاً بك', '', 100],
            ['subtitle', 'الجملة أسفل العنوان', 'customer_menu_subtitle', 'customer_menu_subtitle_en', 'شو بتحب تأكل اليوم؟', '', 180],
            ['search', 'النص داخل خانة البحث', 'customer_menu_search_placeholder_ar', 'customer_menu_search_placeholder_en', 'ابحث عن صنفك المفضل...', 'Search your favorite items...', 150],
        ] as [$part, $label, $arabicKey, $englishKey, $arabicDefault, $englishDefault, $limit])
            <div class="menu-copy-editor-field">
                <strong>{{ $label }}</strong>
                <div class="menu-copy-editor-dual">
                    <label>
                        <span>العربية</span>
                        <input class="form-input" type="text" dir="rtl"
                               name="{{ $arabicKey }}" maxlength="{{ $limit }}"
                               value="{{ $copyValue($arabicKey, $arabicDefault) }}"
                               data-menu-copy-field="{{ $part }}:ar">
                    </label>
                    <label>
                        <span>English</span>
                        <input class="form-input" type="text" dir="ltr"
                               name="{{ $englishKey }}" maxlength="{{ $limit }}"
                               value="{{ $copyValue($englishKey, $englishDefault) }}"
                               data-menu-copy-field="{{ $part }}:en"
                               placeholder="Enter English text">
                    </label>
                </div>
            </div>
        @endforeach
    </div>

    <div class="menu-copy-preview" dir="rtl" id="menuCopyPreview" aria-live="polite">
        <small>معاينة صفحة الطلب</small>
        <h4 data-menu-copy-preview="title"></h4>
        <p data-menu-copy-preview="subtitle"></p>
        <div class="menu-copy-preview-search">
            <span aria-hidden="true">⌕</span>
            <span data-menu-copy-preview="search"></span>
        </div>
    </div>

    <p class="menu-copy-editor-hint">العنوان والوصف العربيان الحاليان محفوظان كما هما. إذا تركت ترجمتهما الإنجليزية فارغة، تُستخدم الترجمة الافتراضية للنصوص المعروفة؛ ولأي نص جديد أدخل ترجمته الإنجليزية للحصول على نتيجة دقيقة.</p>
</section>

<style>
.menu-copy-editor{margin:0 0 1.25rem;padding:1.2rem;border:1px solid #e5dfd2;border-radius:15px;background:#fff}
.menu-copy-editor-heading{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;margin-bottom:1rem}
.menu-copy-editor-heading h3{font-size:1.04rem;font-weight:800;margin:0 0 .3rem;color:#16293d}
.menu-copy-editor-heading p{font-size:.82rem;line-height:1.8;color:#657186;margin:0;max-width:48rem}
.menu-copy-preview-switch{display:flex;gap:.35rem;flex-shrink:0}
.menu-copy-preview-switch button{padding:.5rem .85rem;border-radius:8px;border:1px solid #e2e5e9;background:#fff;color:#495569;font-weight:700;cursor:pointer}
.menu-copy-preview-switch button.active{background:#172d43;color:#fff;border-color:#172d43}
.menu-copy-editor-grid{display:grid;gap:.85rem}
.menu-copy-editor-field{padding:.85rem;border:1px solid #eef0f2;background:#fafbfc;border-radius:11px}
.menu-copy-editor-field>strong{display:block;color:#263648;margin-bottom:.65rem;font-size:.86rem}
.menu-copy-editor-dual{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}
.menu-copy-editor-dual label span{display:block;color:#6d7787;font-size:.76rem;font-weight:700;margin-bottom:.35rem}
.menu-copy-editor-dual input{width:100%;font-size:.87rem}
.menu-copy-preview{margin-top:1rem;padding:1.25rem;border:1px solid #e4d6b7;border-radius:14px;background:linear-gradient(130deg,#fbf9f4,#f5f7fb);min-height:155px}
.menu-copy-preview>small{display:block;color:#8a7142;font-size:.72rem}
.menu-copy-preview h4{font-weight:900;color:#1d2632;font-size:1.65rem;margin:.4rem 0 .15rem}
.menu-copy-preview p{color:#647083;font-size:.95rem;margin:0 0 .8rem}
.menu-copy-preview-search{padding:.75rem 1rem;border:1px solid #e4e6ec;background:#fff;border-radius:12px;color:#84909e;display:flex;align-items:center;gap:.65rem}
.menu-copy-editor-hint{font-size:.78rem;line-height:1.8;color:#687586;margin:.8rem 0 0}
@media(max-width:760px){.menu-copy-editor-heading{flex-direction:column}.menu-copy-editor-dual{grid-template-columns:1fr}}
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('menuCopyEditor');
    if (!root) return;
    const preview = document.getElementById('menuCopyPreview');
    let selectedLanguage = 'ar';
    const fields = {};
    root.querySelectorAll('[data-menu-copy-field]').forEach(input => {
        fields[input.dataset.menuCopyField] = input;
        input.addEventListener('input', refresh);
    });
    const fallback = {
        'أهلاً بك': 'Welcome',
        'اطلب مباشرة': 'Order directly',
        'شو بتحب تأكل اليوم؟': 'What would you like today?',
        'اختر طلبك وسنجهزه لك': "Choose your order and we'll prepare it for you",
    };
    function value(part) {
        const selected = fields[part + ':' + selectedLanguage]?.value.trim() || '';
        if (selected) return selected;
        const arabic = fields[part + ':ar']?.value.trim() || '';
        if (selectedLanguage === 'ar') return arabic;
        if (part === 'search') return 'Search your favorite items...';
        return fallback[arabic] || arabic;
    }
    function refresh() {
        preview.dir = selectedLanguage === 'en' ? 'ltr' : 'rtl';
        for (const part of ['title', 'subtitle', 'search']) {
            const target = root.querySelector('[data-menu-copy-preview="' + part + '"]');
            if (target) target.textContent = value(part);
        }
    }
    root.querySelectorAll('[data-menu-copy-lang]').forEach(btn => btn.addEventListener('click', () => {
        selectedLanguage = btn.dataset.menuCopyLang;
        root.querySelectorAll('[data-menu-copy-lang]').forEach(el => {
            const active = el === btn;
            el.classList.toggle('active', active);
            el.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        refresh();
    }));
    refresh();
});
</script>
