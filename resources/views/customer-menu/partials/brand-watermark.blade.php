@if(!empty($branding['logo']))
    <div class="cm-brand-watermark" aria-hidden="true">
        <img class="cm-brand-core" src="{{ $branding['logo'] }}" alt="" loading="lazy" decoding="async">
        @if(!empty($floatingBrandingEnabled))
            <img class="cm-brand-echo-left" src="{{ $branding['logo'] }}" alt="" loading="lazy" decoding="async">
            <img class="cm-brand-echo-right" src="{{ $branding['logo'] }}" alt="" loading="lazy" decoding="async">
        @endif
    </div>
@endif
