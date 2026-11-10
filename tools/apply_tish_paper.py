#!/usr/bin/env python3
"""Safe, idempotent TISH customer-menu skin installer for ERP-TISH-SYSTEM.
No route, controller, model, migration, pricing or order business logic edits.
Usage: python tools/apply_tish_paper.py --repo /path/to/ERP-TISH-SYSTEM [--check|--apply]
"""
import argparse
from pathlib import Path
import shutil
import sys

HERE = Path(__file__).resolve().parents[1]
CSS = "<link rel=\"stylesheet\" href=\"{{ asset('assets/css/customer-menu/tish-paper-v2.css') }}?v=1\">"
SRC = "assets/tish-paper"

def inject_once(source, anchor, addition, label):
    if addition in source:
        return source, False
    if source.count(anchor) != 1:
        raise ValueError(f"{label}: Expected exactly one anchor, found {source.count(anchor)}")
    return source.replace(anchor, anchor + addition, 1), True

def replace_once(source, old, new, label):
    if new in source:
        return source, False
    if source.count(old) != 1:
        raise ValueError(f"{label}: Expected exactly one match, found {source.count(old)}")
    return source.replace(old, new, 1), True

HERO = '''\n  {{-- Paper collage: independent art layers, existing branch logo/settings remain live. --}}
  <div class="tish-paper-brand-hero" aria-label="Tish — Hear it, Feel it">
    <img class="paper-booth" src="{{ asset('assets/tish-paper/brand/telephone.webp') }}" alt="" loading="eager">
    <img class="paper-claim" src="{{ asset('assets/tish-paper/brand/slogan.svg') }}" alt="Hear it, Feel it!" loading="eager">
    <img class="paper-stamp" src="{{ asset('assets/tish-paper/brand/logo-disc.webp') }}" alt="Tish" loading="eager">
  </div>
'''
PROMO = '''\n  {{-- Static image decoration; destination uses the REAL Laravel products route. --}}
  <div class="tish-paper-food-promo">
    <img src="{{ asset('assets/tish-paper/food/grilled-chicken.webp') }}" loading="lazy" alt="">
    <button type="button" onclick="document.getElementById('pwaInstallBtn')?.click()" aria-label="ثبّت المنيو">ثبّت المنيو</button>
  </div>
'''

TRANSFORMS = {}
def theme_body(path, old, new):
    def x(s):return replace_once(s, old, new, path)
    TRANSFORMS[path] = x

# The shared skin loads after the current legacy + Tish circular themes.
def shared_styles(s):
    anchor = "<link rel=\"stylesheet\" href=\"{{ asset('assets/css/customer-menu/tish-circular-ui.css') }}?v=12\">"
    return inject_once(s, anchor, "\n" + CSS, 'shared styles')
TRANSFORMS['resources/views/customer-menu/partials/styles.blade.php'] = shared_styles

# Homepage: logo, branch and search preserve their existing settings and links.
def homepage(s):
    s,_=replace_once(s,'<body class="crisp-customer-menu reference-home">','<body class="crisp-customer-menu reference-home tish-paper">','homepage body')
    s,_=inject_once(s, "  @if($showSearch)", "", 'show search anchor') if False else (s,False)
    if HERO.strip() not in s:
        needle='  @if($showSearch)'
        if s.count(needle)!=1:raise ValueError('homepage: missing search anchor')
        s=s.replace(needle, HERO + '\n' + needle, 1)
    if PROMO.strip() not in s:
        needle="  @if(($theme['show_hero'] ?? true) && ($banners->isNotEmpty() || $coverImage))"
        if s.count(needle)!=1:raise ValueError('homepage: missing original banner anchor')
        s=s.replace(needle, PROMO + '\n' + needle, 1)
    return s,True
TRANSFORMS['resources/views/customer-menu/show.blade.php']=homepage

theme_body('resources/views/customer-menu/products.blade.php', '<body class="crisp-customer-menu">','<body class="crisp-customer-menu tish-paper">')
theme_body('resources/views/customer-menu/cart/cart.blade.php', '<body class="crisp-customer-menu">','<body class="crisp-customer-menu tish-paper">')
theme_body('resources/views/customer-menu/favorites.blade.php', '<body class="crisp-customer-menu">','<body class="crisp-customer-menu tish-paper">')
theme_body('resources/views/customer-menu/product.blade.php', '<body class="crisp-customer-menu', '<body class="crisp-customer-menu tish-paper')
theme_body('resources/views/customer-menu/checkout/checkout.blade.php', '<body class="crisp-customer-menu', '<body class="crisp-customer-menu tish-paper')

# My Orders and tracking ship with their own inline CSS. Add our final override after it.
def my_orders(s):
    s,_=replace_once(s,'<body class="cm-my-orders">','<body class="cm-my-orders tish-paper">','my orders body')
    old='<link rel="stylesheet" href="{{ asset(\'assets/css/customer-menu/tish-circular-ui.css\') }}?v=12">'
    s,_=inject_once(s,old,'\n'+CSS,'my-orders skin')
    return s,True
TRANSFORMS['resources/views/customer-menu/my-orders.blade.php']=my_orders

def tracking(s):
    s,_=replace_once(s,'\n<body>','\n<body class="tish-paper tish-paper-track">','tracking body')
    if CSS not in s:
        marker='</head>'
        if s.count(marker)!=1:raise ValueError('tracking head not found')
        s=s.replace(marker,CSS+'\n'+marker,1)
    return s,True
TRANSFORMS['resources/views/customer-menu/track.blade.php']=tracking

# Preserve IDs and working route destinations. Replace only the visible nav glyphs.
NAV = {
  'fa-solid fa-house': 'home',
  'fa-solid fa-utensils': 'menu',
  'fa-solid fa-bag-shopping': 'cart',
  'fa-regular fa-heart': 'favorites',
  'fa-solid fa-receipt': 'orders',
}
def nav(s):
    # Only change the actual navigation, not the separate sticky-cart icon.
    marker = '<nav class="bottom-nav" aria-label="التنقل">'
    if s.count(marker) != 1:
        raise ValueError('nav: missing bottom navigation')
    before, nav_html = s.split(marker, 1)
    changed = False
    for fa, name in NAV.items():
        old = f'<i class="{fa}"></i>'
        icon = f'<img class="tish-paper-nav-icon" src="{{{{ asset(\'assets/tish-paper/icons/{name}.svg\') }}}}" alt="" aria-hidden="true">'
        if icon in nav_html:
            continue
        nav_html, did_change = replace_once(nav_html, old, old + icon, f'nav {name}')
        changed = changed or did_change
    return before + marker + nav_html, changed
TRANSFORMS['resources/views/customer-menu/partials/bottom-nav.blade.php']=nav

# Branch-aware topbar: uses admin-controlled brand logo, with the kit logo fallback.
def topbar(s):
    snippet='''  <a class="tish-paper-sublogo" href="{{ route('customer-menu.show', $location->code) }}" aria-label="الرئيسية">
   <img src="{{ $branding['logo'] ?? asset('assets/tish-paper/brand/tish-logo.svg') }}" alt="{{ $branding['name'] ?? 'Tish' }}">
  </a>
'''
    if snippet.strip() in s:return s,False
    needle=' <div class="page-head">\n'
    if s.count(needle)!=1:raise ValueError('topbar: page-head anchor absent')
    return s.replace(needle,needle+snippet,1),True
TRANSFORMS['resources/views/customer-menu/partials/topbar.blade.php']=topbar

# Home shortcut and the existing install button need the same English label.
def bilingual_pin(s):
    phrase='  "ثبّت المنيو": "Pin menu",\n'
    if phrase in s:return s,False
    needle='  "تغيير الفرع": "Change branch",'
    if s.count(needle)!=1:raise ValueError('i18n: cannot locate dictionary')
    return s.replace(needle,needle+'\n'+phrase.rstrip('\n'),1),True
TRANSFORMS['public/assets/js/customer-menu/i18n.js']=bilingual_pin

# Important: invoice remains canonical and accounting-safe. We only add a
# customer SCREEN style layer via Blade stack; @media print is untouched.
INVOICE_SKIN = '''@push('print_styles')
<style>
@media screen {
 html,body {background:#f3eee6 url("{{ asset('assets/tish-paper/paper/background.webp') }}") center/540px auto repeat!important}
 .print-paper {background:#f3eee6 url("{{ asset('assets/tish-paper/paper/background.webp') }}") center/540px auto repeat!important;box-shadow:0 12px 28px #33211530!important;border:0!important}
 .print-document-title {color:#a91f17!important;font-weight:900!important}
 .print-document-subtitle {color:#62584f!important}
 .invoice-party-box {background:#faf0e3!important}
 .print-screen-toolbar {background:#252525!important}
 .print-screen-btn {border-radius:0!important}
 .invoice-status-paid {background:#277936!important;color:white!important}
 .invoice-items th {color:#a91f17!important}
}
</style>
@endpush
'''
def invoice(s):
    if INVOICE_SKIN in s:return s,False
    if not s.startswith('{{-- Legacy view name'):raise ValueError('invoice: unexpected wrapper')
    return INVOICE_SKIN+'\n'+s,True
TRANSFORMS['resources/views/customer-menu/invoice.blade.php']=invoice


def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument('--repo',required=True,help='local ERP-TISH-SYSTEM checkout root')
    g=p.add_mutually_exclusive_group(required=True)
    g.add_argument('--check',action='store_true');g.add_argument('--apply',action='store_true')
    args=p.parse_args(); repo=Path(args.repo).expanduser().resolve()
    if not (repo/'artisan').is_file() or not (repo/'routes/customer-ordering.php').is_file():
        p.error('--repo must point to the root of the ERP-TISH-SYSTEM Laravel repository')
    changes={}
    try:
        for rel,func in TRANSFORMS.items():
            target=repo/rel
            if not target.exists():raise ValueError(f'File missing: {rel}')
            original=target.read_text(encoding='utf-8')
            updated,_=func(original)
            if updated!=original:changes[target]=updated
    except Exception as err:
        print('ABORTED (no files changed):',err,file=sys.stderr)
        sys.exit(2)
    files=[f for f in (HERE/'public').rglob('*') if f.is_file()]
    print(f'Checked {len(TRANSFORMS)} project files; {len(changes)} will be updated, {len(files)} assets will be copied.')
    if args.check:return
    for target, updated in changes.items():
        backup=target.with_suffix(target.suffix+'.tish-before-paper.bak')
        if not backup.exists():shutil.copy2(target,backup)
        target.write_text(updated,encoding='utf-8')
        print('Updated:',target.relative_to(repo))
    for src in files:
        rel=src.relative_to(HERE/'public')
        dst=repo/'public'/rel
        dst.parent.mkdir(parents=True,exist_ok=True)
        shutil.copy2(src,dst)
    print('DONE. No DB migrations, controllers, routes, order or payment logic were changed.')
    print('Next: php artisan view:clear; php artisan optimize:clear; smoke test on a staging branch.')

if __name__=='__main__':main()
