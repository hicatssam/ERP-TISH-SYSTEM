@php
    $backUrl = $backUrl ?? route('customer-menu.show', $location->code);
@endphp
<div class="shell">
 <div class="page-head">
  <a class="tish-paper-sublogo" href="{{ route('customer-menu.show', $location->code) }}" aria-label="الرئيسية">
   <img src="{{ asset('assets/tish-paper/brand/logo-disc.webp') }}" alt="{{ $branding['name'] ?? 'Tish' }}">
  </a>
  <a class="page-back tish-paper-page-arrow" href="{{ $backUrl }}" aria-label="رجوع"><i class="fa-solid fa-arrow-right"></i></a>
  @if(!empty($pageTitle))
   <div><h1>{{ $pageTitle }}</h1>@if(!empty($pageSubtitle))<p>{{ $pageSubtitle }}</p>@endif</div>
  @endif
 </div>
</div>
