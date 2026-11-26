{{-- Reference circular paper category chips: shared by Home and Full menu. --}}
<style>
html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.tish-round-categories{
 display:flex!important;flex-direction:row!important;direction:inherit!important;align-items:flex-start!important;justify-content:flex-start!important;
 gap:9px!important;padding:8px 3px 12px!important;overflow-x:auto!important;overflow-y:hidden!important;
 scrollbar-width:none;scroll-snap-type:x proximity;overscroll-behavior-x:contain;
}
html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.tish-round-categories .cat{
 display:flex!important;flex-direction:column!important;align-items:center!important;justify-content:flex-start!important;position:relative!important;
 flex:0 0 clamp(74px,22%,94px)!important;width:clamp(74px,22%,94px)!important;min-width:0!important;max-width:none!important;
 height:auto!important;min-height:98px!important;max-height:none!important;gap:6px!important;padding:2px 0 0!important;margin:0!important;
 border:0!important;border-radius:0!important;background:none!important;background-image:none!important;box-shadow:none!important;filter:none!important;
 color:#2c221a!important;transform:none!important;translate:none!important;clip-path:none!important;overflow:visible!important;scroll-snap-align:start;
}
html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.tish-round-categories .cat::before,
html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.tish-round-categories .cat::after{content:none!important;display:none!important}
html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.tish-round-categories .cat .cat-visual{
 display:grid!important;place-items:center!important;position:relative!important;isolation:isolate!important;
 width:clamp(62px,17vw,78px)!important;height:clamp(62px,17vw,78px)!important;min-width:0!important;min-height:0!important;
 flex:none!important;margin:0!important;padding:0!important;border:0!important;border-radius:0!important;
 background:transparent url('/assets/tish-paper/paper/product-disc.webp') center/100% 100% no-repeat!important;
 color:#a91f17!important;overflow:visible!important;box-shadow:none!important;
 filter:drop-shadow(0 3px 2px #67412b40)!important;transform:none!important;
}
html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.tish-round-categories .cat .cat-visual::before{
 content:none!important;display:none!important;
}
html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.tish-round-categories .cat.active .cat-visual::before{
 content:""!important;display:block!important;position:absolute!important;inset:4px!important;z-index:-1!important;pointer-events:none!important;
 background:#ad2117 url('/assets/tish-paper/paper/nav-active.webp') center/145% 145% no-repeat!important;
 clip-path:polygon(50% 0%,58% 2%,64% 1%,70% 5%,77% 7%,83% 12%,88% 19%,92% 23%,95% 31%,98% 38%,97% 44%,100% 51%,97% 58%,98% 65%,94% 72%,90% 77%,86% 84%,78% 88%,72% 93%,65% 96%,58% 96%,50% 100%,42% 98%,35% 97%,29% 94%,21% 90%,16% 85%,10% 78%,6% 71%,3% 65%,2% 58%,0% 51%,2% 44%,1% 38%,5% 31%,8% 24%,13% 18%,18% 12%,25% 8%,31% 4%,38% 3%,44% 1%)!important;
}
html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.tish-round-categories .cat .cat-visual i{
 display:block!important;position:relative!important;z-index:1!important;margin:0!important;
 color:#a91f17!important;font-size:clamp(25px,7vw,32px)!important;line-height:1!important;
}
html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.tish-round-categories .cat.active .cat-visual i{color:#fff2dc!important}
html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.tish-round-categories .cat .cat-visual img{
 display:block!important;width:65%!important;height:65%!important;object-fit:contain!important;position:absolute!important;z-index:2!important;
 margin:0!important;padding:0!important;border:0!important;background:none!important;box-shadow:none!important;border-radius:0!important;
}
html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.tish-round-categories .cat .cat-visual:has(img) i{visibility:hidden!important}
html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.tish-round-categories .cat>b{
 display:block!important;max-width:100%!important;width:100%!important;min-height:0!important;
 font-size:clamp(11px,3vw,14px)!important;font-weight:800!important;line-height:1.35!important;text-align:center!important;
 color:#2c221a!important;white-space:normal!important;overflow-wrap:normal!important;overflow:visible!important;
}
html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.tish-round-categories .cat.active>b{color:#a91f17!important}
html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.tish-round-categories .paper-category-indicator{display:none!important}
html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.tish-round-categories .cat:focus-visible{outline:2px solid #a91f17!important;outline-offset:2px!important}
@media(prefers-reduced-motion:no-preference){
 html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.tish-round-categories .cat:active .cat-visual{scale:.94}
}
</style>
