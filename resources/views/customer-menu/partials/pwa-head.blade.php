{{-- Shared customer app starts with branch selection. --}}
<link rel="manifest" href="{{ route('customer-menu.manifest') }}">
<meta name="theme-color" content="{{ $theme['primary'] ?? '#C40035' }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ $branding['name'] ?? 'المنيو' }}">
<link rel="apple-touch-icon" href="{{ route('pwa.icon', ['size' => 180]) }}">

<script>
// Capture the Chromium install event before deferred page widgets render.
window.dahabCustomerPwa = window.dahabCustomerPwa || { prompt: null, installed: false };
window.addEventListener('beforeinstallprompt', function (event) {
    event.preventDefault();
    window.dahabCustomerPwa.prompt = event;
    window.dispatchEvent(new Event('dahab:customer-pwa-ready'));
});
window.addEventListener('appinstalled', function () {
    window.dahabCustomerPwa.installed = true;
    window.dahabCustomerPwa.prompt = null;
    window.dispatchEvent(new Event('dahab:customer-pwa-installed'));
});
</script>
