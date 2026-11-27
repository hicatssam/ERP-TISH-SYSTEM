@php
    $selectedIcon = old(
        'icon_key',
        isset($category) ? ($category->icon_key ?: 'none') : 'none'
    );

    $selectedColor = old(
        'icon_color',
        isset($category) ? ($category->icon_color ?: '#111111') : '#111111'
    );

    $icons = \App\Support\MenuIconLibrary::imageOptions();
    $selectedIcon = array_key_exists($selectedIcon, $icons)
        ? $selectedIcon : (!empty($category?->image) ? 'custom' : 'none');
    if (!old('icon_key') && $selectedIcon === 'none' && !empty($category?->image) && ($category->icon_key ?? null) !== 'none') {
        $selectedIcon = 'custom';
    }
@endphp

<div class="category-icon-designer" id="categoryIconDesigner">
    <div class="category-icon-head">
        <div>
            <label class="form-label">صورة الفئة في المنيو</label>
            <p>اختر صورة واحدة من مجموعة TISH أو ارفع صورتك الخاصة. تظهر الصورة وحدها بألوانها الأصلية، دون أيقونة إضافية.</p>
        </div>

        <input type="hidden" name="icon_color" value="{{ $selectedColor }}">
    </div>


    <div class="category-icon-grid">
        @foreach($icons as $key => $label)
            <label class="category-icon-option">
                <input type="radio" class="category-icon-radio" name="icon_key" value="{{ $key }}" @checked($selectedIcon === $key) required>
                <span class="category-icon-choice">
                    <span class="category-icon-circle">
                        @if($artwork = \App\Support\MenuIconLibrary::artwork($key))
                            <img src="{{ asset($artwork) }}" alt="" class="category-picker-image">
                        @elseif($key === 'custom' && !empty($category?->image))
                            <img src="{{ \App\Support\PublicImageUrl::url($category->image) }}" alt="" class="category-picker-image">
                        @else
                            <span class="category-picker-text">{{ $key === 'custom' ? 'رفع صورة' : 'بدون صورة' }}</span>
                        @endif
                    </span>
                    <strong>{{ $label }}</strong>
                    <span class="category-icon-check" aria-hidden="true">✓</span>
                </span>
            </label>
        @endforeach
    </div>

    @error('icon_key')
        <span class="form-error">{{ $message }}</span>
    @enderror

    @error('icon_color')
        <span class="form-error">{{ $message }}</span>
    @enderror
</div>

<style>
.category-icon-designer{
    margin-top:1rem;
    padding:1rem;
    border:1px solid var(--border-light,#e8e8e8);
    border-radius:18px;
    background:linear-gradient(180deg,#fff,#fbfbfb);
}
.category-icon-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:1rem;
    margin-bottom:.9rem;
}
.category-icon-head p{
    margin:.25rem 0 0;
    color:var(--text-muted,#777);
    font-size:.75rem;
}
.category-icon-color{
    display:flex;
    align-items:center;
    gap:.55rem;
    flex:0 0 auto;
    padding:.45rem .55rem;
    border:1px solid var(--border-light,#e8e8e8);
    border-radius:12px;
    background:#fff;
    font-size:.7rem;
    font-weight:800;
}
.category-icon-color input{
    width:34px;
    height:34px;
    padding:2px;
    border:0;
    border-radius:9px;
    background:transparent;
    cursor:pointer;
}
.category-icon-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(112px,1fr));
    gap:.65rem;
}
.category-icon-option{position:relative;display:block;cursor:pointer}
.category-icon-radio{position:absolute;opacity:0;width:1px;height:1px}
.category-icon-radio:focus-visible + .category-icon-choice{outline:2px solid #b32017;outline-offset:2px}
.category-icon-choice{
    position:relative;
    min-height:124px;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:.55rem;
    padding:.8rem .55rem;
    border:1px solid #ececec;
    border-radius:17px;
    background:#fff;
    color:#111;
    transition:.18s ease;
}
.category-icon-choice:hover{
    transform:translateY(-2px);
    border-color:#d7d7d7;
    box-shadow:0 12px 28px rgba(20,20,20,.07);
}
.category-icon-radio:checked + .category-icon-choice{
    border-color:color-mix(in srgb,var(--gold,#c89a2b) 65%,#ddd);
    box-shadow:0 0 0 3px color-mix(in srgb,var(--gold,#c89a2b) 10%,transparent);
}
.category-icon-circle{
    width:64px;
    height:64px;
    display:grid;
    place-items:center;
    border:1px solid #eeeeee;
    border-radius:50%;
    background:#fafafa;
    color:var(--category-icon-color,#b32017);
    transition:.18s ease;
}
.category-icon-radio:checked + .category-icon-choice .category-icon-circle{
    color:#fff;
    background:var(--category-icon-color,#b32017);
    border-color:var(--category-icon-color,#b32017);
}
.category-icon-circle .category-picker-image{width:100%;height:100%;object-fit:contain;background:none;filter:none}
.category-icon-circle .category-picker-text{font-size:.68rem;text-align:center;padding:3px}
.category-icon-circle:has(.category-picker-image){border:0;background:none!important}
.category-icon-radio:checked + .category-icon-choice .category-icon-circle:has(.tish-library-icon){background:#f6eadc;border-color:#b32017}
.category-icon-choice strong{
    font-size:.72rem;
    font-weight:850;
}
.category-icon-check{
    position:absolute;
    top:7px;
    left:7px;
    width:21px;
    height:21px;
    display:grid;
    place-items:center;
    border-radius:50%;
    color:#fff;
    background:var(--gold,#c89a2b);
    font-size:.64rem;
    font-weight:900;
    opacity:0;
    transform:scale(.7);
    transition:.18s ease;
}
.category-icon-radio:checked + .category-icon-choice .category-icon-check{
    opacity:1;
    transform:scale(1);
}
@media(max-width:640px){
    .category-icon-head{flex-direction:column}
    .category-icon-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
    .category-icon-choice{min-height:105px}
    .category-icon-circle{width:54px;height:54px}
}
</style>

<script>
(function () {
    const form = document.currentScript.closest('form');
    if (!form) return;
    const custom = form.querySelector('input[name="icon_key"][value="custom"]');
    // Inputs below the picker may not have been parsed yet. Bind on form change.
    form.addEventListener('change', function (event) {
        if (event.target.name === 'image' && event.target.files?.length && custom) {
            custom.checked = true;
            const remove = form.querySelector('input[name="remove_image"]');
            if (remove) remove.checked = false;
        }
        if (event.target.name === 'remove_image' && event.target.checked && custom?.checked) {
            const none = form.querySelector('input[name="icon_key"][value="none"]');
            if (none) none.checked = true;
        }
    });
})();
</script>
