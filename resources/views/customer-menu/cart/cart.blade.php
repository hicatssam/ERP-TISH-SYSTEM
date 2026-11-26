@php($menuCurrencySymbol = app(\App\Services\PrintThemeService::class)->settings()['currency_symbol'])
<!doctype html>
<html lang="{{ \App\Support\CustomerMenuLanguage::current() }}" dir="{{ \App\Support\CustomerMenuLanguage::direction() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>سلة الطلب - {{ $branding['name'] ?? config('app.name') }}</title>
@if(!empty($branding['favicon']))<link rel="icon" href="{{ $branding['favicon'] }}">@endif
@include('customer-menu.partials.styles')
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-cart-v2.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-cart-v3.css') }}?v=3">
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-cart-v4.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-cart-summary-v5.css') }}?v=5">
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-paper-polish-v13.css') }}?v=17">
@include('customer-menu.partials.pwa-head')
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-drive-ui-fixes-v1.css') }}?v=1">
<link rel="stylesheet" href="{{ asset('assets/css/customer-menu/tish-paper-ux-v2.css') }}?v=2">




<style>
/* TISH-CART-EXACT-PAPER-V1 */

/* Full-sized paper card */
html body #cartItems {
  display:grid !important;
  grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),280px)) !important;
  justify-content:center !important;
  gap:22px !important;
}

html body #cartItems .cart-row {
  position:relative !important;
  box-sizing:border-box !important;
  display:flex !important;
  flex-direction:column !important;
  align-items:center !important;
  width:280px !important;
  max-width:100% !important;
  min-height:460px !important;
  padding:24px 22px 48px !important;
  margin:0 !important;
  border:0 !important;
  border-radius:0 !important;
  overflow:visible !important;
  background:transparent url('/assets/tish-paper/paper/category-ivory.webp')
    center/100% 100% no-repeat !important;
}

html body #cartItems .cart-row::before {
  content:none !important;
}

html body #cartItems .cart-thumb {
  display:grid !important;
  place-items:center !important;
  flex:0 0 145px !important;
  width:145px !important;
  height:145px !important;
  min-width:145px !important;
  min-height:145px !important;
  margin:12px auto 18px !important;
  padding:10px !important;
  border:0 !important;
  background:transparent url('/assets/tish-paper/paper/product-disc.webp')
    center/100% 100% no-repeat !important;
}

html body #cartItems .cart-thumb img {
  width:100% !important;
  height:100% !important;
  object-fit:contain !important;
}

html body #cartItems .row-copy {
  width:100% !important;
  display:flex !important;
  flex-direction:column !important;
  align-items:center !important;
  gap:18px !important;
  text-align:center !important;
  margin:0 !important;
  padding:0 !important;
  background:none !important;
}

html body #cartItems .row-copy > strong {
  font-size:20px !important;
  font-weight:900 !important;
  line-height:1.25 !important;
  text-align:center !important;
}

html body #cartItems .tish-cart-line-price {
  display:grid !important;
  place-items:center !important;
  width:175px !important;
  min-height:52px !important;
  margin:0 auto !important;
  padding:8px !important;
  color:#fffaf1 !important;
  font-size:21px !important;
  font-weight:900 !important;
  background:transparent url('/assets/tish-paper/paper/nav-active.webp')
    center/100% 100% no-repeat !important;
}

/* A real control row with no transformed parent */
html body #cartItems .row-actions {
  position:static !important;
  display:flex !important;
  flex-direction:row !important;
  align-items:center !important;
  justify-content:center !important;
  gap:14px !important;
  width:100% !important;
  margin:auto 0 0 !important;
  padding:0 !important;
  transform:none !important;
  filter:none !important;
  perspective:none !important;
  background:none !important;
}

/* Remove the inherited oval appearance completely */
html body #cartItems .row-actions button[data-inc],
html body #cartItems .row-actions button[data-dec],
html body #cartItems button[data-remove] {
  position:relative;
  isolation:isolate !important;
  display:grid !important;
  place-items:center !important;
  flex:0 0 55px !important;
  width:55px !important;
  height:55px !important;
  min-width:55px !important;
  min-height:55px !important;
  margin:0 !important;
  padding:0 !important;
  border:0 !important;
  border-radius:0 !important;
  box-shadow:none !important;
  clip-path:none !important;
  background:none !important;
  transform:none !important;
  overflow:visible !important;
  font:900 29px/1 Arial,sans-serif !important;
  color:#aa211b !important;
  cursor:pointer;
}

/* Actual torn paper shapes, independent of button borders */
html body #cartItems .row-actions button[data-inc]::before,
html body #cartItems .row-actions button[data-dec]::before,
html body #cartItems button[data-remove]::before {
  content:"" !important;
  position:absolute !important;
  inset:0 !important;
  z-index:-1 !important;
  display:block !important;
  width:100% !important;
  height:100% !important;
  border-radius:0 !important;
  background:#f5e3cb url('/assets/tish-paper/paper/category-ivory.webp')
    center/100% 100% no-repeat !important;
  clip-path:polygon(0 6%,19% 2%,37% 5%,56% 0,77% 4%,100% 2%,97% 30%,100% 54%,96% 82%,100% 96%,75% 99%,53% 95%,31% 100%,0 96%,4% 68%,0 39%) !important;
  filter:drop-shadow(0 3px 3px #46251d38);
}

/* Red paper plus and delete */
html body #cartItems .row-actions button[data-inc],
html body #cartItems button[data-remove] {
  color:#fffaf1 !important;
}

html body #cartItems .row-actions button[data-inc]::before,
html body #cartItems button[data-remove]::before {
  background:#af2019 url('/assets/tish-paper/paper/nav-active.webp')
    center/100% 100% no-repeat !important;
}

/* Quantity */
html body #cartItems .row-actions b,
html body #cartItems .row-actions .tish-cart-qty {
  display:grid !important;
  place-items:center !important;
  flex:0 0 32px !important;
  min-width:32px !important;
  height:55px !important;
  margin:0 !important;
  padding:0 !important;
  border:0 !important;
  border-radius:0 !important;
  background:none !important;
  box-shadow:none !important;
  color:#382019 !important;
  font-size:27px !important;
  font-weight:900 !important;
}

/* Delete at upper left; supports both old and new HTML */
html body #cartItems button[data-remove] {
  position:absolute !important;
  inset:auto !important;
  top:9px !important;
  left:9px !important;
  z-index:10 !important;
}

html body #cartItems button[data-remove] i {
  color:#fffaf1 !important;
  font-family:"Font Awesome 6 Free" !important;
  font-weight:900 !important;
  font-size:20px !important;
}

html body #cartItems button:focus-visible {
  outline:3px solid #af2019 !important;
  outline-offset:4px !important;
}

@media(max-width:600px) {
  html body #cartItems .cart-row {
    min-height:430px !important;
  }
}

/* TISH cart reference: exact mobile composition; page-local overrides only.
   All artwork is already in the TISH asset library. */
body#tishCart{
 --tp-mask:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20400%2012%22%20preserveAspectRatio%3D%22none%22%3E%3Cpath%20fill%3D%22black%22%20d%3D%22M0%2012%20L1%208%20L3%205%20L6%203.68%20L8%204.05%20L10%204.3%20L12%203.68%20L14%204.01%20L16%202.95%20L18%203.7%20L20%204.4%20L22%204.52%20L24%203.36%20L26%203.83%20L28%205.29%20L30%204.59%20L32%205.52%20L34%205.32%20L36%204.82%20L38%204.82%20L40%204.51%20L42%203.08%20L44%203.67%20L46%202.07%20L48%202.56%20L50%202.34%20L52%202.73%20L54%203.28%20L56%203.11%20L58%204.29%20L60%204.57%20L62%203.57%20L64%204.49%20L66%203.32%20L68%202.84%20L70%202.62%20L72%202.84%20L74%201.63%20L76%201.68%20L78%202.88%20L80%202.62%20L82%202.3%20L84%201.78%20L86%203.49%20L88%202.37%20L90%203.19%20L92%202.96%20L94%202.73%20L96%203.3%20L98%202.92%20L100%203.16%20L102%201.82%20L104%202.1%20L106%202.59%20L108%203.86%20L110%202.89%20L112%204.81%20L114%203.94%20L116%205.08%20L118%203.53%20L120%204.98%20L122%205.36%20L124%204.3%20L126%203.24%20L128%203.16%20L130%203.49%20L132%204.2%20L134%204.29%20L136%203.94%20L138%204.33%20L140%204.81%20L142%205.6%20L144%203.65%20L146%205.56%20L148%203.72%20L150%203.09%20L152%204.33%20L154%203.89%20L156%202.93%20L158%202.81%20L160%203.67%20L162%201.9%20L164%202.84%20L166%202.13%20L168%202.16%20L170%204.21%20L172%202.78%20L174%202.66%20L176%202.23%20L178%202.91%20L180%203.34%20L182%202.21%20L184%202.52%20L186%200.98%20L188%201.41%20L190%201.25%20L192%202.9%20L194%203.3%20L196%201.98%20L198%202.68%20L200%204.38%20L202%202.91%20L204%202.63%20L206%202.86%20L208%202.77%20L210%203.42%20L212%202.02%20L214%202.62%20L216%203.11%20L218%203.61%20L220%202.47%20L222%204.67%20L224%204.95%20L226%204.48%20L228%204.37%20L230%205.05%20L232%205.22%20L234%204.21%20L236%203.09%20L238%204.7%20L240%203.64%20L242%204.06%20L244%204.37%20L246%203.17%20L248%204.1%20L250%205.12%20L252%204.54%20L254%205.37%20L256%204.8%20L258%204.4%20L260%204.98%20L262%204.46%20L264%202.39%20L266%203.74%20L268%201.92%20L270%201.77%20L272%202.82%20L274%202.05%20L276%202.88%20L278%202.24%20L280%203.03%20L282%202.12%20L284%202.73%20L286%203.49%20L288%202.89%20L290%202.33%20L292%202.52%20L294%202.93%20L296%202.01%20L298%202.56%20L300%201.57%20L302%201.25%20L304%203.79%20L306%202.05%20L308%202.24%20L310%202.51%20L312%202.76%20L314%203.42%20L316%203.19%20L318%203.55%20L320%202.23%20L322%202.35%20L324%202.06%20L326%203.38%20L328%204.12%20L330%202.68%20L332%203.38%20L334%203.15%20L336%205.22%20L338%204.83%20L340%204.92%20L342%204.83%20L344%204.49%20L346%203.83%20L348%203%20L350%202.98%20L352%203.29%20L354%203.28%20L356%202.53%20L358%203.98%20L360%204.2%20L362%204.38%20L364%205.35%20L366%205.38%20L368%204.34%20L370%204.2%20L372%203.84%20L374%203.6%20L376%203.43%20L378%203.16%20L380%202.5%20L382%202.34%20L384%202.97%20L386%202.54%20L388%203.15%20L390%202.56%20L392%202.28%20L394%203.81%20L397%206%20L399%209%20L400%2012%20Z%22%2F%3E%3C%2Fsvg%3E"),url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20400%2012%22%20preserveAspectRatio%3D%22none%22%3E%3Cpath%20fill%3D%22black%22%20d%3D%22M0%200%20H400%20L399%204%20L396%207%20L394%208.01%20L392%206.38%20L390%206.72%20L388%207.88%20L386%206.53%20L384%207.92%20L382%207.31%20L380%207.63%20L378%207.86%20L376%207.25%20L374%208.04%20L372%208.08%20L370%209.13%20L368%208.2%20L366%207.81%20L364%207.61%20L362%208.73%20L360%206.87%20L358%208.01%20L356%209.03%20L354%208.82%20L352%208.64%20L350%208.2%20L348%208.54%20L346%208.97%20L344%208.91%20L342%209.13%20L340%209.9%20L338%208.51%20L336%207.95%20L334%208.4%20L332%207.24%20L330%208.65%20L328%208.06%20L326%208.21%20L324%207.41%20L322%206.73%20L320%208.09%20L318%207.28%20L316%207.64%20L314%207.23%20L312%207.54%20L310%208.76%20L308%207.72%20L306%207.54%20L304%207.16%20L302%208.5%20L300%208.12%20L298%208.2%20L296%206.54%20L294%206.16%20L292%208.28%20L290%207.2%20L288%206.51%20L286%208.38%20L284%207.92%20L282%208.38%20L280%207.77%20L278%208.4%20L276%208.2%20L274%2010.02%20L272%2010.17%20L270%208.31%20L268%209.23%20L266%209.55%20L264%207.78%20L262%208.72%20L260%207.93%20L258%208.32%20L256%207.6%20L254%207.27%20L252%209.29%20L250%208.06%20L248%208.98%20L246%208.89%20L244%209.44%20L242%208.37%20L240%207.46%20L238%208.6%20L236%206.99%20L234%207.13%20L232%208.45%20L230%207.24%20L228%206.2%20L226%206.11%20L224%206.47%20L222%206.89%20L220%206.91%20L218%208.23%20L216%206.52%20L214%207.38%20L212%208.63%20L210%208.21%20L208%207.93%20L206%208.56%20L204%208.9%20L202%207.86%20L200%207.47%20L198%207.62%20L196%209.12%20L194%209%20L192%209.11%20L190%208.6%20L188%209.06%20L186%208.91%20L184%207.91%20L182%209.28%20L180%209.84%20L178%2010.28%20L176%208.42%20L174%209.32%20L172%209.72%20L170%208.94%20L168%207.76%20L166%208.08%20L164%207.17%20L162%208.28%20L160%206.36%20L158%207.64%20L156%206.12%20L154%208.05%20L152%206.63%20L150%207.12%20L148%208.18%20L146%208.63%20L144%208.1%20L142%207.22%20L140%207.53%20L138%207.98%20L136%207.08%20L134%207.83%20L132%208.2%20L130%208.39%20L128%207.87%20L126%208.42%20L124%208.51%20L122%206.79%20L120%207.2%20L118%207.32%20L116%209.08%20L114%208.79%20L112%209.95%20L110%209.06%20L108%208.44%20L106%2010.2%20L104%209.79%20L102%207.99%20L100%209.89%20L98%208.61%20L96%208.96%20L94%207.5%20L92%208.71%20L90%208%20L88%207.73%20L86%208.25%20L84%208.77%20L82%208.91%20L80%207.26%20L78%208.99%20L76%207.36%20L74%209.06%20L72%207.73%20L70%208.34%20L68%208.19%20L66%207.84%20L64%207.21%20L62%206.74%20L60%206.29%20L58%206.95%20L56%206.64%20L54%207.35%20L52%208.28%20L50%207.02%20L48%208.04%20L46%207.74%20L44%208.33%20L42%209.43%20L40%207.97%20L38%208.53%20L36%209.05%20L34%208.9%20L32%207.87%20L30%208.38%20L28%207.92%20L26%209.24%20L24%207.67%20L22%208.54%20L20%207.77%20L18%208.84%20L16%208.1%20L14%208.29%20L12%209.9%20L10%209.7%20L8%209.72%20L6%208.71%20L3%207%20L1%204%20Z%22%2F%3E%3C%2Fsvg%3E"),url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%209%20240%22%20preserveAspectRatio%3D%22none%22%3E%3Cpath%20fill%3D%22black%22%20d%3D%22M1.16%200%20L1.98%202%20L2.16%204%20L1.68%206%20L2.42%208%20L1.78%2010%20L1.53%2012%20L1.13%2014%20L1.62%2016%20L2.08%2018%20L2.27%2020%20L2.35%2022%20L1.53%2024%20L2.19%2026%20L2.01%2028%20L1.66%2030%20L0.63%2032%20L1.11%2034%20L0.91%2036%20L-0.02%2038%20L0.88%2040%20L0.81%2042%20L1.17%2044%20L1.42%2046%20L0.52%2048%20L1.06%2050%20L0.75%2052%20L0.88%2054%20L0.62%2056%20L0.67%2058%20L1.34%2060%20L2.31%2062%20L2.64%2064%20L2.08%2066%20L1.86%2068%20L2.35%2070%20L2.03%2072%20L1.23%2074%20L2%2076%20L2.07%2078%20L1.5%2080%20L1.64%2082%20L1.38%2084%20L1.21%2086%20L0.88%2088%20L0.88%2090%20L0.37%2092%20L0.86%2094%20L0.42%2096%20L0.25%2098%20L0.71%20100%20L0.83%20102%20L1.3%20104%20L0.74%20106%20L1.75%20108%20L1.7%20110%20L1.3%20112%20L0.75%20114%20L1.38%20116%20L1.46%20118%20L1.7%20120%20L2.62%20122%20L1.83%20124%20L2.68%20126%20L2.77%20128%20L1.83%20130%20L1.25%20132%20L1.84%20134%20L0.96%20136%20L1.12%20138%20L0.9%20140%20L1.96%20142%20L1%20144%20L1.04%20146%20L1.66%20148%20L0.65%20150%20L0.85%20152%20L0.28%20154%20L0.35%20156%20L0.35%20158%20L1.07%20160%20L1.13%20162%20L1.73%20164%20L1.44%20166%20L1.88%20168%20L1.63%20170%20L1.17%20172%20L1.71%20174%20L1.2%20176%20L1.36%20178%20L2.19%20180%20L2.69%20182%20L2.52%20184%20L2.89%20186%20L2.24%20188%20L1.67%20190%20L1.85%20192%20L1.16%20194%20L0.62%20196%20L0.97%20198%20L1.29%20200%20L1.69%20202%20L0.85%20204%20L0.8%20206%20L0.69%20208%20L0.89%20210%20L0.29%20212%20L0.24%20214%20L0.86%20216%20L0.88%20218%20L0.95%20220%20L1.14%20222%20L1.04%20224%20L1.24%20226%20L1.54%20228%20L2.24%20230%20L1.36%20232%20L2.02%20234%20L1.85%20236%20L1.3%20238%20L1.51%20240%20L9%20240%20V0%20Z%22%2F%3E%3C%2Fsvg%3E"),url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%209%20240%22%20preserveAspectRatio%3D%22none%22%3E%3Cpath%20fill%3D%22black%22%20d%3D%22M0%200%20H9%20L7.57%200%20L7.87%202%20L7.5%204%20L6.53%206%20L6.46%208%20L6.62%2010%20L7.01%2012%20L7.27%2014%20L7.66%2016%20L7.67%2018%20L7.24%2020%20L7.01%2022%20L6.97%2024%20L6.78%2026%20L6.66%2028%20L6.68%2030%20L7.02%2032%20L6.95%2034%20L7.88%2036%20L7.19%2038%20L8.54%2040%20L8.34%2042%20L8.27%2044%20L8.42%2046%20L8.87%2048%20L8.42%2050%20L8.56%2052%20L8.39%2054%20L8.43%2056%20L7.65%2058%20L7.55%2060%20L7.73%2062%20L8.76%2064%20L8.72%2066%20L8.57%2068%20L8.86%2070%20L7.83%2072%20L7.99%2074%20L7.58%2076%20L7.5%2078%20L7.55%2080%20L6.76%2082%20L6.86%2084%20L7.4%2086%20L7.48%2088%20L6.65%2090%20L7.84%2092%20L6.8%2094%20L7.08%2096%20L6.79%2098%20L7.34%20100%20L7.49%20102%20L6.33%20104%20L7.19%20106%20L6.91%20108%20L7.66%20110%20L7.27%20112%20L8.3%20114%20L7.9%20116%20L8.33%20118%20L8.47%20120%20L8.97%20122%20L8.54%20124%20L8.09%20126%20L8.59%20128%20L8.35%20130%20L7.5%20132%20L8%20134%20L7.89%20136%20L8.43%20138%20L7.98%20140%20L8.46%20142%20L8.38%20144%20L8.72%20146%20L8.02%20148%20L8.27%20150%20L7.93%20152%20L7.16%20154%20L6.47%20156%20L6.64%20158%20L6.67%20160%20L6.43%20162%20L7.1%20164%20L7%20166%20L7.61%20168%20L7.84%20170%20L6.87%20172%20L7.5%20174%20L6.74%20176%20L6.69%20178%20L7.29%20180%20L7.34%20182%20L7.39%20184%20L7.81%20186%20L7.71%20188%20L8.15%20190%20L7.63%20192%20L8.21%20194%20L8.73%20196%20L8.4%20198%20L8.61%20200%20L8.04%20202%20L7.86%20204%20L8.42%20206%20L8.08%20208%20L8.51%20210%20L8.35%20212%20L8.24%20214%20L8.67%20216%20L7.99%20218%20L8.25%20220%20L8.3%20222%20L8.46%20224%20L7.33%20226%20L7.52%20228%20L7.14%20230%20L6.86%20232%20L7.23%20234%20L6.59%20236%20L7.4%20238%20L7.54%20240%20L0%20240%20Z%22%2F%3E%3C%2Fsvg%3E"),linear-gradient(#000,#000);
 --tp-mask-size:100% 12px,100% 12px,9px calc(100% - 22px),9px calc(100% - 22px),calc(100% - 16px) calc(100% - 22px);
 --tp-mask-position:top center,bottom center,left center,right center,center;
 --cart-paper:#f3eee6 url('/assets/tish-paper/paper/background.webp') center top/540px auto repeat;
 --cart-ink:#211e1a;--cart-red:#aa2117;
 background:var(--cart-paper)!important;background-blend-mode:normal!important;
 min-height:100dvh;
}
body#tishCart::before{height:22px!important;background:url('/assets/tish-paper/decor/red-torn-top.svg') center top/100% 100% no-repeat!important}
body#tishCart::after{
 content:"";position:fixed;inset:auto 0 72px auto;width:60px;height:142px;
 background:url('/assets/tish-paper/decor/red-torn-side.svg') right bottom/auto 100% no-repeat,
 url('/assets/tish-paper/decor/halftone-bottom-flipped.svg') right bottom/contain no-repeat;
 pointer-events:none;z-index:0;
}
body#tishCart .cart-reference-header{
 position:relative;isolation:isolate;display:flex;align-items:center;justify-content:space-between;
 gap:6px;width:min(calc(100% - 24px),680px);margin:0 auto;padding:13px 0 9px;
 min-height:96px;box-sizing:border-box;
}
body#tishCart .cart-reference-header::before{
 content:"";position:absolute;left:-12px;top:0;width:77px;height:89px;z-index:-1;
 background:url('/assets/tish-paper/decor/halftone.svg') left top/contain no-repeat;pointer-events:none;
}
body#tishCart .cart-reference-brand{
 display:flex;align-items:center;gap:5px;min-width:0;flex:1;text-decoration:none;color:var(--cart-ink);
}
body#tishCart .cart-reference-brand img{width:clamp(51px,15vw,78px);height:clamp(51px,15vw,78px);flex-shrink:0;object-fit:contain;filter:drop-shadow(0 3px 2px #4e34285c)}
body#tishCart .cart-reference-copy{display:grid;min-width:0;gap:2px;text-align:start}
body#tishCart .cart-reference-copy h1{font-size:clamp(14px,3.7vw,21px)!important;font-weight:900!important;line-height:1.3;margin:0!important;white-space:normal}
body#tishCart .cart-reference-copy small{font-size:clamp(9px,2.35vw,13px)!important;line-height:1.35;color:#54473e;overflow-wrap:anywhere}
body#tishCart .cart-reference-back{display:grid;place-items:center;flex:0 0 39px;width:39px;height:44px;color:var(--cart-red);background:url('/assets/tish-paper/paper/favorite-disc.webp') center/100% 100% no-repeat;filter:drop-shadow(0 3px 2px #54362240);text-decoration:none;font-size:17px}
body#tishCart .cart-reference-actions{display:flex;align-items:center;gap:4px;flex-shrink:0;border-inline-start:1px solid #897561;padding-inline-start:6px}
body#tishCart .cart-reference-actions [data-cm-language-host]{display:flex;align-items:center}
body#tishCart .cart-reference-actions :is(.cm-language-switch,.cart-branch-link){
 display:inline-flex!important;position:static!important;inset:auto!important;align-items:center;justify-content:center;
 min-height:39px!important;min-width:0!important;width:auto!important;max-width:none!important;margin:0!important;padding:6px 9px!important;
 background:transparent url('/assets/tish-paper/paper/category-ivory.webp') center/100% 100% no-repeat!important;
 border:0!important;border-radius:0!important;box-shadow:none!important;
 font-size:clamp(10px,2.7vw,15px)!important;line-height:1.4!important;font-weight:900!important;
 text-decoration:none!important;white-space:nowrap;filter:drop-shadow(0 3px 2px #54362238);
}
body#tishCart .cart-branch-link{color:var(--cart-red)!important}
body#tishCart .cart-reference-actions .cm-language-switch{color:var(--cart-ink)!important}
body#tishCart .menu-area{position:relative;z-index:1;padding-top:3px!important;padding-bottom:calc(150px + env(safe-area-inset-bottom,0px))!important}
body#tishCart .menu-area>.shell{width:min(calc(100% - 24px),680px)!important;max-width:680px!important;margin:0 auto!important}
html body#tishCart #cartItems:has(>.tish-cart-empty-paper){display:grid!important;grid-template-columns:minmax(0,1fr)!important;gap:0!important;width:100%!important;margin:0!important}
html body#tishCart #cartItems>.tish-cart-empty-paper,
html body#tishCart .menu-area .shell>.order-summary{
 position:relative!important;isolation:isolate!important;width:100%!important;max-width:none!important;
 border:0!important;border-radius:0!important;background:none!important;box-shadow:none!important;overflow:visible!important;
 filter:drop-shadow(0 4px 3px #52391f48) drop-shadow(0 7px 5px #5437221b)!important;
}
html body#tishCart #cartItems>.tish-cart-empty-paper::before,
html body#tishCart .menu-area .shell>.order-summary::before,
html body#tishCart #cartItems>.tish-cart-empty-paper::after,
html body#tishCart .menu-area .shell>.order-summary::after{
 content:""!important;display:block!important;position:absolute!important;pointer-events:none!important;inset:0!important;
 border:0!important;border-radius:0!important;box-shadow:none!important;
 background:var(--cart-paper)!important;z-index:-1!important;
 -webkit-mask-image:var(--tp-mask);mask-image:var(--tp-mask);
 -webkit-mask-size:var(--tp-mask-size);mask-size:var(--tp-mask-size);
 -webkit-mask-position:var(--tp-mask-position);mask-position:var(--tp-mask-position);
 -webkit-mask-repeat:no-repeat;mask-repeat:no-repeat;
 clip-path:none!important;transform:none!important;
}
html body#tishCart #cartItems>.tish-cart-empty-paper::before,
html body#tishCart .menu-area .shell>.order-summary::before{inset:-1px!important;background:#fff8ee!important;z-index:-2!important}
html body#tishCart #cartItems>.tish-cart-empty-paper{
 display:flex!important;flex-direction:column!important;align-items:center!important;justify-content:center!important;
 min-height:clamp(218px,52vw,310px)!important;height:auto!important;margin:0 0 13px!important;
 gap:3px!important;padding:11px 18px 19px!important;
}
html body#tishCart #cartItems .tish-cart-bag-art{
 position:relative!important;width:clamp(108px,29vw,170px)!important;height:clamp(112px,29vw,174px)!important;
 flex:0 0 auto!important;max-width:none!important;margin:0 auto!important;
}
html body#tishCart #cartItems .tish-cart-bag-art .tish-cart-bag-illustration{object-fit:contain!important;filter:drop-shadow(0 3px 2px #49321c55)!important}
html body#tishCart #cartItems .tish-cart-bag-art .tish-cart-spark{
 position:absolute!important;width:32px!important;height:35px!important;top:25%!important;
 background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 40 40'%3E%3Cpath fill='%23b51c11' d='M38 14 17 0 30 17ZM29 23 0 12 19 26ZM26 31 8 35 25 35Z'/%3E%3C/svg%3E") center/contain no-repeat!important;
 transform:none!important;
}
html body#tishCart #cartItems .tish-cart-bag-art .spark-one{right:-24px!important;left:auto!important;transform:scaleX(-1)!important}
html body#tishCart #cartItems .tish-cart-bag-art .spark-two{left:-24px!important;right:auto!important}
html body#tishCart #cartItems .tish-cart-empty-paper>h2{margin:0!important;color:var(--cart-ink)!important;font-size:clamp(22px,5.4vw,32px)!important;font-weight:900!important;line-height:1.4!important}
html body#tishCart #cartItems .tish-cart-empty-paper>.tish-cart-browse{
 min-width:0!important;width:clamp(158px,44vw,245px)!important;min-height:46px!important;padding:6px 15px!important;margin:3px 0 0!important;
 font-size:clamp(17px,4.5vw,24px)!important;line-height:1.4!important;
 background:transparent url('/assets/tish-paper/paper/price-ticket.webp') center/100% 100% no-repeat!important;
}
html body#tishCart .menu-area .shell>.order-summary{
 min-height:0!important;height:auto!important;margin:0 0 15px!important;padding:18px 19px 21px!important;row-gap:8px!important;
}
html body#tishCart .menu-area .shell>.order-summary>.summary-row{
 padding:0 0 9px!important;font-size:clamp(13px,3.3vw,19px)!important;font-weight:600!important;
}
html body#tishCart .menu-area .shell>.order-summary>.summary-row>span:last-child{font-size:clamp(16px,4.2vw,23px)!important;font-weight:900!important}
html body#tishCart .menu-area .shell>.order-summary>.cart-total{font-size:clamp(18px,4.6vw,25px)!important;padding:0!important}
html body#tishCart .menu-area .shell>.order-summary>.cart-note{font-size:clamp(11px,2.9vw,15px)!important;line-height:1.65!important;padding:0!important;width:100%!important}
html body#tishCart .menu-area .shell>.order-summary .tish-note-next-step{display:inline!important;margin:0!important}
html body#tishCart .menu-area .shell>#checkoutStart{
 position:relative!important;isolation:isolate!important;display:block!important;width:100%!important;min-height:53px!important;height:auto!important;
 margin:0 auto!important;padding:10px 16px!important;box-sizing:border-box!important;background:none!important;box-shadow:none!important;border:0!important;border-radius:0!important;
 color:#fff7e8!important;font-size:clamp(18px,4.7vw,25px)!important;line-height:1.35!important;opacity:1!important;filter:drop-shadow(0 4px 3px #54362244)!important;
}
html body#tishCart .menu-area .shell>#checkoutStart::before{
 content:""!important;display:block!important;position:absolute!important;inset:0!important;z-index:-1!important;pointer-events:none!important;
 background:linear-gradient(#ac2017dd,#ac2017dd),url('/assets/tish-paper/paper/background.webp') center/540px auto repeat!important;background-blend-mode:multiply!important;
 -webkit-mask-image:var(--tp-mask);mask-image:var(--tp-mask);-webkit-mask-size:var(--tp-mask-size);mask-size:var(--tp-mask-size);
 -webkit-mask-position:var(--tp-mask-position);mask-position:var(--tp-mask-position);-webkit-mask-repeat:no-repeat;mask-repeat:no-repeat;
 border:0!important;transform:none!important;
}
html body#tishCart .menu-area .shell>#checkoutStart:disabled{cursor:not-allowed!important;color:#fff4df!important}
html body#tishCart .menu-area .shell>#checkoutStart:disabled::before{background:linear-gradient(#80766dcc,#80766dcc),url('/assets/tish-paper/paper/background.webp') center/540px auto repeat!important;background-blend-mode:multiply!important}
html body#tishCart .bottom-nav{background:#242322 url('/assets/tish-paper/decor/nav-background.svg') center/100% 100% no-repeat!important;clip-path:none!important}
html body#tishCart .bottom-nav::before{content:none!important}
html body#tishCart #pwaInstallBtn.tish-paper-pin-control:not([hidden]){left:10px!important;right:auto!important;bottom:calc(85px + env(safe-area-inset-bottom,0px))!important}
@media(max-width:365px){
 body#tishCart .cart-reference-header{width:calc(100% - 16px);gap:3px;min-height:87px}
 body#tishCart .cart-reference-actions{gap:2px;padding-inline-start:3px}
 body#tishCart .cart-reference-actions :is(.cm-language-switch,.cart-branch-link){padding-inline:5px!important;font-size:10px!important}
 body#tishCart .cart-reference-back{flex-basis:34px;width:34px}
 body#tishCart .cart-reference-brand img{width:46px;height:46px}
}

</style>

</head>
<body id="tishCart" class="crisp-customer-menu tish-paper tish-cart-page">
<div class="app crisp-menu-app">
<nav class="cart-reference-header" aria-label="التنقل بين الصفحات">
 <a class="cart-reference-back" href="{{ route('customer-menu.show', $location->code) }}" aria-label="رجوع"><i class="fa-solid {{ \App\Support\CustomerMenuLanguage::isEnglish() ? 'fa-arrow-left' : 'fa-arrow-right' }}" aria-hidden="true"></i></a>
 <a class="cart-reference-brand" href="{{ route('customer-menu.show', $location->code) }}">
  <img src="{{ asset('assets/tish-paper/brand/logo-disc.webp') }}" alt="" width="70" height="70">
  <span class="cart-reference-copy"><h1>سلة الطلب</h1><small>{{ $location->name }}</small></span>
 </a>
 <div class="cart-reference-actions">
  <a class="cart-branch-link" href="{{ route('customer-menu.index') }}">تغيير الفرع</a>
  <div data-cm-language-host></div>
 </div>
</nav>

<main class="menu-area">
 <div class="shell">
  <div class="tish-cart-items-heading" id="cartItemsHeading" hidden><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i><h2>منتجات السلة</h2></div>
  <div id="cartItems" aria-live="polite"></div>

  <div class="order-summary" aria-label="ملخص سلة الطلب">
   <div class="summary-row"><span>عدد الأصناف</span><span id="itemCount">0</span></div>
   <div class="cart-total"><strong>الإجمالي</strong><strong id="cartTotal" dir="ltr">{{ $menuCurrencySymbol }} 0.00</strong></div>
   <p class="cart-note">قد تُضاف رسوم توصيل حسب نوع الطلب.<span class="tish-note-next-step">تظهر بعد اختيار طريقة الاستلام في الخطوة التالية.</span></p>
  </div>
  <button class="checkout-start" id="checkoutStart" type="button" disabled>إتمام الطلب</button>
 </div>
</main>
</div>

@include('customer-menu.partials.bottom-nav', ['activeNav' => 'cart'])
@include('customer-menu.partials.pwa-install')
@include('customer-menu.partials.cart-engine')

<script>
const CM = window.CustomerMenu;

function renderCart() {
    const rows = CM.cartRows();
    document.getElementById('cartItems').innerHTML = rows.length
        ? rows.map(x => {
            const modifierLine = (x.modifiers || []).length
                ? x.modifiers.map(m => `${m.name}${m.quantity > 1 ? ' ×' + m.quantity : ''}`).join('، ')
                : '';
            const subtitle = [x.variant_name, modifierLine].filter(Boolean).join(' — ');
            return `
          <article class="cart-row" aria-label="صنف في سلة الطلب">
            <span class="tish-cart-row-index" aria-hidden="true"></span>
            <div class="cart-thumb">${x.image ? `<img src="${CM.esc(x.image)}" data-fallback="${CM.esc(x.original_image && x.original_image !== x.image ? x.original_image : '')}" alt="" onerror="if(this.dataset.fallback){const f=this.dataset.fallback;this.dataset.fallback='';this.src=f;}else{this.style.display='none';}">` : ''}</div>
            <div class="row-copy">
              <strong>${CM.esc(x.name.split(' - ')[0])}</strong>
              ${subtitle ? `<span style="display:block;font-size:.74rem;color:var(--muted)">${CM.esc(subtitle)}</span>` : ''}
              <small class="tish-cart-line-price">${CM.money(x.price * x.quantity)}</small>
            </div>
            <div class="row-actions">
              <button type="button" data-dec="${CM.esc(x.key)}" aria-label="تقليل كمية ${CM.esc(x.name)}">−</button>
              <b>${x.quantity}</b>
              <button type="button" data-inc="${CM.esc(x.key)}" aria-label="زيادة كمية ${CM.esc(x.name)}">+</button>
              <button type="button" data-remove="${CM.esc(x.key)}" aria-label="حذف ${CM.esc(x.name)}"><i class="fa-solid fa-trash"></i></button>
            </div>
          </article>`;
        }).join('')
        : `<section class="tish-cart-empty-paper" aria-label="السلة فارغة">
          <div class="tish-cart-bag-art" aria-hidden="true">
            <img class="tish-cart-bag-illustration" src="{{ asset('assets/tish-paper/decor/kraft-cart-bag.webp') }}" width="274" height="336" alt="" loading="eager">
            <span class="tish-cart-spark spark-one"></span><span class="tish-cart-spark spark-two"></span>
          </div>
          <h2 class="tish-cart-empty-title">السلة فارغة.</h2>
          <a class="tish-cart-browse" href="${@json(route('customer-menu.products', $location->code))}">تصفح المنيو</a>
        </section>`;

    document.getElementById('cartItemsHeading').hidden = !rows.length;
    document.getElementById('itemCount').textContent = rows.reduce((s, r) => s + Number(r.quantity), 0);
    document.getElementById('cartTotal').textContent = CM.money(CM.cartTotal());
    document.getElementById('checkoutStart').disabled = !rows.length;
}

document.addEventListener('click', e => {
    const inc = e.target.closest('[data-inc]'); if (inc) { CM.changeCart(inc.dataset.inc, 1); renderCart(); return; }
    const dec = e.target.closest('[data-dec]'); if (dec) { CM.changeCart(dec.dataset.dec, -1); renderCart(); return; }
    const rm = e.target.closest('[data-remove]'); if (rm) { CM.removeFromCart(rm.dataset.remove); renderCart(); return; }
});

document.getElementById('checkoutStart').addEventListener('click', () => {
    if (!CM.cartRows().length) return;
    window.location.href = @json(route('customer-menu.checkout', $location->code));
});

renderCart();
</script>



</body>
</html>
