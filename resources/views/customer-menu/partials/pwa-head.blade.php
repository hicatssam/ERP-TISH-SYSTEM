{{-- Shared customer app starts with branch selection. --}}
<link rel="manifest" href="{{ route('customer-menu.manifest') }}?v=paper-20261111-2">
<meta name="theme-color" content="{{ $theme['primary'] ?? '#C40035' }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ $branding['name'] ?? 'المنيو' }}">
{{-- iOS uses this artwork when adding TISH to the home screen. --}}
<link rel="apple-touch-icon" sizes="180x180" href="/assets/tish-paper/pwa/apple-touch-icon-180.png?v=paper-20261111-2">

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
