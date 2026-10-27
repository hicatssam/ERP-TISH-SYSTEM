<!doctype html>
<html lang="{{ \App\Support\CustomerMenuLanguage::current() }}" dir="{{ \App\Support\CustomerMenuLanguage::direction() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>اختر فرعك - {{ $branding['name'] ?? config('app.name') }}</title>
@include('customer-menu.partials.styles')
@include('customer-menu.partials.pwa-head')
<style>
.branch-welcome{width:min(760px,calc(100% - 32px));margin:0 auto;padding:52px 0 90px}
.branch-brand{display:flex;align-items:center;gap:12px;font-weight:800;margin-bottom:34px}
.branch-brand img{width:64px;height:64px;object-fit:contain;border-radius:18px;background:var(--surface)}
.branch-welcome h1{font-size:clamp(28px,5vw,42px);margin:0 0 10px;font-weight:900}
.branch-welcome>p{color:var(--muted);line-height:1.8;margin:0 0 24px}
.branch-search{width:100%;padding:16px 18px;border:1px solid var(--line);background:var(--surface);color:var(--text);border-radius:16px;margin-bottom:22px}
.branch-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.branch-choice{display:flex;align-items:center;gap:14px;padding:24px;border:1px solid var(--line);border-radius:var(--radius);background:var(--surface);box-shadow:var(--shadow)}
.branch-choice:hover,.branch-choice:focus-visible{border-color:var(--primary);outline:2px solid var(--primary);outline-offset:2px}
.branch-choice i{font-size:24px;color:var(--primary)}.branch-choice strong{display:block;font-size:20px}
.branch-choice small{display:block;color:var(--muted);margin-top:5px;line-height:1.6}.branch-choice .arrow{margin-inline-start:auto}
.branch-empty{padding:24px;color:var(--muted);text-align:center}
@media(max-width:600px){.branch-grid{grid-template-columns:1fr}.branch-welcome{padding-top:30px}.branch-choice{padding:20px}}
</style>
</head>
<body>
<main class="branch-welcome">
    <div class="branch-brand">
        @if($branding['logo'] ?? null)<img src="{{ $branding['logo'] }}" alt="{{ $branding['name'] }}">@endif
        <span>{{ $branding['name'] }}</span>
    </div>
    <h1>من أي فرع بدك تطلب؟</h1>
    <p>اختر الفرع المناسب إلك، وبعدها تصفّح المنيو وابدأ طلبك.</p>
    @if($branches->isNotEmpty())
        <input type="search" class="branch-search" data-branch-search placeholder="ابحث عن فرع أو منطقة" aria-label="ابحث عن فرع أو منطقة">
        <div class="branch-grid">
        @foreach($branches as $branch)
            <a class="branch-choice" data-choose-branch data-branch-code="{{ $branch->code }}"
               href="{{ route('customer-menu.show', ['location' => $branch->code, 'branch_selected' => 1]) }}">
                <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                <span><strong>{{ $branch->name }}</strong>@if($branch->address)<small>{{ $branch->address }}</small>@endif</span>
                <i class="fa-solid fa-arrow-left arrow" aria-hidden="true"></i>
            </a>
        @endforeach
        </div>
        <p class="branch-empty" data-branch-search-empty hidden>لا يوجد فرع مطابق للبحث.</p>
    @else
        <p class="branch-empty">لا توجد فروع متاحة حاليًا. حاول مجدداً لاحقًا.</p>
    @endif
</main>
@include('customer-menu.partials.pwa-install')
<script src="{{ asset('assets/js/customer-menu/branch-picker.js') }}?v=1"></script>
</body>
</html>
