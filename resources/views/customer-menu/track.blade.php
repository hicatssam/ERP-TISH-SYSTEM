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
    @if(!empty($branding['favicon']))
        <link rel="icon" href="{{ $branding['favicon'] }}">
    @endif
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
    </style>
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-paper-v2.css') }}?v=1">
</head>
<body class="tish-paper tish-paper-track">
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
    <header class="topbar">
        <div class="wrap topin">
            <a class="brand" href="{{ route('customer-menu.show',$order->location->code) }}">
                @if($brandLogo)
                    <img src="{{ $brandLogo }}" alt="{{ $brandName }}">
                @endif
                <span>
                    <b>{{ $brandName }}</b>
                    <small>{{ $order->location->name }}</small>
                </span>
            </a>
            <a class="back" href="{{ route('customer-menu.my-orders',$order->location->code) }}">طلباتي ←</a>
        </div>
    </header>

    <main class="wrap" style="padding-bottom:60px">
        <section class="card" id="trackingCard">
            <div class="orderHead">
                <div>
                    <span class="eyebrow">تفاصيل الطلب</span>
                    <h1>#{{ $order->order_number }}</h1>
                </div>
                <span class="status" id="label">
                    <span class="statusDot"></span>
                    <span>جاري تحديث الحالة</span>
                </span>
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

            <div class="notice" id="eta"></div>

            <div class="paymentSummary" id="paymentSummary" aria-live="polite" aria-atomic="true">
                <div class="paymentSummaryIcon" aria-hidden="true"><i class="fa-solid fa-credit-card"></i></div>
                <div class="paymentSummaryText">
                    <strong>حالة الدفع</strong>
                    <small id="paymentDescription">يتم تحديث حالة الدفع تلقائيًا.</small>
                </div>
                <span class="paymentSummaryBadge" id="paymentBadge" data-payment-state="unknown">جاري التحديث</span>
            </div>

            <div class="notice show" id="statusDescription"></div>
            <div class="customer-alert-controls">
                <button type="button" id="enableOrderAlerts"><i class="fa-solid fa-bell"></i> تفعيل صوت وإشعارات الطلب</button>
                <small id="alertPermissionHint" aria-live="polite">ستصلك التنبيهات أثناء فتح صفحة الطلب.</small>
            </div>
            <div class="customer-status-alert" id="customerStatusAlert" role="status" aria-live="polite"></div>

            <div class="notice" id="customerMessage"></div>

            <div class="sectionTitle">
                <h2>ملخص الطلب</h2>
                <span class="live">التحديث تلقائيًا</span>
            </div>
            <div class="items">
                @foreach($order->items as $item)
                    <div class="item">
                        <span>
                            {{ $item->product_name }}
                            <small>الكمية: {{ $item->quantity }}</small>
                        </span>
                        <b>{{ number_format($item->line_total,2) }} {{ $menuCurrencySymbol }}</b>
                    </div>
                @endforeach
            </div>
            <div class="total">
                <span>الإجمالي</span>
                <span>{{ number_format($order->total_amount,2) }} {{ $menuCurrencySymbol }}</span>
            </div>

            <div class="footerActions">
                <a class="action primary" href="{{ route('customer-menu.show',$order->location->code) }}">طلب جديد</a>
                <a class="action secondary" href="{{ route('customer-menu.my-orders',$order->location->code) }}">كل طلباتي</a>
                @if($order->invoice)
                    <button class="action secondary" id="saveInvoiceImage" type="button"><i class="fa-solid fa-image"></i> حفظ الفاتورة كصورة</button>
                    <a class="action secondary" href="{{ route('customer-menu.invoice', ['token' => $order->public_token]) }}">
                        عرض الفاتورة
                    </a>
                @endif
            </div>
        </section>
    </main>

    {{-- Tracking belongs to My Orders; all destinations use the order's branch. --}}
    @include('customer-menu.partials.bottom-nav', ['activeNav' => 'orders'])
    @include('customer-menu.partials.nav-badge-sync')
    @include('customer-menu.partials.pwa-install')
    @if($order->invoice)
        <script src="{{ asset('assets/js/customer-menu/tish-invoice-image-v2.js') }}?v=2"></script>
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
            alertsButton.textContent = alertsEnabled ? '🔔 إشعارات الطلب مفعّلة' : '🔔 تفعيل صوت وإشعارات الطلب';
            alertsHint.textContent = alertsEnabled
                ? ('Notification' in window && Notification.permission === 'granted'
                    ? 'الصوت وإشعارات الجهاز مفعّلان أثناء فتح الصفحة.'
                    : 'الصوت مفعّل. إشعارات الجهاز تحتاج إذن المتصفح.')
                : 'فعّل التنبيهات لتسمع صوتًا عند تغيّر حالة الطلب.';
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
                return;
            }
            if (state === lastNotifiedState && message === lastNotifiedMessage) return;
            lastNotifiedState = state;
            lastNotifiedMessage = message;
            const title = menuT(data.label || statusText[state] || 'تحديث طلبك');
            alertBox.textContent = title + (message ? ' — ' + menuF('رسالة من الفرع: {message}', {message}) : '');
            alertBox.classList.add('show');
            if (alertsEnabled) {
                playOrderChime();
                if (navigator.vibrate) navigator.vibrate([120, 60, 120]);
                if ('Notification' in window && Notification.permission === 'granted') {
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
            eta.textContent = showEta ? '⏱️ ' + menuF('الوقت المتوقع لتجهيز طلبك: حوالي {minutes} دقيقة', {minutes}) : '';
            eta.classList.toggle('show', showEta);

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

