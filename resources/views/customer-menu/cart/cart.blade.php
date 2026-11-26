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


/* CART REFERENCE V2 — stronger irregular torn silhouette, correct bottom artwork and compact mobile ratios. */
body#tishCart{
 --tp-mask:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20400%2015%22%20preserveAspectRatio%3D%22none%22%3E%3Cpath%20fill%3D%22black%22%20d%3D%22M0%2015%20L1%2011%20L4%207%20L8%204%20L12%207.14%20L14%205.72%20L16%204.93%20L18%206.22%20L20%204.94%20L22%206.91%20L24%205.19%20L26%204.45%20L28%206.02%20L30%203.76%20L32%205.64%20L34%204.05%20L36%203.68%20L38%205.78%20L40%206.15%20L42%206.16%20L44%205.61%20L46%206.63%20L48%206.6%20L50%207.24%20L52%207.95%20L54%206.85%20L56%207.6%20L58%205.8%20L60%206.65%20L62%205.55%20L64%204.53%20L66%206.05%20L68%203.95%20L70%205.57%20L72%204.58%20L74%202.41%20L76%202.55%20L78%204.65%20L80%202.44%20L82%202.35%20L84%202.24%20L86%202.62%20L88%202.78%20L90%205.26%20L92%203.77%20L94%203.13%20L96%204.36%20L98%205.57%20L100%203.77%20L102%205.01%20L104%204.05%20L106%202.48%20L108%203.07%20L110%202.26%20L112%201.76%20L114%202.74%20L116%203.45%20L118%202.5%20L120%201.8%20L122%200.98%20L124%203.48%20L126%202.59%20L128%202.12%20L130%201.96%20L132%203.52%20L134%204.43%20L136%205.39%20L138%205.57%20L140%204.25%20L142%205.64%20L144%203.64%20L146%204.62%20L148%204.87%20L150%205.59%20L152%205.42%20L154%205.79%20L156%204.93%20L158%204.46%20L160%204.82%20L162%205.21%20L164%202.79%20L166%203.1%20L168%204.85%20L170%205.3%20L172%205.05%20L174%204.11%20L176%206.39%20L178%205.15%20L180%205.64%20L182%207.67%20L184%208.08%20L186%206.31%20L188%208.08%20L190%205.62%20L192%205.82%20L194%207.84%20L196%206.16%20L198%207.39%20L200%204.82%20L202%206.1%20L204%205.06%20L206%206.02%20L208%204.71%20L210%203.79%20L212%204.02%20L214%205.87%20L216%204.68%20L218%205.97%20L220%205.51%20L222%206.27%20L224%207.07%20L226%204.89%20L228%206.31%20L230%206.58%20L232%206.18%20L234%206.59%20L236%205.65%20L238%206.16%20L240%205.09%20L242%205.26%20L244%202.6%20L246%203.37%20L248%203.36%20L250%202.08%20L252%201.45%20L254%203.75%20L256%201.35%20L258%201.67%20L260%202.2%20L262%203.68%20L264%202%20L266%202.17%20L268%204.07%20L270%203.32%20L272%203.34%20L274%203.82%20L276%202.93%20L278%203.44%20L280%205.04%20L282%203.8%20L284%202.24%20L286%202.17%20L288%204.29%20L290%201.61%20L292%203.55%20L294%201.42%20L296%202.51%20L298%202.17%20L300%202.02%20L302%202.49%20L304%203.03%20L306%202.92%20L308%205.56%20L310%204.1%20L312%204.36%20L314%204.59%20L316%206.89%20L318%207.19%20L320%204.81%20L322%205.73%20L324%204.58%20L326%206.14%20L328%206.07%20L330%205.93%20L332%204.58%20L334%204.86%20L336%205.07%20L338%204.14%20L340%203.72%20L342%204.63%20L344%205.4%20L346%206.54%20L348%204.63%20L350%204.85%20L352%205.83%20L354%207.77%20L356%206.03%20L358%207.21%20L360%205.74%20L362%208.07%20L364%207.31%20L366%206.52%20L368%207.14%20L370%204.98%20L372%206.16%20L374%204.68%20L376%205.97%20L378%204.38%20L380%204.86%20L382%205.29%20L384%203.07%20L386%203.08%20L388%204.18%20L392%205%20L397%209%20L400%2015%20Z%22%2F%3E%3C%2Fsvg%3E"),url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20400%2015%22%20preserveAspectRatio%3D%22none%22%3E%3Cpath%20fill%3D%22black%22%20d%3D%22M0%200%20H400%20L398%206%20L394%2010%20L388%2011.62%20L386%2011.96%20L384%2011.67%20L382%2012.06%20L380%2010.36%20L378%2011.47%20L376%209.84%20L374%209.94%20L372%2010.11%20L370%209.31%20L368%208.89%20L366%208.86%20L364%209.45%20L362%207.77%20L360%208.51%20L358%208.15%20L356%208.43%20L354%208.35%20L352%209.58%20L350%208.62%20L348%208.09%20L346%207.57%20L344%208.58%20L342%207.7%20L340%208.47%20L338%207.36%20L336%207.52%20L334%208.95%20L332%207.85%20L330%209.55%20L328%209.59%20L326%209.78%20L324%2011.06%20L322%2010.96%20L320%209.31%20L318%209.75%20L316%2011.52%20L314%208.99%20L312%209.03%20L310%2010.28%20L308%2010.89%20L306%2011.03%20L304%2010.33%20L302%2010.38%20L300%2012.21%20L298%2010.47%20L296%2011.29%20L294%2011.29%20L292%2012.83%20L290%2011.54%20L288%2011.53%20L286%2012.29%20L284%2011.31%20L282%2012.17%20L280%2010.84%20L278%2012.13%20L276%2011.21%20L274%209.9%20L272%2011.9%20L270%2011.22%20L268%2010.62%20L266%2011.8%20L264%2011.71%20L262%2010.55%20L260%2012.2%20L258%2011.7%20L256%2011.53%20L254%209.98%20L252%209.89%20L250%2010.16%20L248%2010.06%20L246%208.92%20L244%209.3%20L242%209.31%20L240%207.34%20L238%209.81%20L236%208.57%20L234%209.33%20L232%208.06%20L230%209.01%20L228%208.15%20L226%207.94%20L224%208.84%20L222%207.51%20L220%207.27%20L218%207.64%20L216%208.36%20L214%208.38%20L212%208.43%20L210%206.83%20L208%207.66%20L206%209%20L204%209.66%20L202%2010.49%20L200%2010.16%20L198%209.13%20L196%209.06%20L194%209.56%20L192%2011.56%20L190%2011.48%20L188%2011.17%20L186%2010.31%20L184%209.27%20L182%2010.87%20L180%2010.36%20L178%209.33%20L176%2011.59%20L174%2010.35%20L172%2012.14%20L170%2010.91%20L168%2012.13%20L166%2011.38%20L164%2012.54%20L162%2011.33%20L160%2012.17%20L158%2011.81%20L156%2011.65%20L154%2010.31%20L152%2011.9%20L150%2011.62%20L148%2011.02%20L146%2012.27%20L144%2010.9%20L142%2010.44%20L140%2011.43%20L138%2010.48%20L136%2012.03%20L134%2012.36%20L132%2012.1%20L130%2011.24%20L128%2011.15%20L126%2011.23%20L124%208.92%20L122%209.74%20L120%2010.15%20L118%207.94%20L116%208.19%20L114%207.7%20L112%208.66%20L110%209.33%20L108%208.47%20L106%209.21%20L104%2010.02%20L102%2010.21%20L100%209.63%20L98%209.92%20L96%208.38%20L94%208.49%20L92%208.27%20L90%207.26%20L88%208.38%20L86%208.93%20L84%207.72%20L82%209.41%20L80%208.32%20L78%208.51%20L76%209.18%20L74%2010.55%20L72%2010.62%20L70%2010.81%20L68%2010.62%20L66%209.96%20L64%2011.43%20L62%2010.89%20L60%209.29%20L58%209.19%20L56%209.43%20L54%2010.49%20L52%2010.62%20L50%209.81%20L48%2010.35%20L46%2012.5%20L44%2011.07%20L42%2013.11%20L40%2012.2%20L38%2012.63%20L36%2011.95%20L34%2011.7%20L32%2012.15%20L30%2011.47%20L28%2011.48%20L26%2010.89%20L24%2011.93%20L22%2011.19%20L20%209.97%20L18%2010.28%20L16%2011.29%20L14%2011.49%20L12%2011.91%20L6%2010%20L2%206%20Z%22%2F%3E%3C%2Fsvg%3E"),url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2014%20220%22%20preserveAspectRatio%3D%22none%22%3E%3Cpath%20fill%3D%22black%22%20d%3D%22M2.82%200%20L2.71%202%20L4.05%204%20L3.14%206%20L2.95%208%20L4.89%2010%20L3.93%2012%20L4.97%2014%20L5.16%2016%20L3.87%2018%20L5.39%2020%20L5.35%2022%20L3.91%2024%20L5.28%2026%20L3.07%2028%20L4.17%2030%20L3.81%2032%20L2.86%2034%20L4.43%2036%20L2.19%2038%20L2.8%2040%20L3.06%2042%20L2.65%2044%20L3.37%2046%20L2.03%2048%20L1.95%2050%20L1.53%2052%20L3.02%2054%20L2.77%2056%20L2.57%2058%20L2.28%2060%20L1.93%2062%20L2.9%2064%20L1.53%2066%20L2.41%2068%20L2.18%2070%20L3.66%2072%20L3.66%2074%20L2.87%2076%20L3.81%2078%20L3.38%2080%20L3.74%2082%20L4.26%2084%20L4.27%2086%20L3.62%2088%20L4.74%2090%20L4.91%2092%20L4.89%2094%20L4.3%2096%20L5.17%2098%20L4.76%20100%20L3.25%20102%20L4.86%20104%20L3.83%20106%20L4.65%20108%20L3.01%20110%20L2.74%20112%20L3.08%20114%20L3.02%20116%20L3.23%20118%20L1.86%20120%20L2.14%20122%20L2.59%20124%20L3%20126%20L1.84%20128%20L1.45%20130%20L1.96%20132%20L2.47%20134%20L1.4%20136%20L1.55%20138%20L1.49%20140%20L2.57%20142%20L3.62%20144%20L2.44%20146%20L2.62%20148%20L3.23%20150%20L4.02%20152%20L3.95%20154%20L4.72%20156%20L4.55%20158%20L3.6%20160%20L4.51%20162%20L3.78%20164%20L4.47%20166%20L4.65%20168%20L5%20170%20L5.37%20172%20L3.38%20174%20L4.16%20176%20L4.72%20178%20L3.66%20180%20L3.04%20182%20L3.5%20184%20L4.29%20186%20L3.94%20188%20L3.97%20190%20L2.91%20192%20L2.78%20194%20L2.76%20196%20L3.43%20198%20L1.88%20200%20L1.76%20202%20L1.12%20204%20L2.38%20206%20L1.12%20208%20L1.58%20210%20L2.7%20212%20L2.57%20214%20L2.15%20216%20L2.47%20218%20L3.15%20220%20L14%20220%20V0%20Z%22%2F%3E%3C%2Fsvg%3E"),url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2014%20220%22%20preserveAspectRatio%3D%22none%22%3E%3Cpath%20fill%3D%22black%22%20d%3D%22M0%200%20L10.58%200%20L9.86%202%20L11.38%204%20L9.69%206%20L10.49%208%20L9.04%2010%20L9.31%2012%20L9.31%2014%20L10.15%2016%20L8.97%2018%20L10.32%2020%20L9.62%2022%20L8.85%2024%20L9.15%2026%20L10.48%2028%20L9.55%2030%20L8.98%2032%20L9.74%2034%20L9.33%2036%20L9.8%2038%20L10.72%2040%20L10.61%2042%20L10.9%2044%20L11.27%2046%20L11.94%2048%20L12.33%2050%20L10.87%2052%20L11.39%2054%20L11.15%2056%20L10.87%2058%20L12.13%2060%20L12.81%2062%20L11.37%2064%20L10.99%2066%20L11.72%2068%20L11.05%2070%20L12.24%2072%20L12.15%2074%20L11.79%2076%20L11.29%2078%20L11.49%2080%20L11.11%2082%20L10.45%2084%20L9.33%2086%20L9.27%2088%20L10.55%2090%20L10.26%2092%20L10.11%2094%20L9.25%2096%20L8.68%2098%20L8.67%20100%20L10.43%20102%20L10.04%20104%20L9.74%20106%20L10.45%20108%20L8.84%20110%20L10.26%20112%20L9.12%20114%20L9.6%20116%20L11.26%20118%20L10.27%20120%20L10.01%20122%20L9.94%20124%20L11.31%20126%20L10.75%20128%20L10.47%20130%20L11.08%20132%20L12.54%20134%20L12.24%20136%20L11.52%20138%20L12.03%20140%20L12.82%20142%20L11.39%20144%20L12.39%20146%20L11.66%20148%20L11.61%20150%20L10.68%20152%20L11.97%20154%20L11.99%20156%20L10.76%20158%20L10.18%20160%20L10.08%20162%20L9.85%20164%20L11%20166%20L10.6%20168%20L10.59%20170%20L10.34%20172%20L10.22%20174%20L8.81%20176%20L9.77%20178%20L9.74%20180%20L10.1%20182%20L9.08%20184%20L9.58%20186%20L8.64%20188%20L9.97%20190%20L9.88%20192%20L9.96%20194%20L9.37%20196%20L11.11%20198%20L10.68%20200%20L11.49%20202%20L10.03%20204%20L10.46%20206%20L11.31%20208%20L11.69%20210%20L12.28%20212%20L11.01%20214%20L12.21%20216%20L11.75%20218%20L11.03%20220%20L0%20220%20Z%22%2F%3E%3C%2Fsvg%3E"),linear-gradient(#000,#000);
 --tp-mask-size:100% 15px,100% 15px,14px calc(100% - 28px),14px calc(100% - 28px),calc(100% - 25px) calc(100% - 28px);
 --tp-mask-position:top center,bottom center,left center,right center,center;
 --cart-disc:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20100%20100%22%20preserveAspectRatio%3D%22none%22%3E%3Cpath%20fill%3D%22black%22%20d%3D%22M97.34%2050%20L98.69%2051.91%20L97.56%2053.74%20L98.71%2055.76%20L97.48%2057.52%20L97.53%2059.45%20L96.85%2061.25%20L96.76%2063.19%20L96.71%2065.18%20L96.6%2067.19%20L95.04%2068.66%20L94.1%2070.33%20L92.38%2071.59%20L92.23%2073.65%20L90.16%2074.61%20L89.6%2076.46%20L87.06%2076.93%20L86.95%2079.13%20L86.03%2080.77%20L84.46%2081.86%20L82.45%2082.45%20L81.54%2084.12%20L80.84%2086.11%20L79.46%2087.37%20L77.84%2088.32%20L76.9%2090.25%20L75.06%2090.89%20L73.7%2092.32%20L72%2093.17%20L70.66%2094.83%20L68.95%2095.75%20L67.09%2096.32%20L65.19%2096.74%20L63.14%2096.6%20L61.6%2098.3%20L59.34%2096.98%20L57.5%2097.33%20L55.6%2097.31%20L53.8%2098.32%20L51.84%2096.91%20L50%2097.68%20L48.17%2096.59%20L46.28%2097.32%20L44.57%2095.89%20L42.73%2095.88%20L41.07%2094.91%20L39.11%2095.37%20L37.48%2094.4%20L35.59%2094.34%20L33.59%2094.47%20L32.02%2093.4%20L29.59%2094.27%20L27.83%2093.5%20L26.48%2092%20L24.2%2092.11%20L23.23%2090.07%20L20.61%2090.45%20L19.84%2088.26%20L18.26%2087.17%20L17.04%2085.66%20L15.19%2084.81%20L14.02%2083.26%20L13.69%2081.01%20L12.25%2079.76%20L11.14%2078.23%20L10.1%2076.66%20L10.26%2074.36%20L9.53%2072.66%20L9.37%2070.7%20L7.28%2069.69%20L6.68%2067.95%20L6.25%2066.14%20L5.58%2064.43%20L3.89%2063.01%20L4.65%2060.89%20L3.78%2059.19%20L3.36%2057.39%20L2.48%2055.62%20L1.76%2053.8%20L1.21%2051.92%20L1.58%2050%20L0.78%2048.07%20L1.89%2046.21%20L0.93%2044.19%20L0.83%2042.21%20L1.54%2040.36%20L2.65%2038.63%20L2.89%2036.71%20L4.51%2035.22%20L4.94%2033.37%20L6.69%2032.06%20L6.86%2030.11%20L8.84%2029.03%20L9.17%2027.14%20L10.56%2025.83%20L11.49%2024.27%20L13.07%2023.17%20L13.62%2021.32%20L14.01%2019.26%20L15.67%2018.27%20L17%2017%20L17.43%2014.76%20L18.32%2012.91%20L19.49%2011.3%20L21.64%2010.97%20L23.18%209.85%20L24.8%208.88%20L25.91%206.98%20L27.73%206.28%20L29.87%206.33%20L31.45%205.22%20L33.27%204.65%20L35.38%204.99%20L37.27%204.88%20L39.13%204.72%20L40.88%204.17%20L42.62%203.4%20L44.57%204.13%20L46.35%203.57%20L48.15%203.02%20L50%203.13%20L51.81%204.06%20L53.68%203.28%20L55.41%204.26%20L57.43%203.1%20L59.24%203.55%20L61.11%203.74%20L62.91%204.21%20L64.94%204.01%20L67.02%203.87%20L68.88%204.43%20L70.61%205.3%20L72.47%205.89%20L74.41%206.41%20L75.43%208.5%20L77.25%209.21%20L78.89%2010.24%20L79.73%2012.28%20L81.42%2013.21%20L82.22%2015.14%20L83.35%2016.65%20L84.56%2018.06%20L85.21%2019.93%20L86.49%2021.24%20L88.04%2022.36%20L88.48%2024.29%20L88.92%2026.15%20L91.33%2026.86%20L91.82%2028.69%20L92.08%2030.6%20L93.83%2031.85%20L94.7%2033.51%20L95.16%2035.33%20L96.21%2036.97%20L97.44%2038.61%20L97.98%2040.46%20L97.72%2042.44%20L98.77%2044.23%20L99.78%2046.08%20L98.07%2048.11%20Z%22%2F%3E%3C%2Fsvg%3E");
 --cart-side:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2040%20160%22%20preserveAspectRatio%3D%22none%22%3E%3Cpath%20fill%3D%22black%22%20d%3D%22M40%200%20L35%2010%2036%2018%2030%2026%2031%2035%2025%2040%2026%2049%2020%2056%2022%2064%2017%2072%2019%2080%2012%2087%2013%2095%207%20103%2010%20112%204%20119%207%20128%202%20137%203%20144%200%20152%203%20160%20H40%20Z%22%2F%3E%3C%2Fsvg%3E");
 --cart-nav-height:76px;
}
body#tishCart::before{height:27px!important}
body#tishCart::after{
 width:min(18vw,86px)!important;height:155px!important;bottom:calc(var(--cart-nav-height) - 3px + env(safe-area-inset-bottom,0px))!important;
 background:url('/assets/tish-paper/decor/halftone-bottom-flipped.svg') right bottom/100% 100% no-repeat!important;
}
body#tishCart .app::after{
 content:"";position:fixed;right:0;bottom:calc(var(--cart-nav-height) - 3px + env(safe-area-inset-bottom,0px));
 width:clamp(9px,3.8vw,20px);height:114px;z-index:0;pointer-events:none;
 background:linear-gradient(#b61c0ddd,#b61c0ddd),url('/assets/tish-paper/paper/background.webp') center/200px auto;
 background-blend-mode:multiply;
 -webkit-mask:var(--cart-side) center/100% 100% no-repeat;mask:var(--cart-side) center/100% 100% no-repeat;
}
body#tishCart .cart-reference-header{min-height:99px;padding:14px 0 8px;gap:5px}
body#tishCart .cart-reference-brand{gap:5px}
body#tishCart .cart-reference-brand img{width:clamp(56px,16vw,80px);height:clamp(56px,16vw,80px)}
body#tishCart .cart-reference-copy h1{font-size:clamp(15px,3.9vw,23px)!important}
body#tishCart .cart-reference-copy small{font-size:clamp(9px,2.3vw,12px)!important}
body#tishCart .cart-reference-back{
 background:none!important;position:relative;isolation:isolate;flex:0 0 41px;width:41px;height:41px;
}
body#tishCart .cart-reference-back::before{
 content:"";position:absolute;inset:0;z-index:-1;pointer-events:none;
 background:var(--cart-paper);border:1px solid #fff8ee;
 -webkit-mask:var(--cart-disc) center/100% 100% no-repeat;mask:var(--cart-disc) center/100% 100% no-repeat;
}
body#tishCart .cart-reference-actions :is(.cm-language-switch,.cart-branch-link){
 position:relative!important;isolation:isolate;min-height:38px!important;background:none!important;
}
body#tishCart .cart-reference-actions :is(.cm-language-switch,.cart-branch-link)::before{
 content:"";position:absolute;inset:0;z-index:-1;pointer-events:none;background:var(--cart-paper);
 -webkit-mask-image:var(--tp-mask);mask-image:var(--tp-mask);
 -webkit-mask-size:100% 8px,100% 8px,7px calc(100% - 14px),7px calc(100% - 14px),calc(100% - 12px) calc(100% - 14px);mask-size:100% 8px,100% 8px,7px calc(100% - 14px),7px calc(100% - 14px),calc(100% - 12px) calc(100% - 14px);
 -webkit-mask-position:var(--tp-mask-position);mask-position:var(--tp-mask-position);-webkit-mask-repeat:no-repeat;mask-repeat:no-repeat;
}
html body#tishCart #cartItems>.tish-cart-empty-paper,
html body#tishCart .menu-area .shell>.order-summary{
 filter:drop-shadow(0 4px 2px #51341d55) drop-shadow(0 6px 4px #51341d20)!important;
}
html body#tishCart #cartItems>.tish-cart-empty-paper::after,
html body#tishCart .menu-area .shell>.order-summary::after{
 background:linear-gradient(#fff9f024,#fff5e51a),url('/assets/tish-paper/paper/background.webp') center top/540px auto repeat!important;
}
html body#tishCart #cartItems>.tish-cart-empty-paper::before,
html body#tishCart .menu-area .shell>.order-summary::before{inset:-1.5px!important;background:#fff7ea!important}
html body#tishCart #cartItems>.tish-cart-empty-paper{min-height:clamp(220px,52vw,310px)!important;margin:0 0 13px!important;padding:8px 16px 16px!important;gap:2px!important}
html body#tishCart #cartItems .tish-cart-bag-art{
 width:clamp(125px,31vw,185px)!important;height:clamp(120px,30vw,180px)!important;
}
html body#tishCart #cartItems .tish-cart-empty-paper>h2{font-size:clamp(23px,5.7vw,33px)!important;line-height:1.25!important;margin:2px 0 3px!important}
html body#tishCart #cartItems .tish-cart-empty-paper>.tish-cart-browse{min-height:46px!important;width:clamp(175px,43vw,245px)!important;font-size:clamp(18px,4.7vw,25px)!important}
html body#tishCart .menu-area .shell>.order-summary{min-height:clamp(146px,36vw,205px)!important;padding:18px 20px 19px!important;row-gap:6px!important;margin:0 0 15px!important}
html body#tishCart .menu-area .shell>.order-summary>.summary-row{border-bottom:1.5px dashed #b7a48c!important}
html body#tishCart .menu-area .shell>.order-summary>.cart-total{font-size:clamp(19px,4.8vw,27px)!important}
html body#tishCart .menu-area .shell>.order-summary>.cart-note{font-weight:500!important;font-size:clamp(11px,2.8vw,14px)!important;color:#66574a!important}
html body#tishCart .menu-area .shell>#checkoutStart{min-height:55px!important;filter:drop-shadow(0 3px 2px #54362255)!important}
html body#tishCart .menu-area .shell>#checkoutStart::before{inset:1px!important}
html body#tishCart .menu-area .shell>#checkoutStart::after{
 content:""!important;position:absolute!important;inset:-1px!important;z-index:-2!important;pointer-events:none!important;
 background:#fff7ea!important;
 -webkit-mask-image:var(--tp-mask);mask-image:var(--tp-mask);-webkit-mask-size:var(--tp-mask-size);mask-size:var(--tp-mask-size);
 -webkit-mask-position:var(--tp-mask-position);mask-position:var(--tp-mask-position);-webkit-mask-repeat:no-repeat;mask-repeat:no-repeat;
}
html body#tishCart .menu-area .shell>#checkoutStart:disabled::before{background:linear-gradient(#8b8580a6,#8b8580a6),url('/assets/tish-paper/paper/background.webp') center/540px auto repeat!important;background-blend-mode:multiply!important}
html body#tishCart .bottom-nav{
 min-height:calc(var(--cart-nav-height) + env(safe-area-inset-bottom,0px))!important;
 background:url('/assets/tish-paper/paper/nav-charcoal.webp') center/100% 100% no-repeat!important;
 -webkit-mask:url('/assets/tish-paper/decor/nav-background.svg') center/100% 100% no-repeat;mask:url('/assets/tish-paper/decor/nav-background.svg') center/100% 100% no-repeat;
 padding-top:9px!important;
}
html body#tishCart .bottom-nav .nav-btn{min-height:60px!important;height:60px!important;font-size:clamp(12px,3.15vw,16px)!important;gap:3px!important}
html body#tishCart .bottom-nav .nav-btn .tish-paper-nav-icon{width:clamp(27px,7.4vw,36px)!important;height:clamp(27px,7.4vw,36px)!important;max-height:none!important}
html body#tishCart #pwaInstallBtn.tish-paper-pin-control:not([hidden]){
 isolation:isolate!important;width:82px!important;height:82px!important;min-width:82px!important;min-height:82px!important;
 left:9px!important;bottom:calc(var(--cart-nav-height) + 10px + env(safe-area-inset-bottom,0px))!important;
 border:0!important;background:none!important;clip-path:none!important;
 color:#fff5e7!important;filter:drop-shadow(0 4px 3px #4e281b66)!important;
}
html body#tishCart #pwaInstallBtn.tish-paper-pin-control:not([hidden])::before,
html body#tishCart #pwaInstallBtn.tish-paper-pin-control:not([hidden])::after{
 content:""!important;display:block!important;position:absolute!important;inset:0!important;z-index:-1!important;
 -webkit-mask:var(--cart-disc) center/100% 100% no-repeat;mask:var(--cart-disc) center/100% 100% no-repeat;
 pointer-events:none!important;border:0!important;
 background:linear-gradient(#ba2014dd,#ba2014dd),url('/assets/tish-paper/paper/background.webp') center/200px auto repeat!important;background-blend-mode:multiply!important;
}
html body#tishCart #pwaInstallBtn.tish-paper-pin-control:not([hidden])::after{inset:-2px!important;z-index:-2!important;background:#fff5e7!important}
html body#tishCart #pwaInstallBtn.tish-paper-pin-control:not([hidden]) :is(i,span){color:#fff7e8!important}
@media(max-width:365px){
 body#tishCart .cart-reference-header{gap:3px;min-height:87px}
 body#tishCart .cart-reference-brand img{width:48px;height:48px}
 body#tishCart .cart-reference-back{width:34px;flex-basis:34px;height:37px}
 body#tishCart .cart-reference-copy h1{font-size:13px!important}
 html body#tishCart #pwaInstallBtn.tish-paper-pin-control:not([hidden]){width:74px!important;height:74px!important;min-width:74px!important;min-height:74px!important}
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
