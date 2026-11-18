@php
    $backUrl = $backUrl ?? route('customer-menu.show', $location->code);
@endphp
<div class="shell">
 @include('customer-menu.partials.unified-header', ['backUrl' => $backUrl])
 @if(!empty($pageTitle))
 <div class="cm-subpage-heading">
  <h1>{{ $pageTitle }}</h1>
 </div>
 @endif
</div>
