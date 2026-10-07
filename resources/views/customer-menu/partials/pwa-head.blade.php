{{-- Shared customer app starts with branch selection. --}}
<link rel="manifest" href="{{ route('customer-menu.manifest') }}">
<meta name="theme-color" content="{{ $theme['primary'] ?? '#C40035' }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ $branding['name'] ?? 'المنيو' }}">
<link rel="apple-touch-icon" href="{{ route('pwa.icon', ['size' => 180]) }}">
