@php
    $key = $category['icon_key'] ?? null;
    $artwork = \App\Support\MenuIconLibrary::artwork($key);
    $image = $key === 'none' ? null : ($artwork ? asset($artwork) : ($category['image'] ?? null));
@endphp
@if($image)
    <img src="{{ $image }}" data-menu-icon="{{ $artwork ? $key : 'custom' }}" class="category-selected-image" alt="" loading="lazy" onerror="this.hidden=true" style="width:100%!important;height:100%!important;object-fit:contain!important;filter:none!important;background:none!important;border:0!important;padding:0!important;box-shadow:none!important">
@endif
@once
<style>
.category-selected-image[hidden]{display:none!important}
body.tish-paper #categories .cat[data-cat="all"]::before{display:none!important;content:none!important}
body.tish-paper #categories .cat .cat-visual:has(img.category-selected-image:not([hidden])),
body.tish-paper #categories .cat[data-cat="all"] .cat-visual{
 background:none!important;border:0!important;box-shadow:none!important;padding:0!important;outline:0!important;overflow:visible!important;
}
body.tish-paper #categories .cat .cat-visual:has(.category-selected-image)::before,
body.tish-paper #categories .cat .cat-visual:has(.category-selected-image)::after,
body.tish-paper #categories .cat[data-cat="all"] .cat-visual::before,
body.tish-paper #categories .cat[data-cat="all"] .cat-visual::after{content:none!important;display:none!important}
body.tish-paper.reference-home #categories .cat .cat-visual .category-selected-image,
body.tish-paper.reference-home #categories .cat[data-cat="all"] .cat-visual .tish-library-icon{
 width:100%!important;height:100%!important;object-fit:contain!important;filter:none!important;background:none!important;border:0!important;padding:0!important;box-shadow:none!important;
}
body.tish-paper.tish-paper-products #categories .cat .catalog-category-icon img{
 filter:none!important;background:none!important;border:0!important;padding:0!important;box-shadow:none!important;
}
body.tish-paper #categories .cat .cat-visual[data-category-icon]:not(:has(img:not([hidden]))),
body.tish-paper #categories .cat .catalog-category-icon[data-category-icon]:not(:has(img:not([hidden]))){display:none!important}
</style>
@endonce
