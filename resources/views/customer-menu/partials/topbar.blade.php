@php
    $backUrl = $backUrl ?? route('customer-menu.show', $location->code);
@endphp
<div class="shell">
 <div class="page-head">
  <a class="tish-paper-sublogo" href="{{ route('customer-menu.show', $location->code) }}" aria-label="الرئيسية">
   <img src="{{ $branding['logo'] ?? asset('assets/tish-paper/brand/tish-logo.svg') }}" alt="{{ $branding['name'] ?? 'Tish' }}">
  </a>
  <a class="page-back" href="{{ $backUrl }}" aria-label="رجوع"><i class="fa-solid fa-arrow-right"></i></a>
  @if(!empty($pageTitle))
   <div><h1>{{ $pageTitle }}</h1>@if(!empty($pageSubtitle))<p>{{ $pageSubtitle }}</p>@endif</div>
  @endif
  <a href="{{ route('customer-menu.index', ['choose' => 1]) }}" style="margin-inline-start:auto;color:var(--primary);font-weight:700" aria-label="تغيير الفرع">تغيير الفرع</a>
 </div>
</div>
