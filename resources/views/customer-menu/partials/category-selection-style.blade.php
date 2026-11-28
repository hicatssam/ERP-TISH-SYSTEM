<style>
/* TISH category artwork: ivory paper normally; red paper and contrast-safe artwork on selection.
   Scoped to #categories on both menu pages; overrides legacy paper variants. */
html body.tish-paper #categories#categories {
    align-items:start!important;
    gap:clamp(6px,1.4vw,16px)!important;
}
html body.tish-paper #categories#categories > .paper-category-indicator,
html body.tish-paper #categories#categories button.cat[data-cat]::before,
html body.tish-paper #categories#categories button.cat[data-cat]::after {
    display:none!important;
    content:none!important;
}
html body.tish-paper #categories#categories button.cat[data-cat] {
    --tish-category-disc:clamp(72px,10vw,100px);
    display:flex!important;
    flex-direction:column!important;
    justify-content:flex-start!important;
    align-items:center!important;
    gap:6px!important;
    flex:0 0 auto!important;
    width:calc(var(--tish-category-disc) + 12px)!important;
    min-width:calc(var(--tish-category-disc) + 12px)!important;
    min-height:0!important;
    height:auto!important;
    padding:3px 4px 9px!important;
    background:transparent!important;
    border:0!important;
    box-shadow:none!important;
    outline:none!important;
    filter:none!important;
    transform:none!important;
    translate:none!important;
    scale:none!important;
    opacity:1!important;
    animation:none!important;
    transition:none!important;
}
html body.tish-paper #categories#categories button.cat[data-cat] > :is(.cat-visual,.catalog-category-icon) {
    display:grid!important;
    place-items:center!important;
    box-sizing:border-box!important;
    width:var(--tish-category-disc)!important;
    height:var(--tish-category-disc)!important;
    min-width:var(--tish-category-disc)!important;
    min-height:var(--tish-category-disc)!important;
    flex:0 0 var(--tish-category-disc)!important;
    padding:9%!important;
    background:transparent url('/assets/tish-paper/paper/product-disc.webp') center/100% 100% no-repeat!important;
    border:0!important;
    border-radius:50%!important;
    outline:0!important;
    box-shadow:none!important;
    overflow:hidden!important;
    clip-path:none!important;
    mask:none!important;
    -webkit-mask:none!important;
    filter:none!important;
    transform:none!important;
    opacity:1!important;
    animation:none!important;
    transition:none!important;
}
html body.tish-paper #categories#categories button.cat[data-cat] > :is(.cat-visual,.catalog-category-icon)::before,
html body.tish-paper #categories#categories button.cat[data-cat] > :is(.cat-visual,.catalog-category-icon)::after {
    content:none!important;
    display:none!important;
}
html body.tish-paper #categories#categories button.cat[data-cat] > :is(.cat-visual,.catalog-category-icon):has(img:not([hidden])) {
    padding:calc(var(--tish-category-disc) * .10)!important;
}
html body.tish-paper #categories#categories button.cat[data-cat] > :is(.cat-visual,.catalog-category-icon):not(:has(img:not([hidden]))) {
    display:none!important;
}
html body.tish-paper #categories#categories button.cat[data-cat] :is(.cat-visual,.catalog-category-icon) img {
    display:block!important;
    width:100%!important;
    height:100%!important;
    min-width:0!important;
    min-height:0!important;
    max-width:100%!important;
    max-height:100%!important;
    object-fit:contain!important;
    background:transparent!important;
    border:0!important;
    border-radius:0!important;
    box-shadow:none!important;
    outline:none!important;
    filter:none!important;
    mix-blend-mode:normal!important;
    opacity:1!important;
    padding:0!important;
    clip-path:none!important;
    mask:none!important;
    -webkit-mask:none!important;
    transform:none!important;
    translate:none!important;
    scale:none!important;
    animation:none!important;
    transition:none!important;
}
/* Uploaded photographs fit the same round silhouette as the transparent artwork. */
html body.tish-paper #categories#categories button.cat[data-cat] :is(.cat-visual,.catalog-category-icon) img[data-menu-icon="custom"] {
    clip-path:circle(49% at 50% 50%)!important;
}
html body.tish-paper #categories#categories button.cat[data-cat] :is(.cat-visual,.catalog-category-icon) img[hidden] {
    display:none!important;
}
html body.tish-paper #categories#categories button.cat[data-cat] > b {
    display:block!important;
    max-width:100%!important;
    white-space:normal!important;
    line-height:1.45!important;
    text-align:center!important;
    color:var(--tish-paper-ink,#211e1a)!important;
    font-size:clamp(.78rem,1.5vw,1rem)!important;
    text-decoration:none!important;
    background:none!important;
}
html body.tish-paper #categories#categories button.cat[data-cat].active > b {
    color:var(--tish-paper-red,#b51c11)!important;
    text-decoration:underline!important;
    text-decoration-color:var(--tish-paper-red,#b51c11)!important;
    text-decoration-thickness:2px!important;
    text-underline-offset:5px!important;
}
html body.tish-paper #categories#categories button.cat[data-cat]:focus-visible > b {
    outline:2px solid var(--tish-paper-red,#b51c11)!important;
    outline-offset:3px!important;
}
@media(max-width:600px){
    html body.tish-paper #categories#categories button.cat[data-cat]{
        --tish-category-disc:clamp(64px,17vw,78px);
    }
}
/* Selected paper uses the same torn-disc alpha silhouette, not a square overlay. */
html body.tish-paper #categories#categories button.cat[data-cat].active > :is(.cat-visual,.catalog-category-icon):has(img:not([hidden])) {
    position:relative!important;
    isolation:isolate!important;
}
html body.tish-paper #categories#categories button.cat[data-cat].active > :is(.cat-visual,.catalog-category-icon):has(img:not([hidden]))::before {
    content:""!important;
    display:block!important;
    position:absolute!important;
    inset:0!important;
    width:100%!important;
    height:100%!important;
    z-index:0!important;
    pointer-events:none!important;
    background:#c5251b url('/assets/tish-paper/paper/product-disc.webp') center/100% 100% no-repeat!important;
    background-blend-mode:multiply!important;
    -webkit-mask:url('/assets/tish-paper/paper/product-disc.webp') center/100% 100% no-repeat!important;
    mask:url('/assets/tish-paper/paper/product-disc.webp') center/100% 100% no-repeat!important;
    border:0!important;
    box-shadow:none!important;
    border-radius:0!important;
    opacity:1!important;
    transform:none!important;
}
html body.tish-paper #categories#categories button.cat[data-cat].active :is(.cat-visual,.catalog-category-icon) img {
    position:relative!important;
    z-index:1!important;
}
/* Map the red ink to ivory while retaining the dark ink and the original alpha.
   Only the original TISH graphic library is recolored; photos are untouched. */
html body.tish-paper #categories#categories button.cat[data-cat].active :is(.cat-visual,.catalog-category-icon) img[data-menu-icon^="tish-"] {
    filter:url('/assets/tish-paper/decor/category-selected-ink.svg#selected-ink')!important;
}
</style>
