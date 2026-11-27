<style>
/* TISH category selection: retain original artwork, mark the label only. */
html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.categories button.cat[data-cat],
html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.categories button.cat[data-cat]:is(.active,:hover,:active,:focus) {
    background:transparent!important;
    background-image:none!important;
    border-color:transparent!important;
    box-shadow:none!important;
    outline:none!important;
    filter:none!important;
    transform:none!important;
}
html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.categories button.cat[data-cat] :is(.cat-visual,.catalog-category-icon),
html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.categories button.cat[data-cat]:is(.active,:hover,:active,:focus) :is(.cat-visual,.catalog-category-icon) {
    background:transparent!important;
    background-image:none!important;
    border:0!important;
    border-radius:0!important;
    box-shadow:none!important;
    outline:none!important;
    filter:none!important;
    padding:0!important;
    clip-path:none!important;
    mask:none!important;
    -webkit-mask:none!important;
    transform:none!important;
    opacity:1!important;
}
html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.categories button.cat[data-cat] img,
html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.categories button.cat[data-cat]:is(.active,:hover,:active,:focus) img {
    background:transparent!important;
    background-image:none!important;
    border:0!important;
    border-radius:0!important;
    box-shadow:none!important;
    outline:none!important;
    filter:none!important;
    mix-blend-mode:normal!important;
    opacity:1!important;
    padding:0!important;
    object-fit:contain!important;
    clip-path:none!important;
    mask:none!important;
    -webkit-mask:none!important;
    transform:none!important;
}
html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.categories button.cat[data-cat]::before,
html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.categories button.cat[data-cat]::after,
html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.categories button.cat[data-cat] :is(.cat-visual,.catalog-category-icon)::before,
html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.categories button.cat[data-cat] :is(.cat-visual,.catalog-category-icon)::after {
    display:none!important;
    content:none!important;
}
html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.categories button.cat[data-cat] > b {
    color:var(--tish-paper-ink,#211e1a)!important;
    text-decoration:none!important;
}
html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.categories button.cat[data-cat].active > b {
    color:var(--tish-paper-red,#b51c11)!important;
    text-decoration:underline!important;
    text-decoration-color:var(--tish-paper-red,#b51c11)!important;
    text-decoration-thickness:2px!important;
    text-underline-offset:5px!important;
}
html body.tish-paper:is(.reference-home,.tish-paper-products) #categories.categories button.cat[data-cat]:focus-visible > b {
    outline:2px solid var(--tish-paper-red,#b51c11)!important;
    outline-offset:3px!important;
    border-radius:3px;
}
</style>
