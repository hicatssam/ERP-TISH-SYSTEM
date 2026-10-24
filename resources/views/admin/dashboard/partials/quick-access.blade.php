{{-- Dashboard quick access: enabled modules, permissions, and existing named routes only. --}}
@php
    $dashboardShortcuts = collect([
        ['key' => 'orders', 'label' => 'إنشاء طلب', 'icon' => 'bi-plus-circle', 'module' => 'sales', 'permission' => 'orders.create', 'route' => 'orders.create'],
        ['key' => 'cakes', 'label' => 'طلب كيك خاص', 'icon' => 'bi-cake2', 'module' => 'cake_orders', 'permission' => 'cake_orders.create', 'route' => 'cake-orders.create'],
        ['key' => 'stock', 'label' => 'طلب مخزون', 'icon' => 'bi-box-seam', 'module' => 'inventory', 'permission' => 'stock_requests.create', 'route' => 'stock-requests.create'],
        ['key' => 'cash', 'label' => 'جلسة الكاش', 'icon' => 'bi-wallet2', 'module' => 'finance', 'permission' => 'cash_sessions.manage', 'route' => 'cash-sessions.index'],
        ['key' => 'sweets', 'label' => 'طلب حلويات', 'icon' => 'bi-bag-plus', 'module' => null, 'permission' => 'showroom_sweets_requests.create', 'route' => 'showroom-sweets-requests.create'],
        ['key' => 'pos', 'label' => 'نقطة البيع', 'icon' => 'bi-cart3', 'module' => 'restaurant_pos', 'permission' => 'restaurant_pos.use', 'route' => 'restaurant.pos.index'],
        ['key' => 'hall', 'label' => 'تشغيل الطلبات', 'icon' => 'bi-shop', 'module' => 'restaurant', 'permission' => 'restaurant.view', 'route' => 'restaurant.dashboard'],
        ['key' => 'kds', 'label' => 'شاشة المطبخ', 'icon' => 'bi-display', 'module' => 'kds', 'permission' => 'kds.view', 'route' => 'kds.index'],
        ['key' => 'production', 'label' => 'أوامر الإنتاج', 'icon' => 'bi-gear', 'module' => 'production', 'permission' => 'production.view', 'route' => 'production.orders.index'],
        ['key' => 'quality', 'label' => 'مراقبة الجودة', 'icon' => 'bi-clipboard-check', 'module' => 'quality_control', 'permission' => 'quality_control.view', 'route' => 'quality-control.index'],
        ['key' => 'purchasing', 'label' => 'المشتريات', 'icon' => 'bi-bag', 'module' => 'purchasing', 'permission' => 'dashboard.procurement', 'route' => 'procurement.dashboard'],
    ])->filter(fn ($shortcut) =>
        ($shortcut['module'] === null || $moduleEnabled($shortcut['module']))
        && $dashboardUser->can($shortcut['permission'])
        && $routeExists($shortcut['route'])
    )->values();
@endphp

@if($dashboardShortcuts->isNotEmpty())
<section class="card dahab-shortcuts" aria-labelledby="dahab-shortcuts-title" data-dashboard-shortcuts>
    <div class="card-header dahab-shortcuts-header">
        <h2 class="card-title" id="dahab-shortcuts-title">اختصاراتي</h2>
        <button type="button" class="dahab-shortcuts-customize" data-shortcuts-edit aria-pressed="false">
            <i class="bi bi-sliders" aria-hidden="true"></i> تخصيص
        </button>
    </div>
    <div class="card-body">
        <p class="dahab-shortcuts-help" data-shortcuts-help hidden>اضغط على الاختصار لإخفائه أو إظهاره، ثم اضغط تم.</p>
        <div class="dahab-shortcuts-grid">
            @foreach($dashboardShortcuts as $shortcut)
            <a class="dahab-shortcut" href="{{ route($shortcut['route']) }}" data-shortcut-key="{{ $shortcut['key'] }}">
                <span class="dahab-shortcut-icon"><i class="bi {{ $shortcut['icon'] }}" aria-hidden="true"></i></span>
                <span class="dahab-shortcut-label">{{ $shortcut['label'] }}</span>
                <span class="dahab-shortcut-selected" aria-hidden="true"><i class="bi bi-check-circle-fill"></i></span>
            </a>
            @endforeach
        </div>
    </div>
</section>
@endif

@once
@push('styles')
<style>
.dahab-shortcuts{margin-bottom:1.25rem;overflow:hidden}
.dahab-shortcuts-header{display:flex;justify-content:space-between;align-items:center;gap:12px}
.dahab-shortcuts-customize{border:0;background:transparent;color:var(--gold-deep,#a77b16);font:inherit;font-size:.78rem;font-weight:800;cursor:pointer;padding:8px;border-radius:8px}
.dahab-shortcuts-customize:hover,.dahab-shortcuts-customize:focus-visible{background:rgba(180,133,35,.1);outline-offset:2px}
.dahab-shortcuts-help{font-size:.75rem;color:var(--text-muted);margin:0 0 12px}
.dahab-shortcuts-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:.75rem}
.dahab-shortcut{position:relative;display:flex;min-height:100px;flex-direction:column;justify-content:center;align-items:center;gap:.55rem;padding:.8rem .45rem;color:var(--text);border:1px solid var(--border-light);border-radius:12px;text-decoration:none;background:var(--surface,#fff);transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease,opacity .2s ease}
.dahab-shortcut:hover{transform:translateY(-4px);box-shadow:0 10px 22px rgba(30,35,43,.08);border-color:rgba(180,133,35,.5)}
.dahab-shortcut:focus-visible{outline:2px solid var(--gold,#d4a017);outline-offset:3px}
.dahab-shortcut-icon{display:grid;place-items:center;width:42px;height:42px;border-radius:12px;background:rgba(212,160,23,.1);color:var(--gold-deep,#a77b16);font-size:1.2rem}
.dahab-shortcut-label{text-align:center;font-size:.78rem;font-weight:800;line-height:1.5}
.dahab-shortcut-selected{display:none;position:absolute;inset-block-start:6px;inset-inline-start:8px;color:#168545}
.dahab-shortcuts.is-editing .dahab-shortcut-selected{display:block}
.dahab-shortcuts.is-editing .dahab-shortcut.is-hidden{display:flex;opacity:.4;border-style:dashed}
.dahab-shortcuts:not(.is-editing) .dahab-shortcut.is-hidden{display:none}
.dahab-shortcuts.is-editing .dahab-shortcut.is-hidden .dahab-shortcut-selected{color:#9ca3af}
.dashboard-task-card,.dashboard-module-card,.stats-grid .stat-card{transition:transform .22s ease,box-shadow .22s ease,border-color .22s ease}
.stats-grid .stat-card:hover,.dashboard-task-card:hover,.dashboard-module-card:hover{transform:translateY(-3px);box-shadow:0 10px 24px rgba(15,23,42,.08)}
@media(max-width:1100px){.dahab-shortcuts-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}
@media(max-width:680px){.dahab-shortcuts-grid{grid-template-columns:repeat(3,minmax(0,1fr));gap:.5rem}.dahab-shortcut{min-height:90px}}
@media(max-width:380px){.dahab-shortcuts-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(prefers-reduced-motion:reduce){.dahab-shortcut,.stats-grid .stat-card,.dashboard-task-card,.dashboard-module-card{transition:none!important;animation:none!important}.dahab-shortcut:hover,.stats-grid .stat-card:hover{transform:none}}
</style>
@endpush
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const box = document.querySelector('[data-dashboard-shortcuts]');
    if (!box) return;
    const button = box.querySelector('[data-shortcuts-edit]');
    const help = box.querySelector('[data-shortcuts-help]');
    const items = Array.from(box.querySelectorAll('[data-shortcut-key]'));
    const storageKey = 'dahab:dashboard-shortcuts:v1:{{ (int) $dashboardUser->id }}';
    let hidden = [];
    try { const saved = JSON.parse(localStorage.getItem(storageKey) || '[]'); if (Array.isArray(saved)) hidden = saved.filter(x => typeof x === 'string'); } catch (_) {}
    const render = () => items.forEach(item => {
        const off = hidden.includes(item.dataset.shortcutKey);
        item.classList.toggle('is-hidden',off);
        item.setAttribute('aria-label',item.querySelector('.dahab-shortcut-label').textContent.trim() + (box.classList.contains('is-editing') ? (off ? '، إظهار' : '، إخفاء') : ''));
        item.setAttribute('aria-disabled',box.classList.contains('is-editing') ? 'true' : 'false');
    });
    button.addEventListener('click', () => {
        const editing = box.classList.toggle('is-editing');
        button.innerHTML = editing ? '<i class="bi bi-check2" aria-hidden="true"></i> تم' : '<i class="bi bi-sliders" aria-hidden="true"></i> تخصيص';
        button.setAttribute('aria-pressed',String(editing));
        help.hidden = !editing;
        if (!editing) { try { localStorage.setItem(storageKey, JSON.stringify(hidden)); } catch (_) {} }
        render();
    });
    items.forEach(item => item.addEventListener('click', event => {
        if (!box.classList.contains('is-editing')) return;
        event.preventDefault();
        const key = item.dataset.shortcutKey;
        hidden = hidden.includes(key) ? hidden.filter(x => x !== key) : [...hidden,key];
        render();
    }));
    render();
});
</script>
@endpush
@endonce