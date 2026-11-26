@php
    $location = $order->location;
    $menuCurrencySymbol = app(\App\Services\PrintThemeService::class)->settings()['currency_symbol'];
@endphp
<!doctype html>
<html lang="{{ \App\Support\CustomerMenuLanguage::current() }}" dir="{{ \App\Support\CustomerMenuLanguage::direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    @php
        $brandLogo = null;
        $logoCandidates = [
            data_get($branding, 'logo'),
            data_get($branding, 'logo.url'),
            data_get($branding, 'logo.src'),
            data_get($branding, 'logo.path'),
            data_get($branding, 'logo.original_url'),
            data_get($branding, 'logo.public_url'),
            data_get($branding, 'logo_url'),
            data_get($branding, 'brand_logo'),
            data_get($branding, 'logo_small'),
            data_get($branding, 'favicon'),
            data_get($branding, 'assets.logo'),
            data_get($branding, 'assets.logo.url'),
            data_get($branding, 'assets.logo.original_url'),
            data_get($branding, 'assets.logo.public_url'),
            data_get($branding, 'logos.primary'),
            data_get($branding, 'logos.primary.url'),
        ];
        foreach ($logoCandidates as $logoCandidate) {
            if (is_array($logoCandidate)) {
                $logoCandidate = $logoCandidate['url']
                    ?? $logoCandidate['src']
                    ?? $logoCandidate['path']
                    ?? $logoCandidate['original_url']
                    ?? $logoCandidate['public_url']
                    ?? null;
            }
            if (!empty($logoCandidate)) {
                $brandLogo = $logoCandidate;
                break;
            }
        }
        $brandName = !empty($branding['name'])
            ? $branding['name']
            : (!empty($branding['brand_name']) ? $branding['brand_name'] : 'طلبك');
        if (is_string($brandLogo) && $brandLogo !== '' && !preg_match('/^(https?:|data:|blob:|\/\/|\/)/i', $brandLogo)) {
            $brandLogo = '/'.ltrim($brandLogo, '/');
        }
        $appDownloadUrl = $branding['app_download_url']
            ?? $branding['app_url']
            ?? $branding['mobile_app_url']
            ?? $branding['app_link']
            ?? null;
        $websiteUrl = $branding['website_url']
            ?? $branding['website']
            ?? $branding['url']
            ?? null;
    @endphp
    <title>تتبع {{ $order->order_number }} — {{ $brandName }}</title>
    {{-- The tracking page uses the existing local TISH icon; avoid a stale uploaded favicon URL. --}}
    <link rel="icon" type="image/png" href="{{ asset('assets/tish-paper/pwa/icon-192.png') }}">
    @include('customer-menu.partials.styles')
    @include('customer-menu.partials.pwa-head')
    <style>
        :root{
            --primary:{{ $theme['primary'] ?? '#704C34' }};
            --accent:{{ $theme['accent'] ?? '#D79A55' }};
            --bg:{{ $theme['background'] ?? '#F7F3EE' }};
            --surface:{{ $theme['surface'] ?? '#FFFFFF' }};
            --text:{{ $theme['text'] ?? '#241D18' }};
            --muted:{{ $theme['muted'] ?? '#7D746C' }};
            --radius:{{ $theme['radius'] ?? 18 }}px;
        }
        *{box-sizing:border-box}
        body{
            margin:0;
            min-height:100vh;
            background:
                radial-gradient(circle at 90% 0%, color-mix(in srgb,var(--accent) 20%,transparent), transparent 25rem),
                var(--bg);
            color:var(--text);
            font-family:Cairo,Tajawal,Arial,sans-serif;
            padding-bottom:calc(102px + var(--safe-bottom));
        }
        a{color:inherit;text-decoration:none}
        .wrap{width:min(820px,calc(100% - 30px));margin:auto}
        .appBar{
            background:var(--primary);
            color:#fff;
            font-size:.82rem;
        }
        .appBarInner{
            min-height:40px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
        }
        .appHint{display:flex;align-items:center;gap:7px;opacity:.92}
        .appLinks{display:flex;align-items:center;gap:8px}
        .appLink{
            display:inline-flex;
            align-items:center;
            gap:7px;
            padding:6px 10px;
            border-radius:999px;
            font-weight:900;
            white-space:nowrap;
        }
        .downloadLink{background:var(--accent);color:var(--text)}
        .websiteLink{border:1px solid rgba(255,255,255,.28);color:#fff}
        .websiteLink img{width:20px;height:20px;object-fit:contain;border-radius:6px;background:#fff}
        .topbar{
            position:sticky;
            top:0;
            z-index:30;
            padding:18px 0;
            background:color-mix(in srgb,var(--bg) 92%,transparent);
            backdrop-filter:blur(12px);
        }
        .topin{display:flex;align-items:center;justify-content:space-between;gap:14px}
        .brand{display:flex;align-items:center;gap:10px}
        .brand img{width:42px;height:42px;object-fit:contain}
        .brand b,.brand small{display:block}
        .brand small{color:var(--muted);font-size:.76rem}
        .back{color:var(--primary);font-weight:900}
        .card{
            background:var(--surface);
            border:1px solid color-mix(in srgb,var(--text) 8%,transparent);
            border-radius:calc(var(--radius) + 5px);
            padding:clamp(20px,5vw,38px);
            box-shadow:0 22px 65px color-mix(in srgb,var(--text) 11%,transparent);
        }
        .orderHead{
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:18px;
            padding-bottom:24px;
            border-bottom:1px solid color-mix(in srgb,var(--text) 9%,transparent);
        }
        .eyebrow{font-size:.78rem;color:var(--muted);font-weight:800}
        h1{margin:5px 0 0;font-size:clamp(25px,5vw,38px)}
        .status{
            display:inline-flex;
            align-items:center;
            gap:7px;
            padding:9px 13px;
            border-radius:999px;
            background:color-mix(in srgb,var(--accent) 22%,var(--surface));
            color:var(--primary);
            font-size:.82rem;
            font-weight:900;
            white-space:nowrap;
        }
        .statusDot{width:8px;height:8px;border-radius:50%;background:var(--accent)}
        .paymentSummary{display:flex;align-items:center;gap:12px;margin:0 0 16px;padding:15px 16px;border:1px solid color-mix(in srgb,var(--primary) 13%,var(--surface));border-radius:24px;background:var(--surface);box-shadow:0 8px 24px color-mix(in srgb,var(--text) 6%,transparent)}
        .paymentSummaryIcon{width:44px;height:44px;flex:0 0 44px;border-radius:50%;display:grid;place-items:center;background:color-mix(in srgb,var(--primary) 8%,var(--surface));color:var(--primary);font-size:1.05rem}
        .paymentSummaryText{flex:1;min-width:0;display:grid;gap:4px}
        .paymentSummaryText strong{font-size:.92rem}
        .paymentSummaryText small{font-size:.77rem;line-height:1.5;color:var(--muted)}
        .paymentSummaryBadge{display:inline-flex;align-items:center;justify-content:center;max-width:44%;border-radius:999px;padding:8px 11px;font-size:.75rem;line-height:1.35;text-align:center;font-weight:900;background:color-mix(in srgb,var(--primary) 9%,var(--surface));color:var(--primary)}
        .paymentSummaryBadge[data-payment-state="paid"]{background:#e3f5eb;color:#116b3c}
        .paymentSummaryBadge[data-payment-state="partial"],.paymentSummaryBadge[data-payment-state="review"]{background:#fff1d5;color:#875300}
        .paymentSummaryBadge[data-payment-state="refunded"]{background:#e9e9f4;color:#514d79}
        @media(max-width:400px){.paymentSummary{gap:9px;padding:12px}.paymentSummaryBadge{font-size:.7rem;padding:8px;max-width:43%}.paymentSummaryIcon{width:38px;height:38px;flex-basis:38px}}

        .progress{padding:28px 0 25px}
        .progressLine{display:grid;grid-template-columns:repeat(4,1fr);position:relative}
        .progressLine::before{
            content:"";
            position:absolute;
            top:17px;
            right:12.5%;
            left:12.5%;
            height:3px;
            background:color-mix(in srgb,var(--text) 12%,transparent);
        }
        .progressItem{position:relative;z-index:1;text-align:center;color:var(--muted);font-size:.74rem;font-weight:800}
        .progressItem .dot{
            display:grid;
            place-items:center;
            width:36px;
            height:36px;
            margin:0 auto 8px;
            border-radius:50%;
            background:var(--bg);
            border:3px solid color-mix(in srgb,var(--text) 13%,transparent);
            color:var(--muted);
            font-size:.8rem;
        }
        .progressItem.on{color:var(--primary)}
        .progressItem.on .dot{background:var(--primary);border-color:var(--primary);color:#fff}
        .progressItem.current .dot{box-shadow:0 0 0 5px color-mix(in srgb,var(--accent) 28%,transparent)}
        .progressItem[data-stage="3"].on .dot{background:var(--primary);border-color:var(--primary);color:#fff}
        .progressItem[data-stage="3"].current{color:var(--primary);font-weight:900}
        .notice{
            display:none;
            padding:12px 14px;
            margin-bottom:20px;
            border-radius:12px;
            background:color-mix(in srgb,var(--accent) 16%,var(--surface));
            color:var(--primary);
            font-size:.9rem;
        }
        .notice.show{display:block}
        .sectionTitle{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:4px 0 10px}
        .sectionTitle h2{font-size:1rem;margin:0}
        .live{font-size:.72rem;color:var(--muted)}
        .items{border-top:1px solid color-mix(in srgb,var(--text) 9%,transparent)}
        .item{display:flex;justify-content:space-between;gap:12px;padding:14px 0;border-bottom:1px solid color-mix(in srgb,var(--text) 9%,transparent)}
        .item small{display:block;color:var(--muted);margin-top:3px}
        .item b{white-space:nowrap;color:var(--primary)}
        .total{display:flex;justify-content:space-between;gap:12px;padding-top:20px;font-size:1.15rem;font-weight:900}
        .total span:last-child{color:var(--primary)}
        .footerActions{display:flex;gap:10px;margin-top:27px}
        .action{flex:1;text-align:center;padding:12px 14px;border-radius:12px;font-weight:900}
        .action.primary{background:var(--primary);color:#fff}
        .action.secondary{border:1px solid color-mix(in srgb,var(--primary) 20%,transparent);color:var(--primary)}
        .cancelled .progress{opacity:.42}
        .action{font-family:inherit;font-size:.85rem;cursor:pointer}
        .customer-alert-controls{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:16px 0}
        .customer-alert-controls button{border:1px solid var(--primary);background:var(--surface);color:var(--primary);border-radius:12px;padding:10px 16px;font:800 .82rem Cairo,Tajawal,Arial,sans-serif;cursor:pointer}
        .customer-alert-controls small{color:var(--muted)}
        .customer-status-alert{display:none;margin:10px 0;padding:13px;border-radius:12px;background:color-mix(in srgb,var(--accent) 18%,var(--surface));font-weight:800}
        .customer-status-alert.show{display:block}
        @media(max-width:560px){
            .wrap{width:min(100% - 20px,820px)}
            .topbar{padding:12px 0}
            .brand img{width:36px;height:36px}
            .orderHead{flex-direction:column}
            .status{align-self:flex-start}
            .progressItem{font-size:.65rem}
            .progressItem .dot{width:32px;height:32px}
            .progressLine::before{top:15px}
            .footerActions{flex-direction:column}
            .appBarInner{min-height:38px}
            .appHint{font-size:.72rem}
            .appHint span:last-child{max-width:145px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
            .appLink{padding:6px 8px;font-size:.74rem}
            .websiteLink span{display:none}
        }

        /* Order-status paper overlay, deliberately separate from system push. */
        .tish-status-overlay{position:fixed;inset:0;z-index:120;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(25,12,11,.68);backdrop-filter:blur(5px)}
        .tish-status-overlay.is-open{display:flex}
        .tish-status-sheet{position:relative;width:min(100%,410px);max-height:min(90dvh,700px);overflow:auto;padding:30px 22px 24px;text-align:center;color:#251512;background:#f5e5c6;border:3px solid #271b17;box-shadow:12px 14px 0 #b92c24,0 24px 65px rgba(0,0,0,.45);clip-path:polygon(0 0,98% 0,100% 3%,99% 96%,96% 100%,2% 99%,0 96%,1% 3%);animation:tish-paper-open .48s cubic-bezier(.2,1.2,.25,1) both}
        .tish-status-close{position:absolute;top:10px;left:12px;border:2px solid #251512;background:transparent;border-radius:50%;width:36px;height:36px;font-weight:900;cursor:pointer}
        .tish-status-icon{position:relative;display:grid;place-items:center;margin:6px auto 14px;width:100px;height:100px;border:4px solid #271b17;border-radius:50%;background:#c52e27;color:white;font-size:48px}
        .tish-status-overlay[data-state="preparing"] .tish-status-icon::before{content:"♨";position:absolute;top:-30px;font-size:30px;animation:tish-steam 1.6s ease-in-out infinite}
        .tish-status-sheet h2{font-size:clamp(23px,6vw,32px);margin:8px 0;color:#c52e27}
        .tish-status-sheet p{margin:7px 0 12px;line-height:1.6}
        .tish-status-items{margin:15px 0;padding:12px;border-top:2px dashed #624d40;border-bottom:2px dashed #624d40;text-align:start;font-weight:700;font-size:.87rem}
        .tish-status-items span{display:block;margin:5px 0}
        .tish-status-actions{display:flex;gap:8px;margin-top:18px}
        .tish-status-actions button{flex:1;padding:12px 10px;cursor:pointer;border:2px solid #251512;font-weight:900;font-family:inherit;background:#c52e27;color:#fff}
        .tish-status-actions button:last-child{background:#f5e5c6;color:#251512}
        .tish-status-mini{position:fixed;z-index:90;bottom:calc(85px + env(safe-area-inset-bottom,0px));left:12px;right:12px;max-width:440px;margin:auto;display:none;align-items:center;justify-content:space-between;gap:10px;padding:12px 16px;background:#f5e5c6;color:#251512;border:2px solid #251512;box-shadow:4px 5px 0 #c52e27;border-radius:9px;cursor:pointer;font:900 .85rem Cairo,Tajawal,Arial,sans-serif}
        .tish-status-mini.is-visible{display:flex}
        @keyframes tish-paper-open{from{opacity:0;transform:translateY(80px) rotate(-5deg) scale(.82)}to{opacity:1;transform:none}}
        @keyframes tish-steam{50%{opacity:.3;transform:translateY(-12px)}}
        @media(prefers-reduced-motion:reduce){.tish-status-sheet,.tish-status-overlay[data-state="preparing"] .tish-status-icon::before{animation:none}}
    
/* TISH-STATUS-POPUP-PAPER-V1 */
#tishStatusOverlay .tish-status-sheet {
    width: min(100%, 400px) !important;
    max-height: 88dvh;
    overflow-y: auto;
    padding: 35px 22px 25px !important;
    border: 0 !important;
    border-radius: 0 !important;
    color: #252525 !important;
    background: #f3eee6
      url('/assets/tish-paper/paper/background.webp')
      center / 540px auto repeat !important;
    box-shadow: 9px 12px 0 #a91f17,
      0 25px 65px #0005 !important;
    clip-path: polygon(
      0 1%, 12% 0, 30% 1%, 51% 0,
      72% 1%, 90% 0, 100% 1%,
      99% 98%, 83% 100%, 60% 99%,
      37% 100%, 14% 99%, 0 100%
    );
}

#tishStatusOverlay .tish-status-sheet::before {
    content: "";
    position: absolute;
    top: 0;
    right: 0;
    left: 0;
    height: 18px;
    background: url('/assets/tish-paper/decor/red-torn-top.svg')
      center / 100% 100% no-repeat;
    pointer-events: none;
}

#tishStatusOverlay .tish-status-icon {
    width: 88px;
    height: 88px;
    border: 3px solid #252525;
    background: #a91f17;
    box-shadow: 4px 5px 0 #25252526;
}

#tishStatusOverlay .tish-status-sheet h2 {
    color: #a91f17 !important;
    font-weight: 950;
    font-size: clamp(24px, 6vw, 31px);
    line-height: 1.4;
    margin: 10px 0 !important;
}

#tishStatusOverlay .tish-status-sheet p {
    line-height: 1.75;
    font-weight: 650;
    margin: 8px 0 12px;
}

#tishStatusOverlay .tish-status-items {
    border-top: 1px dashed #866a53;
    border-bottom: 1px dashed #866a53;
    padding: 13px 4px !important;
    margin: 18px 0 !important;
    line-height: 1.8;
}

#tishStatusOverlay .tish-status-items span {
    margin: 7px 0;
}

#tishStatusOverlay .tish-status-actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-top: 18px;
}

#tishStatusOverlay .tish-status-actions button {
    min-width: 0;
    min-height: 58px;
    padding: 12px 8px;
    border: 0 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    background: transparent
      url('/assets/tish-paper/paper/nav-active.webp')
      center / 100% 100% no-repeat !important;
    color: #fff9ed !important;
    font-family: inherit;
    font-weight: 900;
    font-size: 15px;
    cursor: pointer;
}

#tishStatusOverlay .tish-status-actions button:last-child {
    background: transparent
      url('/assets/tish-paper/paper/category-ivory.webp')
      center / 100% 100% no-repeat !important;
    color: #252525 !important;
}

#tishStatusOverlay .tish-status-close {
    position: absolute;
    top: 17px;
    left: 17px;
    z-index: 3;
    border: 2px solid #252525;
    background: #f3eee6;
    color: #252525;
}

#tishStatusOverlay .tish-status-actions button:focus-visible,
#tishStatusOverlay .tish-status-close:focus-visible {
    outline: 3px solid #a91f17;
    outline-offset: 3px;
}

@media (max-width: 480px) {
    #tishStatusOverlay {
        padding: 14px;
    }
    #tishStatusOverlay .tish-status-sheet {
        padding: 30px 18px 20px !important;
    }
    #tishStatusOverlay .tish-status-actions button {
        font-size: 13px;
        min-height: 56px;
    }
}


/* TISH-TRACK-MINI-PAPER-V4 */
#tishStatusMini.tish-status-mini{
    position: fixed;
    left: 12px;
    right: 12px;
    bottom: calc(74px + env(safe-area-inset-bottom,0px));
    z-index: 60;

    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 12px !important;

    min-height: 58px !important;
    padding: 13px 18px 15px !important;

    background: transparent url('/assets/tish-paper/paper/category-ivory.webp') center/100% 100% no-repeat !important;
    border: 0 !important;
    outline: 0 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    filter: none !important;
    clip-path: none !important;

    color: #2a241f !important;
    text-decoration: none !important;
}

#tishStatusMini.tish-status-mini:hover,
#tishStatusMini.tish-status-mini:active,
#tishStatusMini.tish-status-mini:focus,
#tishStatusMini.tish-status-mini:focus-visible{
    background: transparent url('/assets/tish-paper/paper/category-ivory.webp') center/100% 100% no-repeat !important;
    border: 0 !important;
    outline: 0 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    color: #2a241f !important;
}

#tishStatusMini.tish-status-mini::before,
#tishStatusMini.tish-status-mini::after{
    content: none !important;
    display: none !important;
}

#tishStatusMini.tish-status-mini *{
    box-shadow: none !important;
}

#tishStatusMini.tish-status-mini > span{
    color: #2a241f !important;
    font-size: 12px !important;
    line-height: 1.45 !important;
    font-weight: 800 !important;
}

#tishStatusMini.tish-status-mini > span:first-child{
    flex: 1 1 auto !important;
    min-width: 0 !important;
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
}

#tishStatusMini.tish-status-mini > span:last-child{
    flex: 0 0 auto !important;
    white-space: nowrap !important;
    font-weight: 900 !important;
    text-decoration: none !important;
}

#tishStatusMini.tish-status-mini i,
#tishStatusMini.tish-status-mini .fa-solid,
#tishStatusMini.tish-status-mini .fa{
    color: #b32020 !important;
    font-size: 13px !important;
}

@media (max-width: 480px){
    #tishStatusMini.tish-status-mini{
        left: 10px;
        right: 10px;
        bottom: calc(72px + env(safe-area-inset-bottom,0px));
        min-height: 56px !important;
        padding: 12px 15px 14px !important;
    }

    #tishStatusMini.tish-status-mini > span{
        font-size: 11px !important;
    }
}

</style>
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-paper-v2.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-fonts-v1.css') }}?v=2">

<style>
/* TISH-STATUS-BRAND-LOGO-V1 */
#tishStatusOverlay .tish-status-icon {
    background: transparent !important;
    border: 0 !important;
    box-shadow: none !important;
    width: 110px !important;
    height: 110px !important;
    overflow: visible !important;
}
#tishStatusOverlay .tish-status-icon img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: contain;
    filter: drop-shadow(0 5px 5px rgba(0,0,0,.12));
}
</style>



<style>
/* TISH-TRACK-REFERENCE-V6. Preserve the original menu canvas and reuse the menu's actual torn-paper artwork for card edges.
   All tracking, payment, notification and invoice values remain server driven. */
body#tishTrack{
 --tp-ink:#211e1a;--tp-red:#b51c11;--tp-cream:#fff0df;--tp-green:#21742b;
 --tp-texture:url('data:image/webp;base64,UklGRu4AAABXRUJQVlA4IOIAAABwDACdASqsAKgAPqlUoU8mJKOmoElo0BUJaW7IYARG/OxrF2+wCmgF38o8q5v57t1PejBQWLu9yzY7X3Ru+R+sHCpJ9L3oPIaRAmyihGAhP46omNolNc/lBYz/mct3bT/DDIWdpJCEESoEeQAA/vVGRXwxJGPAup4LuJbf/eg3y4wMKeTy4CbBr+YUL6DlI4BgREvoQYtN1qcnv9eeHIrvubtl+/9Ui5sD0MoStdImzZ6Ea0zN1paKVDAdcmTK7vXYMO1zhRH6WxLHd1IZwZ5vYhqcB2BfyorS6hRwlMEP92AA');
 --tp-paper:var(--tp-texture),linear-gradient(145deg,#fff7ea,#ffeedb);
 --tp-edge:polygon(0.5% 12px,1.2% 5px,3.4% 1px,5.1% 3px,8.7% 0,11.8% 2px,15% 1px,18.5% 4px,21% 2px,25.3% 0,28.6% 3px,32.2% 1px,35.5% 2px,39.3% 0,43.1% 3px,47.4% 1px,51% 4px,54.4% 2px,58.6% 0,61.9% 2px,65% 1px,69.5% 4px,73.1% 0,76.3% 2px,80.8% 1px,84% 4px,87.5% 2px,91.6% 0,94.7% 2px,97.5% 1px,99% 5px,100% 13px,calc(100% - 2px) 11%,100% 20%,calc(100% - 3px) 29%,100% 38%,calc(100% - 1px) 47%,100% 56%,calc(100% - 3px) 65%,100% 74%,calc(100% - 2px) 85%,100% calc(100% - 12px),98.8% calc(100% - 5px),96.5% 100%,93.4% calc(100% - 3px),89.7% calc(100% - 1px),85.4% calc(100% - 4px),82.2% 100%,78.6% calc(100% - 2px),75.1% calc(100% - 1px),70.4% calc(100% - 4px),67% 100%,63.2% calc(100% - 2px),59.7% 100%,55.9% calc(100% - 3px),52.3% calc(100% - 1px),48.1% 100%,43.9% calc(100% - 3px),40.2% calc(100% - 1px),36.6% 100%,33% calc(100% - 4px),29.2% calc(100% - 2px),25.8% 100%,21.3% calc(100% - 2px),18% calc(100% - 1px),14.2% calc(100% - 4px),10.7% 100%,7.4% calc(100% - 2px),3.1% 100%,1% calc(100% - 6px),0 calc(100% - 13px),2px 85%,0 76%,3px 67%,0 58%,1px 49%,0 40%,3px 31%,0 22%,2px 13%); 
 --tp-ticket:polygon(3% 15%,7% 7%,12% 9%,18% 2%,24% 6%,32% 1%,40% 6%,49% 0,57% 4%,65% 1%,73% 6%,81% 0,88% 5%,95% 3%,98% 18%,100% 36%,97% 51%,100% 69%,96% 85%,90% 96%,81% 92%,74% 100%,65% 94%,56% 98%,47% 92%,39% 100%,31% 95%,23% 98%,15% 93%,8% 100%,3% 87%,0 67%,2% 49%,0 30%);
 --tp-shadow:0 4px 3px #78502d45,0 8px 8px #54341e1c;
 --tp-rays:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 40 40'%3E%3Cpath fill='%23b51c11' d='M38 14 17 0 30 17ZM29 23 0 12 19 26ZM26 31 8 35 25 35Z'/%3E%3C/svg%3E");
 background:var(--tish-paper-ivory,#f3eee6) url('/assets/tish-paper/paper/background.webp') center top / 540px auto repeat!important;background-blend-mode:normal!important;
 color:var(--tp-ink);padding-bottom:calc(83px + env(safe-area-inset-bottom,0px))!important;
}
body#tishTrack::before{height:17px!important;background:var(--tp-texture),#bd1c0e!important;background-size:172px 168px,auto!important;background-blend-mode:multiply!important;clip-path:polygon(0 0,100% 0,100% 43%,96% 47%,92% 38%,88% 65%,84% 46%,80% 62%,76% 51%,71% 72%,67% 55%,62% 79%,57% 55%,51% 82%,47% 61%,43% 90%,38% 70%,33% 95%,29% 73%,25% 92%,20% 79%,15% 100%,10% 80%,5% 100%,0 81%);z-index:1}
body#tishTrack::after{content:"";position:absolute;top:8px;left:0;width:76px;height:90px;background:url('/assets/tish-paper/decor/halftone.svg') center/contain no-repeat;pointer-events:none;z-index:0}
body#tishTrack .wrap{width:min(calc(100% - 26px),620px)!important;margin-inline:auto}
body#tishTrack .track-header{position:relative;z-index:2;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 8px 8px;min-height:73px}
body#tishTrack .track-brand{display:flex;align-items:center;gap:10px;min-width:0;flex:1}
body#tishTrack .track-brand img{width:clamp(58px,15.5vw,86px);height:clamp(58px,15.5vw,86px);flex-shrink:0;object-fit:contain;filter:drop-shadow(0 3px 2px #49311e66)}
body#tishTrack .track-brand-copy{min-width:0;display:flex;flex-direction:column;line-height:1.35;text-align:start}
body#tishTrack .track-brand-copy strong{font-size:clamp(16px,4.1vw,24px);font-weight:900;overflow-wrap:anywhere}
body#tishTrack .track-brand-copy small{font-size:clamp(10px,2.9vw,15px);color:#48413a;overflow-wrap:anywhere}
body#tishTrack .track-language{flex-shrink:0;display:flex;align-items:center}
body#tishTrack .track-language .cm-language-switch{border:0!important;border-radius:0!important;min-width:76px;min-height:40px;font-size:clamp(14px,3.4vw,19px)!important;font-weight:900!important;color:var(--tp-ink)!important;background:var(--tp-paper)!important;background-blend-mode:multiply!important;background-size:172px 168px,100%!important;clip-path:var(--tp-ticket);padding:8px 12px!important;text-decoration:underline;text-decoration-color:var(--tp-red);text-underline-offset:5px;text-decoration-thickness:2px}
body#tishTrack #trackingCard,body#tishTrack .track-summary,body#tishTrack #paymentSummary{position:relative;isolation:isolate;background:none!important;border:0!important;border-radius:0!important;clip-path:none!important;box-shadow:none!important;filter:drop-shadow(0 4px 2px #795a3766) drop-shadow(0 7px 5px #6b452526)}
body#tishTrack main.wrap{position:relative;isolation:isolate}
/* Use the exact same transparent torn-paper artwork as the menu cards.
   Mask only the decorative layers, never text, controls or the live content. */
body#tishTrack main.wrap::before{
 content:"";position:absolute;inset:4px -3px 5px;z-index:-2;pointer-events:none;
 background:transparent url('/assets/tish-paper/paper/category-ivory.webp') center/100% 100% no-repeat;
 clip-path:none;
}
body#tishTrack #trackingCard::before,body#tishTrack .track-summary::before,body#tishTrack #paymentSummary::before{content:none}
body#tishTrack #trackingCard::after,body#tishTrack .track-summary::after,body#tishTrack #paymentSummary::after{
 content:"";position:absolute;inset:0;z-index:-1;pointer-events:none;
 background:transparent url('/assets/tish-paper/paper/category-ivory.webp') center/100% 100% no-repeat;
 background-blend-mode:normal;clip-path:none;
}
body#tishTrack #trackingCard::after{
 background:var(--tish-paper-ivory,#f3eee6) url('/assets/tish-paper/paper/background.webp') center top / 540px auto repeat;
 -webkit-mask:url('/assets/tish-paper/paper/category-ivory.webp') center/100% 100% no-repeat;
 mask:url('/assets/tish-paper/paper/category-ivory.webp') center/100% 100% no-repeat;
}
body#tishTrack #trackingCard,body#tishTrack .track-summary,body#tishTrack #paymentSummary{
 filter:drop-shadow(0 5px 3px #54362235);
}
body#tishTrack #trackingCard{padding:12px 12px 18px!important;margin-bottom:10px}
body#tishTrack .orderHead{display:block!important;padding:0 7px!important;border:0!important;text-align:start;line-height:1.35!important}
body#tishTrack .orderHead .eyebrow{display:block!important;font-size:clamp(17px,4.5vw,28px)!important;font-weight:900!important;color:var(--tp-ink)!important}
body#tishTrack .orderHead h1{display:block!important;position:relative;direction:ltr!important;text-align:inherit!important;font-size:clamp(23px,6.1vw,39px)!important;line-height:1.38!important;font-weight:900!important;margin:3px 0 8px!important;letter-spacing:-.04em;overflow-wrap:anywhere!important}
html[dir="rtl"] body#tishTrack .orderHead h1{text-align:right!important}
body#tishTrack .orderHead h1::before{content:"";display:inline-block;width:clamp(17px,6vw,35px);height:27px;background:var(--tp-rays) center/contain no-repeat;vertical-align:middle;margin-right:5px}
body#tishTrack .track-status-trigger{display:block;background:none;border:0;padding:0;margin:0;max-width:100%;font:inherit;color:inherit;cursor:pointer;filter:drop-shadow(0 3px 2px #65402450)}
body#tishTrack #label{display:inline-flex!important;align-items:center;gap:8px;padding:9px 18px!important;max-width:100%;min-height:36px;font-size:clamp(16px,4.5vw,26px)!important;line-height:1.4;font-weight:900;color:#fff9ee!important;border:0!important;border-radius:0!important;background:var(--tp-texture),#b51c11!important;background-blend-mode:multiply!important;background-size:172px 168px,auto!important;clip-path:var(--tp-ticket)}
body#tishTrack #label:is([data-order-state="ready"],[data-order-state="completed"]){background:var(--tp-texture),#268331!important;background-blend-mode:multiply!important}
body#tishTrack #label[data-order-state="loading"]{background:#766354!important}
body#tishTrack .statusDot{display:grid;place-items:center;width:21px;height:21px;flex:0 0 21px;background:#fff9ee;color:#a92417;border-radius:50%;font-size:12px}
body#tishTrack .statusDot i{display:block}
body#tishTrack #label:is([data-order-state="ready"],[data-order-state="completed"]) .statusDot{color:var(--tp-green)}
body#tishTrack .progress{padding:18px 0 19px!important}
body#tishTrack .progressLine{--dot:clamp(39px,11.8vw,66px);display:grid;grid-template-columns:repeat(4,minmax(0,1fr));align-items:start;gap:0}
body#tishTrack .progressLine::before{top:calc(var(--dot)/2);height:2px;background:#342c25}
body#tishTrack .progressItem{font-size:clamp(11px,3.25vw,18px)!important;font-weight:900;line-height:1.4!important;color:#665a50}
body#tishTrack .progressItem.on{color:var(--tp-ink)}
body#tishTrack .progressItem .dot{display:grid;place-items:center;width:var(--dot)!important;height:var(--dot)!important;margin:0 auto 7px!important;position:relative;background:var(--tp-texture),#e6d1b5!important;background-blend-mode:multiply!important;border:2px solid #fff3df!important;border-radius:49% 51% 48% 52% / 52% 48% 53% 47%!important;color:#7c6653!important;font-size:clamp(17px,5vw,29px)!important;box-shadow:0 3px 3px #65402455!important}
body#tishTrack .progressItem.on .dot{background:var(--tp-texture),#bd2115!important;background-blend-mode:multiply!important;color:#fff4e3!important;border:3px solid #fff2df!important;box-shadow:0 2px 0 #f7e8cf,0 4px 4px #51301566!important}
body#tishTrack .progressItem.current .dot{outline:2px solid var(--tp-red);outline-offset:2px}
body#tishTrack .progressItem.current .dot::before{content:"";position:absolute;left:-14px;top:-13px;width:22px;height:23px;background:var(--tp-rays) center/contain no-repeat}
body#tishTrack #paymentSummary{padding:11px 13px!important;margin:0 0 16px!important;display:flex;align-items:center;gap:9px}
body#tishTrack .paymentSummaryIcon{background:var(--tp-paper)!important;background-blend-mode:multiply!important;width:clamp(38px,10.8vw,58px);height:clamp(38px,10.8vw,58px);flex:0 0 clamp(38px,10.8vw,58px);color:var(--tp-red)!important;box-shadow:0 3px 3px #65402444;font-size:clamp(20px,5vw,28px)}
body#tishTrack .paymentSummaryText{gap:2px}
body#tishTrack .paymentSummaryText strong{font-size:clamp(16px,4.6vw,27px)!important;font-weight:900;line-height:1.3}
body#tishTrack .paymentSummaryText small{font-size:clamp(10px,3vw,16px)!important;color:#4e463d;line-height:1.5}
body#tishTrack #paymentBadge{max-width:45%;border-radius:0!important;clip-path:var(--tp-ticket);font-size:clamp(14px,4vw,23px)!important;padding:9px 15px!important;line-height:1.4}
body#tishTrack #paymentBadge[data-payment-state="paid"]{background:var(--tp-texture),#21802b!important;background-blend-mode:multiply!important;color:#fff!important;transform:rotate(-4deg)}
body#tishTrack #paymentBadge:is([data-payment-state="partial"],[data-payment-state="review"]){background:#fff1d5!important;color:#875300!important}
body#tishTrack #paymentBadge[data-payment-state="refunded"]{background:#e9e9f4!important;color:#514d79!important}
body#tishTrack .notice.show,body#tishTrack .customer-status-alert.show{margin:0 0 11px!important;padding:12px 10px!important;border:0!important;border-radius:0!important;background:var(--tp-texture),#ffdad3!important;background-blend-mode:multiply!important;clip-path:var(--tp-edge);color:#941b14!important;font-size:clamp(13px,3.8vw,22px)!important;text-align:center;font-weight:900;line-height:1.55!important}
body#tishTrack #eta.show{background:var(--tp-texture),#f7e7d0!important;background-blend-mode:multiply!important;color:#704a24!important;padding:7px 9px!important;font-size:clamp(10px,2.8vw,15px)!important}
body#tishTrack .customer-alert-controls{display:grid!important;justify-items:center;gap:5px;margin:13px 0 16px!important;text-align:center}
body#tishTrack .customer-alert-controls button{width:auto;max-width:100%;background:var(--tp-paper)!important;background-blend-mode:multiply!important;border:2px solid var(--tp-red)!important;border-radius:14px 11px 13px 10px!important;min-height:43px!important;padding:7px 13px!important;font-size:clamp(14px,3.85vw,22px)!important;font-weight:900;line-height:1.4;color:#981b13!important;box-shadow:0 3px 3px #67432944}
body#tishTrack .customer-alert-controls button[hidden]{display:none!important}
body#tishTrack .customer-alert-controls small{font-size:clamp(10px,2.9vw,15px)!important;color:#51473e;line-height:1.5}
body#tishTrack .track-summary{padding:0 14px 13px;margin:0}
body#tishTrack .track-summary .sectionTitle{position:relative;display:flex;align-items:center;justify-content:space-between;gap:6px;margin:0 -5px!important;padding:10px 5px 10px!important;border-bottom:1px solid #cab69e;box-shadow:0 4px 4px #62402516}
body#tishTrack .track-summary .sectionTitle h2{font-size:clamp(19px,5vw,30px)!important;font-weight:900;line-height:1.35}
body#tishTrack .track-summary .live{font-size:clamp(10px,2.7vw,14px)!important;color:#51473e}
body#tishTrack .track-summary .items{border-top:0!important}
body#tishTrack .track-summary .item{align-items:center;padding:11px 0!important;gap:10px;border-color:#b9a58d!important;font-weight:900;font-size:clamp(15px,4.1vw,24px)!important;line-height:1.4!important}
body#tishTrack .track-summary .item>span{min-width:0;overflow-wrap:anywhere}
body#tishTrack .track-summary .item small{font-size:clamp(10px,2.9vw,16px)!important;color:#544a40;font-weight:600;margin-top:2px}
body#tishTrack .track-summary .item b{color:#a21b13!important;font-size:clamp(17px,4.5vw,27px)!important;font-weight:900;flex-shrink:0}
body#tishTrack .track-summary .total{display:flex;align-items:center;justify-content:space-between;padding-top:12px!important;font-size:clamp(20px,5.1vw,31px)!important;font-weight:900}
body#tishTrack .track-summary .total span:last-child{color:#fff9eb!important;background:var(--tp-texture),#c51d0f!important;background-blend-mode:multiply!important;clip-path:var(--tp-ticket);padding:10px 17px!important;white-space:nowrap;font-weight:900}
body#tishTrack .footerActions{margin:12px 0 0!important}
body#tishTrack .bottom-nav{width:min(100%,646px)!important;min-height:calc(71px + env(safe-area-inset-bottom,0px))!important;padding:5px 5px calc(5px + env(safe-area-inset-bottom,0px))!important;background:var(--tp-texture),#292725!important;background-blend-mode:multiply!important;clip-path:polygon(0 3px,3% 0,7% 3px,12% 0,19% 3px,25% 0,32% 3px,39% 1px,45% 4px,52% 0,60% 3px,67% 1px,74% 3px,82% 0,90% 3px,96% 0,100% 3px,100% 100%,0 100%)}
body#tishTrack .bottom-nav .nav-btn{height:60px!important;min-height:60px!important;gap:4px!important;font-size:clamp(12px,3.25vw,18px)!important}
body#tishTrack .bottom-nav .nav-btn .tish-paper-nav-icon{display:block!important;width:clamp(27px,7.6vw,40px)!important;height:clamp(27px,7.6vw,40px)!important;max-height:none!important}
body#tishTrack .bottom-nav .nav-btn.active{background:var(--tp-texture),#c51d0f!important;background-blend-mode:multiply!important;clip-path:var(--tp-ticket)}
body#tishTrack #pwaInstallBtn.tish-paper-pin-control:not([hidden]){display:none!important}
@media(max-width:350px){body#tishTrack .wrap{width:calc(100% - 16px)!important}body#tishTrack #trackingCard{padding-inline:9px!important}body#tishTrack .track-summary{padding-inline:9px}body#tishTrack .track-brand{gap:6px}}
@media(min-width:600px){body#tishTrack #trackingCard{padding:20px!important}body#tishTrack .progress{padding-block:28px!important}body#tishTrack .track-summary{padding:0 20px 16px}}
@media print{body#tishTrack #trackingCard,body#tishTrack .track-summary{filter:none}}

/* All paper finishes use the same seamless tile size; no grid from stretching/repeat boundaries. */
body#tishTrack :is(.statusDot,.progressItem .dot,.paymentSummaryIcon,#paymentBadge,.notice.show,.customer-status-alert.show,.customer-alert-controls button,.total span:last-child,.bottom-nav,.bottom-nav .nav-btn.active){background-size:172px 168px,auto!important}
body#tishTrack .track-language{filter:drop-shadow(0 3px 2px #65402444)}
body#tishTrack .customer-alert-controls #enableOrderAlerts{position:relative;justify-self:start;margin-inline-start:4px;filter:drop-shadow(0 2px 2px #795a3726)}
body#tishTrack .customer-alert-controls #enableOrderAlerts::before{content:"";position:absolute;left:-25px;top:-9px;width:23px;height:25px;background:var(--tp-rays) center/contain no-repeat;pointer-events:none}
html[dir="ltr"] body#tishTrack .customer-alert-controls #enableOrderAlerts::before{left:auto;right:-25px;transform:scaleX(-1)}
body#tishTrack .customer-alert-controls small{text-align:start;margin-inline:6px}
body#tishTrack .track-summary .sectionTitle{background:var(--tp-texture),#fff1df;background-size:172px 168px,auto;background-blend-mode:multiply}
body#tishTrack .bottom-nav{min-height:calc(74px + env(safe-area-inset-bottom,0px))!important}
body#tishTrack .bottom-nav .nav-btn{height:63px!important}
@media(max-width:360px){body#tishTrack .customer-alert-controls #enableOrderAlerts::before{display:none}body#tishTrack .track-brand img{width:52px;height:52px}}
</style>

</head>
<body id="tishTrack" class="tish-paper tish-paper-track">
    @if($appDownloadUrl || $websiteUrl)
        <div class="appBar">
            <div class="wrap appBarInner">
                <span class="appHint">
                    <span aria-hidden="true">📱</span>
                    <span>اطلب أسرع من تطبيق {{ $brandName }}</span>
                </span>
                <div class="appLinks">
                    @if($websiteUrl)
                        <a class="appLink websiteLink" href="{{ $websiteUrl }}" target="_blank" rel="noopener">
                            @if($brandLogo)
                                <img src="{{ $brandLogo }}" alt="">
                            @endif
                            <span>الموقع</span>
                        </a>
                    @endif
                    @if($appDownloadUrl)
                        <a class="appLink downloadLink" href="{{ $appDownloadUrl }}" target="_blank" rel="noopener">
                            تحميل التطبيق <span aria-hidden="true">↓</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endif
    <header class="wrap track-header">
        <a class="track-brand" href="{{ route('customer-menu.show', $location->code) }}" aria-label="الرئيسية">
            <img src="{{ asset('assets/tish-paper/brand/logo-disc.webp') }}" alt="" width="65" height="65">
            <span class="track-brand-copy">
                <strong>{{ $branding['name'] ?? $brandName }}</strong>
                <small>{{ $location->name }}</small>
            </span>
        </a>
        <div class="track-language" data-cm-language-host></div>
    </header>

    <main class="wrap" style="padding-bottom:12px">
        <section class="card" id="trackingCard">
            <div class="orderHead">
                <div>
                    <span class="eyebrow">تفاصيل الطلب</span>
                    <h1><bdi>{{ $order->order_number }}#</bdi></h1>
                </div>
                <button class="track-status-trigger" id="tishStatusMini" type="button" aria-haspopup="dialog" aria-controls="tishStatusOverlay">
                <span class="status" id="label" data-order-state="loading" aria-live="polite">
                    <span class="statusDot" aria-hidden="true"><i id="trackStateIcon" class="fa-solid fa-bag-shopping"></i></span>
                    <span id="tishStatusMiniText">جاري تحديث الحالة</span>
                </span>
                </button>
            </div>

            <div class="progress" aria-label="مراحل الطلب">
                <div class="progressLine">
                    <div class="progressItem on" data-stage="0">
                        <span class="dot"><i class="fa-solid fa-bag-shopping"></i></span>
                        <span>تم الاستلام</span>
                    </div>
                    <div class="progressItem" data-stage="1">
                        <span class="dot"><i class="fa-solid fa-check"></i></span>
                        <span>تم التأكيد</span>
                    </div>
                    <div class="progressItem" data-stage="2">
                        <span class="dot"><i class="fa-solid fa-fire-burner"></i></span>
                        <span>قيد التحضير</span>
                    </div>
                    <div class="progressItem" data-stage="3">
                        <span class="dot"><i class="fa-solid fa-house"></i></span>
                        <span>جاهز</span>
                    </div>
                </div>
            </div>

            <div class="paymentSummary" id="paymentSummary" aria-live="polite" aria-atomic="true">
                <div class="paymentSummaryIcon" aria-hidden="true"><i class="fa-solid fa-credit-card"></i></div>
                <div class="paymentSummaryText">
                    <strong>حالة الدفع</strong>
                    <small id="paymentDescription">يتم تحديث حالة الدفع تلقائيًا.</small>
                </div>
                <span class="paymentSummaryBadge" id="paymentBadge" data-payment-state="unknown">جاري التحديث</span>
            </div>

            <div class="notice" id="statusDescription"></div>
            <div class="notice" id="eta"></div>
            <div class="customer-alert-controls">
                <button type="button" id="enableOrderAlerts"><i class="fa-solid fa-bell"></i> تفعيل صوت وإشعارات الطلب</button>
                <button type="button" id="disableOrderPush" hidden>إيقاف إشعارات الخلفية لهذا الطلب</button>
                <small id="alertPermissionHint" aria-live="polite">ستصلك التنبيهات أثناء فتح صفحة الطلب.</small>
            </div>
            <div class="customer-status-alert" id="customerStatusAlert" role="status" aria-live="polite"></div>

            <div class="notice" id="customerMessage"></div>

            <div class="track-summary">
            <div class="sectionTitle">
                <h2>ملخص الطلب</h2>
                <span class="live">التحديث تلقائيًا</span>
            </div>
            <div class="items">
                @foreach($order->items as $item)
                    <div class="item">
                        <span>
                            {{ $item->product ? \App\Support\CustomerMenuLanguage::name($item->product->name, $item->product->name_ar) : $item->product_name }}
                            <small>{{ \App\Support\CustomerMenuLanguage::isEnglish() ? 'Quantity' : 'الكمية' }}: {{ number_format((float) $item->quantity, 0) }}</small>
                        </span>
                        <b><bdi>{{ $menuCurrencySymbol }} {{ number_format($item->line_total,2) }}</bdi></b>
                    </div>
                @endforeach
            </div>
            <div class="total">
                <span>الإجمالي</span>
                <span><bdi>{{ $menuCurrencySymbol }} {{ number_format($order->total_amount,2) }}</bdi></span>
            </div>

            </div>

            @if($order->invoice)
            <div class="footerActions">
                    <button class="action secondary" id="saveInvoiceImage" type="button"><i class="fa-solid fa-image"></i> حفظ الفاتورة كصورة</button>
                    <a class="action secondary" href="{{ route('customer-menu.invoice', ['token' => $order->public_token]) }}">
                        عرض الفاتورة
                    </a>
            </div>
            @endif
        </section>
    </main>


    <div class="tish-status-overlay" id="tishStatusOverlay" role="dialog" aria-modal="true" aria-labelledby="tishPopupTitle" aria-hidden="true">
        <div class="tish-status-sheet">
            <button type="button" class="tish-status-close" id="tishPopupClose" aria-label="إغلاق">×</button>
            <div class="tish-status-icon" aria-hidden="true"><img src="/assets/tish-paper/brand/tish-logo.svg" alt=""></div>
            <small>طلب #{{ $order->order_number }}</small>
            <h2 id="tishPopupTitle">حالة طلبك</h2>
            <p id="tishPopupDescription"></p>
            <div class="tish-status-items">
                @foreach($order->items as $item)
                    <span>{{ $item->quantity }} × {{ $item->product ? \App\Support\CustomerMenuLanguage::name($item->product->name, $item->product->name_ar) : $item->product_name }}</span>
                @endforeach
            </div>
            <p id="tishPopupEta"></p>
            <div class="tish-status-actions">
                <button type="button" id="tishPopupFollow">متابعة طلبي</button>
                <button type="button" id="tishPopupDismiss">إغلاق</button>
            </div>
        </div>
    </div>

    {{-- Tracking belongs to My Orders; all destinations use the order's branch. --}}
    @include('customer-menu.partials.bottom-nav', ['activeNav' => 'orders'])
    @include('customer-menu.partials.nav-badge-sync')
    {{-- Register the service worker without adding a floating install control to tracking. --}}
    <script>
    if ('serviceWorker' in navigator && window.isSecureContext) {
        navigator.serviceWorker.register('/sw.js', {updateViaCache:'none'})
            .then(registration => registration.update()).catch(() => {});
    }
    </script>
    @if($order->invoice)
        <script src="{{ asset('assets/js/customer-menu/tish-invoice-image-v2.js') }}?v=3"></script>
    @endif

    <script>
        const menuT = text => window.CustomerMenuI18n.t(text);
        const menuF = (key, values) => window.CustomerMenuI18n.format(key, values);
        const url = @json(route('customer-menu.status',$order->public_token));
        const initialStatus = @json($statusPayload ?? null);
        const label = document.querySelector('#label span:last-child');
        const paymentBadge = document.getElementById('paymentBadge');
        const paymentDescription = document.getElementById('paymentDescription');
        const eta = document.querySelector('#eta');
        const statusDescription = document.querySelector('#statusDescription');
        const customerMessage = document.querySelector('#customerMessage');
        const card = document.querySelector('#trackingCard');
        const stages = [...document.querySelectorAll('[data-stage]')];

        const statusText = {
            pending: 'تم استلام الطلب',
            received: 'تم استلام الطلب',
            confirmed: 'تم تأكيد الطلب',
            accepted: 'تم تأكيد الطلب',
            preparing: 'جاري تجهيز طلبك',
            ready: 'طلبك جاهز',
            completed: 'تم إكمال الطلب',
            cancelled: 'تم إلغاء الطلب',
            canceled: 'تم إلغاء الطلب'
        };

        const alertsButton = document.getElementById('enableOrderAlerts');
        const alertsHint = document.getElementById('alertPermissionHint');
        const alertBox = document.getElementById('customerStatusAlert');
        const orderNumber = @json((string) $order->order_number);
        const alertKey = 'tish-order-alerts:' + orderNumber;
        let lastNotifiedState = null;
        let lastNotifiedMessage = null;
        let alertsEnabled = sessionStorage.getItem(alertKey) === 'enabled';
        let audioContext = null;
        const pushKeyUrl = @json(route('customer-menu.push.public-key'));
        const pushSaveUrl = @json(route('customer-menu.push.save', $order->public_token));
        const pushDeleteUrl = @json(route('customer-menu.push.delete', $order->public_token));
        const csrfToken = @json(csrf_token());
        const pushEnabledKey = 'tish-push:' + url;
        let pushSubscribed = localStorage.getItem(pushEnabledKey) === 'yes';
        let pushErrorMessage = '';
        const disablePushButton = document.getElementById('disableOrderPush');
        function base64UrlToBytes(base64) {
            const binary = atob(base64.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - base64.length % 4) % 4));
            return Uint8Array.from(binary, char => char.charCodeAt(0));
        }
        async function enableTishBackgroundPush() {
            if (!('serviceWorker' in navigator) || !('PushManager' in window) || !window.isSecureContext) {
                throw new Error('هذا الجهاز لا يدعم إشعارات الخلفية على هذه الصفحة.');
            }
            const configResponse = await fetch(pushKeyUrl, {cache:'no-store', credentials:'same-origin'});
            if (!configResponse.ok) throw new Error('تعذر قراءة إعدادات إشعارات الخلفية.');
            const config = await configResponse.json();
            if (!config.enabled || !config.public_key) throw new Error('إشعارات الخلفية تحتاج إعداد مفاتيح VAPID على السيرفر.');
            if (!('Notification' in window)) throw new Error('إشعارات النظام غير مدعومة على هذا الجهاز.');
            if (Notification.permission !== 'granted') {
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') throw new Error('لم يتم منح إذن الإشعارات.');
            }
            const registration = await navigator.serviceWorker.register('/sw.js', {scope:'/'});
            const ready = await navigator.serviceWorker.ready;
            const subscribed = await ready.pushManager.getSubscription();
            const subscription = subscribed || await ready.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: base64UrlToBytes(config.public_key)
            });
            const json = subscription.toJSON();
            const response = await fetch(pushSaveUrl, {
                method:'POST', credentials:'same-origin',
                headers:{'Content-Type':'application/json', Accept:'application/json', 'X-CSRF-TOKEN':csrfToken},
                body:JSON.stringify({endpoint:json.endpoint, keys:json.keys})
            });
            if (!response.ok) throw new Error('تعذر حفظ اشتراك الجهاز في السيرفر.');
            pushSubscribed = true;
            localStorage.setItem(pushEnabledKey, 'yes');
            refreshAlertsHint();
        }

        const tishOverlay = document.getElementById('tishStatusOverlay');
        const tishMini = document.getElementById('tishStatusMini');
        const tishPopupTitle = document.getElementById('tishPopupTitle');
        const tishPopupDescription = document.getElementById('tishPopupDescription');
        const tishPopupEta = document.getElementById('tishPopupEta');
        let lastPopupState = '';
        let tishDeadlineMs = null;
        let tishClockOffsetMs = 0;
        let tishTimerDone = false;
        const tishTimeFormat = seconds => {
            const minutes = Math.floor(seconds / 60);
            return String(minutes).padStart(2, '0') + ':' + String(seconds % 60).padStart(2, '0');
        };
        function renderTishCountdown() {
            if (!tishDeadlineMs) return;
            const state = String(window.tishLatestTracking?.state || '').toLowerCase();
            if (['ready', 'completed', 'cancelled', 'canceled'].includes(state)) return;
            const remaining = Math.max(0, Math.ceil((tishDeadlineMs - (Date.now() - tishClockOffsetMs)) / 1000));
            const label = remaining ? '⏱️ ' + menuF('الوقت التقديري المتبقي: {time}', {time: tishTimeFormat(remaining)})
                : '⏱️ ' + menuT('انتهى الوقت التقديري، ننتظر تحديث المطبخ');
            const mainEta = document.getElementById('eta');
            mainEta.textContent = label;
            mainEta.classList.add('show');
            tishPopupEta.textContent = label;
            if (!remaining && !tishTimerDone) tishTimerDone = true;
        }
        setInterval(renderTishCountdown, 1000);
        function setTishDeadline(data) {
            const serverNow = Date.parse(data.server_time || '');
            if (Number.isFinite(serverNow)) tishClockOffsetMs = Date.now() - serverNow;
            const date = typeof data.estimated_ready_at === 'string'
                ? Date.parse(data.estimated_ready_at) : NaN;
            tishDeadlineMs = Number.isFinite(date) ? date : null;
            if (tishDeadlineMs) renderTishCountdown();
        }
        function closeTishPopup() {
            tishOverlay.classList.remove('is-open');
            tishOverlay.setAttribute('aria-hidden','true');
            tishMini.classList.add('is-visible');
            tishMini.focus();
        }
        function openTishPopup(data) {
            const state = String(data.state || 'received').toLowerCase();
            tishOverlay.dataset.state = state;
            tishPopupTitle.textContent = ({
                received:'وصلنا طلبك!', accepted:'اعتمدنا طلبك!',
                preparing:'طلبك على السماش 🔥',ready:'طلبك جاهز، بالعافية!',
                completed:'بالعافية عليك!',cancelled:'تم إلغاء الطلب'
            })[state] || data.label || 'تحديث طلبك';
            tishPopupDescription.textContent = data.customer_message || data.description || '';
            const minutes = Number(data.estimated_ready_minutes);
            tishPopupEta.textContent = ['ready','completed','cancelled'].includes(state)
                ? '' : (Number.isFinite(minutes) && minutes > 0
                    ? '⏱️ ' + menuF('الوقت التقديري: حوالي {minutes} دقيقة (غير مضمون)', {minutes})
                    : 'بانتظار تحديث وقت التحضير من المطعم');
            if (tishDeadlineMs) renderTishCountdown();
            tishOverlay.classList.add('is-open');
            tishOverlay.setAttribute('aria-hidden','false');
            tishMini.classList.remove('is-visible');
            document.getElementById('tishPopupClose').focus();
        }
        ['tishPopupClose','tishPopupDismiss','tishPopupFollow'].forEach(id => {
            document.getElementById(id)?.addEventListener('click',closeTishPopup);
        });
        tishMini?.addEventListener('click',() => {
            if (window.tishLatestTracking) openTishPopup(window.tishLatestTracking);
        });
        document.addEventListener('keydown',event => {
            if (event.key === 'Escape' && tishOverlay.classList.contains('is-open')) closeTishPopup();
        });
        tishOverlay?.addEventListener('click',event => {
            if (event.target === tishOverlay) closeTishPopup();
        });

        // An original, synthesized smash sizzle; no external audio assets.
        function playTishSmash() {
            if (!alertsEnabled) return;
            try {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx) return;
                audioContext = audioContext || new Ctx();
                if (audioContext.state !== 'running') return;
                const now = audioContext.currentTime;
                const duration = .34;
                const buffer = audioContext.createBuffer(1, Math.ceil(audioContext.sampleRate * duration), audioContext.sampleRate);
                const channel = buffer.getChannelData(0);
                for (let i = 0; i < channel.length; i++) {
                    const t = i / audioContext.sampleRate;
                    channel[i] = (Math.random() * 2 - 1) * Math.exp(-t * 13);
                }
                const noise = audioContext.createBufferSource();
                noise.buffer = buffer;
                const filter = audioContext.createBiquadFilter();
                filter.type = 'lowpass';
                filter.frequency.setValueAtTime(1300, now);
                filter.frequency.exponentialRampToValueAtTime(240, now + duration);
                const gain = audioContext.createGain();
                gain.gain.setValueAtTime(.0001, now);
                gain.gain.exponentialRampToValueAtTime(.34, now + .015);
                gain.gain.exponentialRampToValueAtTime(.0001, now + duration);
                noise.connect(filter).connect(gain).connect(audioContext.destination);
                noise.start(now);
                noise.stop(now + duration);
            } catch (error) { console.warn('TISH smash unavailable:', error); }
        }

        function playOrderChime() {
            if (!alertsEnabled) return;
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) return;
                audioContext = audioContext || new AudioContext();
                if (audioContext.state === 'suspended') audioContext.resume().catch(() => {});
                if (audioContext.state !== 'running') return;
                [0, 0.16].forEach((delay, index) => {
                    const oscillator = audioContext.createOscillator();
                    const gain = audioContext.createGain();
                    oscillator.type = 'sine';
                    oscillator.frequency.value = index ? 880 : 660;
                    gain.gain.setValueAtTime(.0001, audioContext.currentTime + delay);
                    gain.gain.exponentialRampToValueAtTime(.13, audioContext.currentTime + delay + .015);
                    gain.gain.exponentialRampToValueAtTime(.0001, audioContext.currentTime + delay + .22);
                    oscillator.connect(gain).connect(audioContext.destination);
                    oscillator.start(audioContext.currentTime + delay);
                    oscillator.stop(audioContext.currentTime + delay + .23);
                });
            } catch (error) { console.warn('Audio notification unavailable:', error); }
        }

        function refreshAlertsHint() {
            if (!alertsButton || !alertsHint) return;
            alertsButton.textContent = menuT(alertsEnabled ? '🔔 إشعارات الطلب مفعّلة' : '🔔 تفعيل صوت وإشعارات الطلب');
            if (disablePushButton) disablePushButton.hidden = !pushSubscribed;
            alertsHint.textContent = menuT(pushErrorMessage || (alertsEnabled
                ? ('Notification' in window && Notification.permission === 'granted'
                    ? (pushSubscribed ? 'الصوت وإشعارات الخلفية مفعّلان على هذا الجهاز.' : 'الصوت مفعّل. إشعارات الخلفية تحتاج إعدادًا إضافيًا.')
                    : 'الصوت مفعّل. إشعارات الجهاز تحتاج إذن المتصفح.')
                : 'فعّل التنبيهات لتسمع صوتًا عند تغيّر حالة الطلب.'));
        }

        alertsButton?.addEventListener('click', async () => {
            alertsEnabled = true;
            sessionStorage.setItem(alertKey, 'enabled');
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (AudioContext) {
                    audioContext = audioContext || new AudioContext();
                    await audioContext.resume();
                }
            } catch (error) { console.warn('Audio could not start:', error); }
            if ('Notification' in window && Notification.permission === 'default' && window.isSecureContext) {
                try { await Notification.requestPermission(); } catch (error) {}
            }
            pushErrorMessage = '';
            try {
                await enableTishBackgroundPush();
            } catch (error) {
                pushErrorMessage = String(error.message || error);
            }
            refreshAlertsHint();
        });
        disablePushButton?.addEventListener('click', async () => {
            try {
                const subscription = await (await navigator.serviceWorker.ready).pushManager.getSubscription();
                if (subscription) {
                    const response = await fetch(pushDeleteUrl, {
                        method:'DELETE', credentials:'same-origin',
                        headers:{'Content-Type':'application/json', Accept:'application/json', 'X-CSRF-TOKEN':csrfToken},
                        body:JSON.stringify({endpoint:subscription.endpoint})
                    });
                    if (!response.ok) throw new Error('تعذر إلغاء اشتراك هذا الطلب.');
                }
                pushSubscribed = false;
                localStorage.removeItem(pushEnabledKey);
                pushErrorMessage = '';
            } catch (error) {
                pushErrorMessage = String(error.message || error);
            }
            refreshAlertsHint();
        });
        refreshAlertsHint();

        function notifyStatusChange(data) {
            const state = String(data.state || '').toLowerCase();
            const message = String(data.customer_message || '');
            // Initial render is not a change and must never play a false alert.
            if (lastNotifiedState === null) {
                lastNotifiedState = state;
                lastNotifiedMessage = message;
                lastPopupState = state;
                window.tishLatestTracking = data;
                tishMini.classList.add('is-visible');
                document.getElementById('tishStatusMiniText').textContent = menuT(data.label || 'متابعة الطلب');
                return;
            }
            if (state === lastNotifiedState && message === lastNotifiedMessage) return;
            lastNotifiedState = state;
            lastNotifiedMessage = message;
            // Only real state transitions open the paper card; a customer message alone
            // still uses the existing text alert without replaying the status animation.
            if (state !== lastPopupState) openTishPopup(data);
            lastPopupState = state;
            const title = menuT(data.label || statusText[state] || 'تحديث طلبك');
            alertBox.textContent = title + (message ? ' — ' + menuF('رسالة من الفرع: {message}', {message}) : '');
            alertBox.classList.add('show');
            if (alertsEnabled) {
                if (state === 'preparing') {
                    // localStorage allows tabs to deduplicate audible transition.
                    const soundKey = 'tish-smash:' + url + ':' + (data.event_at || state);
                    if (localStorage.getItem(soundKey) !== 'played') {
                        localStorage.setItem(soundKey, 'played');
                        playTishSmash();
                    }
                } else {
                    playOrderChime();
                }
                if (navigator.vibrate) navigator.vibrate([120, 60, 120]);
                if (!pushSubscribed && 'Notification' in window && Notification.permission === 'granted') {
                    try {
                        const notification = new Notification(title, {
                            body: menuT(message || data.description || 'تم تحديث حالة طلبك.'),
                            tag: 'tish-order-status-' + orderNumber,
                        });
                        notification.onclick = () => { window.focus(); notification.close(); };
                    } catch (error) { console.warn('System notification unavailable:', error); }
                }
            }
        }

        function updateTracking(data) {
            window.tishLatestTracking = data;
            setTishDeadline(data);
            document.getElementById('tishStatusMiniText').textContent = menuT(data.label || 'متابعة الطلب');
            // The backend already computes the correct step (1=received,
            // 2=accepted, 3=preparing, 4=ready/completed) from the real
            // kitchen ticket states — trust it directly instead of
            // re-guessing progress from the order's own status field,
            // which never actually changes to "preparing"/"ready" itself.
            const state = String(data.state || 'received').toLowerCase();
            const cancelled = ['cancelled', 'canceled'].includes(state);
            // State is authoritative if a stale or inconsistent step value is returned.
            const stepsByState = {
                pending: 1, received: 1, confirmed: 2, accepted: 2,
                preparing: 3, ready: 4, completed: 4
            };
            const serverStep = Number(data.step);
            const resolvedStep = stepsByState[state] ?? (Number.isFinite(serverStep) ? serverStep : 1);
            const stage = cancelled ? 0 : Math.max(0, Math.min(3, resolvedStep - 1));

            stages.forEach(item => {
                const value = Number(item.dataset.stage);
                item.classList.toggle('on', !cancelled && value <= stage);
                item.classList.toggle('current', !cancelled && value === stage);
                if (!cancelled && value === stage) item.setAttribute('aria-current', 'step');
                else item.removeAttribute('aria-current');
            });

            card.classList.toggle('cancelled', cancelled);
            document.getElementById('label').dataset.orderState = state;
            const stateIcon = document.getElementById('trackStateIcon');
            stateIcon.className = 'fa-solid ' + ({
                pending:'fa-bag-shopping', received:'fa-bag-shopping',
                accepted:'fa-check', confirmed:'fa-check', preparing:'fa-fire-burner',
                ready:'fa-check', completed:'fa-check', cancelled:'fa-xmark', canceled:'fa-xmark'
            }[state] || 'fa-clock');
            label.textContent = menuT(data.label || statusText[state] || 'جاري تجهيز طلبك');
            statusDescription.textContent = menuT(data.description || '');
            statusDescription.classList.toggle('show', Boolean(data.description));

            customerMessage.textContent = data.customer_message
                ? menuF('رسالة من الفرع: {message}', {message:data.customer_message})
                : '';
            customerMessage.classList.toggle('show', Boolean(data.customer_message));

            const paymentStatus = String(data.payment_status || '').toLowerCase();
            const paymentArrangement = String(data.payment_arrangement || '').toLowerCase();
            // Only use server-confirmed payment_status; order preparation never implies paid.
            const paymentStates = {
                payment_pending: ['في انتظار الدفع', 'لم يتم تأكيد الدفع بعد.', 'pending'],
                partially_paid: ['مدفوع جزئيًا', 'تم استلام جزء من المبلغ.', 'partial'],
                paid: ['مدفوع', 'تم تأكيد دفع الطلب.', 'paid'],
                pending_payment_verification: ['بانتظار التحقق', 'إثبات الدفع قيد المراجعة لدى المطعم.', 'review'],
                partially_refunded: ['مسترجع جزئيًا', 'تم استرجاع جزء من المبلغ.', 'refunded'],
                refunded: ['مسترجع', 'تم استرجاع المبلغ.', 'refunded']
            };
            const stateInfo = paymentStates[paymentStatus] || ['الحالة غير متاحة', 'يرجى التواصل مع المطعم لتأكيد حالة الدفع.', 'unknown'];
            const payOnPickup = paymentStatus === 'payment_pending' && paymentArrangement === 'pay_on_pickup';
            paymentBadge.textContent = menuT(stateInfo[0]);
            paymentBadge.dataset.paymentState = stateInfo[2];
            paymentDescription.textContent = menuT(payOnPickup ? 'الدفع عند الاستلام — لم يتم تحصيل المبلغ بعد.' : stateInfo[1]);

            const minutes = Number(data.estimated_ready_minutes);
            const showEta = !cancelled && Number.isFinite(minutes) && minutes > 0;
            if (!tishDeadlineMs) {
                eta.textContent = showEta ? '⏱️ ' + menuF('الوقت المتوقع لتجهيز طلبك: حوالي {minutes} دقيقة', {minutes}) : '';
                eta.classList.toggle('show', showEta);
            } else if (cancelled || ['ready', 'completed'].includes(state)) {
                eta.textContent = '';
                eta.classList.remove('show');
                tishPopupEta.textContent = '';
            } else {
                renderTishCountdown();
            }

            notifyStatusChange(data);
        }

        let polling = false;
        async function poll() {
            if (polling) return;
            polling = true;
            try {
                const endpoint = new URL(url, location.href);
                endpoint.searchParams.set('_fresh', Date.now().toString());
                const response = await fetch(endpoint.toString(), {
                    method: 'GET',
                    headers: {Accept:'application/json', 'Cache-Control':'no-cache'},
                    cache: 'no-store',
                    credentials: 'same-origin'
                });
                if (!response.ok) throw new Error('Status HTTP ' + response.status);
                const data = await response.json();
                updateTracking(data);
            } catch (error) {
                console.warn('Order tracking status refresh failed:', error);
                // Keep the last successfully received status visible during a temporary outage.
            } finally {
                polling = false;
            }
        }

        // Export an actual PNG receipt locally, without screenshot permissions,
        // cross-origin libraries or server-side file storage.
        @if($order->invoice)
        const saveInvoiceButton = document.getElementById('saveInvoiceImage');
        @php
            // Keep complex PHP expressions out of Blade's @json directive.
            // Blade's directive parser cannot safely balance nested callbacks.
            $receiptInvoice = $order->invoice;
            $receiptLines = $receiptInvoice->items->isNotEmpty()
                ? $receiptInvoice->items->map(fn ($item) => [
                    'name' => $item->description ?: ($item->product?->name_ar ?? $item->product?->name ?? 'صنف'),
                    'quantity' => (string) $item->quantity,
                    'unit_price' => number_format((float) $item->unit_price, 2),
                    'total' => number_format((float) $item->line_total, 2),
                ])->values()->all()
                : $order->items->map(fn ($item) => [
                    'name' => $item->product_name ?: 'صنف',
                    'quantity' => (string) $item->quantity,
                    'unit_price' => number_format((float) $item->unit_price, 2),
                    'total' => number_format((float) $item->line_total, 2),
                ])->values()->all();
            $receiptData = [
                'brand' => $brandName,
                'branch' => $order->location->name,
                'number' => (string) ($receiptInvoice->invoice_number ?? $order->order_number),
                'order_number' => (string) $order->order_number,
                'date' => ($receiptInvoice->issued_at ?? $order->created_at)?->format('Y-m-d H:i'),
                'items' => $receiptLines,
                'subtotal' => number_format((float) $receiptInvoice->subtotal, 2),
                'discount' => number_format((float) $receiptInvoice->discount_amount, 2),
                'tax' => number_format((float) $receiptInvoice->tax_amount, 2),
                'total' => number_format((float) $receiptInvoice->total_amount, 2),
                'currency' => $menuCurrencySymbol,
            ];
        @endphp
        @php
            $receiptArtAssets = [
                'background' => asset('assets/tish-paper/paper/background.webp'),
                'paper' => asset('assets/tish-paper/paper/category-ivory.webp'),
                'redTop' => asset('assets/tish-paper/decor/red-torn-top.svg'),
                'redPrice' => asset('assets/tish-paper/paper/price-ticket.webp'),
                'lightning' => asset('assets/tish-paper/decor/lightning.svg'),
                'halftone' => asset('assets/tish-paper/decor/halftone.svg'),
                'logo' => $brandLogo,
                'logoFallback' => asset('assets/tish-paper/brand/logo-disc.webp'),
                'logoDisc' => asset('assets/tish-paper/brand/logo-disc.webp'),
            ];
        @endphp
        const receipt = {{ \Illuminate\Support\Js::from($receiptData) }};
        const receiptArtAssets = {{ \Illuminate\Support\Js::from($receiptArtAssets) }};
        saveInvoiceButton?.addEventListener('click', async () => {
            saveInvoiceButton.disabled = true;
            const oldLabel = saveInvoiceButton.innerHTML;
            saveInvoiceButton.textContent = 'جارٍ تجهيز صورة الفاتورة...';
            try {
                if (!window.TishPaperInvoiceImage?.create) {
                    throw new Error('TISH invoice image exporter is unavailable');
                }
                const blob = await window.TishPaperInvoiceImage.create(receipt, receiptArtAssets);
                const filename = 'Tish-Invoice-' + receipt.number.replace(/[^a-zA-Z0-9_-]/g, '_') + '.png';
                const file = new File([blob], filename, {type:'image/png'});
                // Mobile Share can save to Photos/Files; fallback downloads PNG.
                if (navigator.canShare?.({files:[file]}) && navigator.share) {
                    try { await navigator.share({files:[file], title:'فاتورة ' + receipt.number}); return; }
                    catch (error) { if (error.name === 'AbortError') return; }
                }
                const href = URL.createObjectURL(blob);
                const anchor = document.createElement('a');
                anchor.href = href; anchor.download = filename;
                document.body.appendChild(anchor);
                anchor.click(); anchor.remove();
                setTimeout(() => URL.revokeObjectURL(href), 30000);
            } catch (error) {
                console.error('Invoice image export failed:', error);
                alert('تعذر إنشاء صورة الفاتورة. يمكنك استخدام زر عرض الفاتورة.');
            } finally {
                saveInvoiceButton.disabled = false;
                saveInvoiceButton.innerHTML = oldLabel;
            }
        });

        @endif

        if (initialStatus) {
            updateTracking(initialStatus);
        }

        poll();
        setInterval(poll, 5000);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) poll();
        });
        window.addEventListener('pageshow', poll);
    </script>
</body>
</html>

