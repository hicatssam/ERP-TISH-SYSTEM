@php
    $key = $category['icon_key'] ?? 'sparkles';
    $key = in_array($key, \App\Support\MenuIconLibrary::keys(), true) ? $key : 'sparkles';
    $useImage = !empty($category['image']) && ($key === 'custom'
        || (!($category['icon_explicit'] ?? true) && ($theme['category_visual'] ?? 'icons') === 'images'));
@endphp
@if($useImage)
    <img src="{{ $category['image'] }}" data-menu-icon="custom" class="category-custom-icon" alt="" loading="lazy" style="width:58%;height:58%;object-fit:contain;background:none!important" onerror="this.hidden=true;this.nextElementSibling.hidden=false">
    <span hidden style="display:grid;place-items:center;width:100%;height:100%" class="category-icon-image-fallback">
        <x-menu-icon name="sparkles" :size="34" style="width:50%;height:50%;color:inherit" />
    </span>
    <style>.category-icon-image-fallback[hidden],.category-custom-icon[hidden]{display:none!important}</style>
@else
    <x-menu-icon :name="$key === 'custom' ? 'sparkles' : $key" :size="40" :stroke="1.8" style="width:50%;height:50%;min-width:18px;min-height:18px;color:inherit" />
@endif
