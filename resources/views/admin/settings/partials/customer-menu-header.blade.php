@php
    $headerSetting = static fn ($key, $default = null) => old($key, \App\Models\SystemSetting::get($key, $default));
    $headerValue = \App\Models\SystemSetting::get('customer_menu_header_image');
    $headerPreview = $headerValue === null
        ? \App\Models\SystemSetting::assetUrl('customer_menu_cover_image')
        : \App\Models\SystemSetting::assetUrl('customer_menu_header_image');
@endphp
<div class="card" style="padding:1.25rem;margin:1rem 0">
    <h3 style="margin-top:0">خلفية رأس المنيو وألوان العميل</h3>
    <p class="form-hint">الصورة أعلى المنيو خلف الشعار والبحث، مستقلة عن إعلانات العروض. الشعار والسلوجن من قسم الهوية والشعارات.</p>
    <div class="settings-grid">
        <div class="form-group">
            <label class="form-label" for="menuHeaderImage">صورة خلفية رأس المنيو</label>
            @if($headerPreview)
                <img src="{{ $headerPreview }}" alt="خلفية رأس المنيو الحالية" style="max-width:100%;max-height:180px;object-fit:contain;border-radius:16px;margin-bottom:12px">
            @endif
            <input id="menuHeaderImage" type="file" name="customer_menu_header_image" class="form-input" accept="image/png,image/jpeg,image/webp">
            @if($headerPreview)
                <label class="remove-image-option"><input type="checkbox" name="remove_customer_menu_header_image" value="1"> إخفاء صورة رأس المنيو الحالية</label>
            @endif
        </div>
        <div class="form-group">
            <label class="form-label" for="menuHeaderHeight">ارتفاع خلفية الرأس</label>
            <input id="menuHeaderHeight" type="number" class="form-input" name="customer_menu_header_height" min="240" max="600" value="{{ $headerSetting('customer_menu_header_height',340) }}">
        </div>
        <div class="form-group">
            <label class="form-label" for="menuHeaderOverlay">تعتيم الخلفية %</label>
            <input id="menuHeaderOverlay" type="number" class="form-input" name="customer_menu_header_overlay" min="0" max="85" value="{{ $headerSetting('customer_menu_header_overlay',15) }}">
        </div>
        <div class="form-group">
            <label class="form-label" for="menuHeaderPosition">موضع الصورة</label>
            <select id="menuHeaderPosition" class="form-input" name="customer_menu_header_position">
                @foreach(['center'=>'الوسط','top'=>'أعلى','bottom'=>'أسفل','left'=>'يسار','right'=>'يمين'] as $value=>$label)
                    <option value="{{ $value }}" @selected($headerSetting('customer_menu_header_position','center')===$value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="menuClientColors">ألوان هوية العميل</label>
            <select id="menuClientColors" class="form-input" name="customer_menu_use_client_colors">
                <option value="1" @selected((string)$headerSetting('customer_menu_use_client_colors',1)==='1')>نفس ألوان العميل في كل المنيو</option>
                <option value="0" @selected((string)$headerSetting('customer_menu_use_client_colors',1)==='0')>ألوان المنيو المستقلة</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="menuCategoryVisual">عرض التصنيفات</label>
            <select id="menuCategoryVisual" class="form-input" name="customer_menu_category_visual">
                <option value="icons" @selected($headerSetting('customer_menu_category_visual','icons')==='icons')>أيقونات بألوان العميل</option>
                <option value="images" @selected($headerSetting('customer_menu_category_visual','icons')==='images')>صور التصنيفات</option>
            </select>
        </div>
    </div>
</div>
