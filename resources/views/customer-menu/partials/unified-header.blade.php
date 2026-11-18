{{-- One consistent, accessible TISH menu header for subpages and order history. --}}
@php
    $cmHeaderLocation = $location ?? $order?->location ?? null;
    $cmHeaderHome = $cmHeaderLocation
        ? route('customer-menu.show', $cmHeaderLocation->code)
        : url('/');
    $cmHeaderBack = $backUrl ?? $cmHeaderHome;
    $cmHeaderName = $branding['name'] ?? $brandName ?? 'Tish British Burger';
    $cmHeaderBranch = $cmHeaderLocation?->name ?? '';
@endphp
<nav class="cm-unified-header" aria-label="{{ \App\Support\CustomerMenuLanguage::isEnglish() ? 'Page navigation' : 'التنقل بين الصفحات' }}">
 <a class="cm-unified-header-brand" href="{{ $cmHeaderHome }}" aria-label="{{ \App\Support\CustomerMenuLanguage::isEnglish() ? 'Menu home' : 'الرئيسية' }}">
  <img class="cm-unified-header-logo" src="{{ asset('assets/tish-paper/brand/logo-disc.webp') }}" alt="" width="62" height="62">
  <span class="cm-unified-header-brand-copy">
   <strong>{{ $cmHeaderName }}</strong>
   <small>{{ $cmHeaderBranch }}</small>
  </span>
 </a>
 <div class="cm-unified-header-actions" data-cm-language-host>
  <a class="cm-unified-header-back tish-paper-page-arrow"
     href="{{ $cmHeaderBack }}"
     aria-label="{{ \App\Support\CustomerMenuLanguage::isEnglish() ? 'Back' : 'رجوع' }}">
   <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
  </a>
 </div>
</nav>
