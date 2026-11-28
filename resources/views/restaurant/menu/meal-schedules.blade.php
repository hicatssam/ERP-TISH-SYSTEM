@extends('layouts.app')
@section('title','مواعيد الوجبات')
@section('page-title','مواعيد الوجبات')
@section('content')
@php
    $periodLabels = ['breakfast'=>'الفطور','lunch'=>'الغداء'];
    $periodOptions = [''=>'بدون قيد زمني','breakfast'=>'الفطور','lunch'=>'الغداء'];
    $clockNow = \Carbon\CarbonImmutable::now('Asia/Gaza')->format('H:i');
    $mealCategories = $items->map(fn ($item) => $item->product?->category)->filter()->unique('id')
        ->sortBy(fn ($category) => $category->name_ar ?: $category->name);
    $filterStorageKey = 'tish.meal-admin.filters.v1.'.auth()->id().'.'.$location->id;
@endphp
<style>
.meal-admin{width:100%;min-width:0;max-width:1400px;margin:0 auto;container-type:inline-size}
.meal-admin *{box-sizing:border-box}
.meal-admin [hidden]{display:none!important}
.ma-header,.ma-section-head,.ma-card-head,.ma-save,.ma-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.ma-header{margin-bottom:18px;align-items:flex-start}
.ma-header h1{font-size:clamp(1.2rem,2vw,1.6rem);margin:0 0 6px;font-weight:800}
.ma-header p,.ma-sub{margin:0;font-size:.83rem;color:var(--text-muted);line-height:1.65}
.ma-meta{display:flex;align-items:center;flex-wrap:wrap;gap:6px 14px;margin-top:9px;font-size:.8rem;color:var(--text-muted)}
.ma-meta bdi{font-weight:800;color:var(--text)}
.ma-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 15px;min-height:40px;border:1px solid var(--border);border-radius:9px;background:var(--surface);color:var(--text);text-decoration:none;font:inherit;font-size:.85rem;font-weight:700;cursor:pointer}
.ma-btn-primary{background:#a31240;color:#fff;border-color:#a31240}
.ma-btn:focus-visible,.meal-admin input:focus-visible,.meal-admin select:focus-visible{outline:2px solid #ad1846;outline-offset:2px}
.ma-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-bottom:14px}
.ma-card,.ma-products{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px;min-width:0}
.ma-card h2,.ma-products h2{font-size:1.05rem;font-weight:800;margin:0}
.ma-card-head{flex-wrap:nowrap}
.ma-title{display:flex;align-items:center;gap:10px;min-width:0}
.ma-icon{display:grid;place-items:center;width:38px;height:38px;border-radius:10px;color:#a31240;background:#a312400d;flex:none}
.ma-icon svg{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.ma-toggle{display:inline-flex;align-items:center;gap:7px;font-size:.78rem;cursor:pointer;white-space:nowrap}
.ma-toggle input{width:1.15rem;height:1.15rem;accent-color:#a31240}
.ma-status{display:inline-flex;align-items:center;gap:6px;font-size:.76rem;font-weight:700;color:var(--text-muted)}
.ma-status::before{content:'';width:7px;height:7px;border-radius:50%;background:currentColor}
.ma-status[data-state="open"]{color:#19804b}.ma-status[data-state="upcoming"]{color:#a16207}.ma-status[data-state="ended"]{color:#8a655f}
.ma-times{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:16px 0 10px}
.ma-field{display:flex;flex-direction:column;gap:5px;min-width:0;font-size:.8rem;font-weight:700}
.meal-admin .form-input{width:100%;min-width:0;min-height:42px;margin:0}
.ma-time{direction:ltr;text-align:center;font-variant-numeric:tabular-nums;font-weight:800;font-size:1.05rem!important}
.ma-time-note{font-size:.73rem;color:var(--text-muted);line-height:1.6;margin:0}
.ma-card-foot{display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-top:13px;padding-top:10px;border-top:1px solid var(--border);font-size:.79rem;color:var(--text-muted)}
.ma-card-foot b{color:var(--text)}
.ma-info{padding:11px 14px;border:1px solid var(--border);border-inline-start:3px solid #a31240;background:var(--surface);border-radius:10px;font-size:.82rem;color:var(--text-muted);line-height:1.7;margin:0 0 16px}
.ma-section-head{margin-bottom:12px}
.ma-toolbar{justify-content:flex-start;margin-bottom:12px}
.ma-search{flex:1 1 230px;min-width:0}
.ma-filter{flex:1 1 150px;min-width:115px;max-width:230px}
.ma-filter-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0 0 12px}
.ma-filter-actions .ma-btn{min-height:34px;padding:6px 11px;font-size:.75rem}
.ma-filter-note{font-size:.72rem;color:var(--text-muted)}
.ma-stat[data-filter-active="true"]{border-color:#a31240}
.ma-stat button{border:0;background:none;color:inherit;text-align:inherit;font:inherit;padding:0;cursor:pointer}
.ma-stat button:focus-visible{outline:2px solid #a31240;outline-offset:3px}
.ma-count{font-size:.78rem;color:var(--text-muted);font-variant-numeric:tabular-nums}
.ma-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-bottom:14px}
.ma-stat{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:5px;padding:9px 12px;border:1px solid var(--border);border-radius:9px;font-size:.77rem;color:var(--text-muted)}
.ma-stat b{font-size:1.05rem;color:var(--text);font-variant-numeric:tabular-nums}
.ma-list{border:1px solid var(--border);border-radius:10px;overflow:hidden}
.ma-list-head,.meal-admin-row{display:grid;grid-template-columns:minmax(0,1fr) minmax(145px,220px);gap:12px;align-items:center;padding:10px 12px}
.ma-list-head{font-size:.76rem;color:var(--text-muted);background:color-mix(in srgb,var(--surface) 92%,var(--text))}
.meal-admin-row{border-top:1px solid var(--border)}
.meal-admin-row:hover{background:color-mix(in srgb,var(--surface) 97%,var(--text))}
.ma-product-name{font-size:.9rem;font-weight:700;overflow-wrap:anywhere;display:block}
.ma-product-meta{display:block;margin-top:3px;font-size:.72rem;color:var(--text-muted)}
.ma-unpublished{color:#9a650e}
.ma-empty{padding:24px;text-align:center;color:var(--text-muted);font-size:.85rem}
.ma-save{position:sticky;bottom:0;z-index:3;padding:12px 14px;margin:16px 0 8px;background:var(--surface);border:1px solid var(--border);border-radius:12px;box-shadow:0 -4px 16px #00000009}
.ma-save .ma-sub{max-width:70%}
.ma-error{margin-bottom:14px}
@container(max-width:650px){.ma-grid{grid-template-columns:1fr}.ma-card,.ma-products{padding:12px}.ma-toolbar{gap:8px}.ma-filter{flex:1 1 140px}.ma-list-head,.meal-admin-row{grid-template-columns:minmax(0,1fr) minmax(110px,42%);gap:8px;padding:10px 8px}.ma-stat{padding:8px;display:block}.ma-stat b{display:block;margin-top:3px}.ma-save .ma-sub{max-width:100%}.ma-save .ma-btn-primary{width:100%}}
</style>
<div class="meal-admin">
    <header class="ma-header">
        <div>
            <h1>مواعيد الفطور والغداء</h1>
            <p>تحكّم بأوقات ظهور الوجبات والأصناف في المنيو الإلكتروني.</p>
            <div class="ma-meta"><span>{{ $location->name }}</span><span>توقيت غزة · 24 ساعة</span><span>وقت فتح الصفحة: <bdi dir="ltr">{{ $clockNow }}</bdi></span></div>
        </div>
        <a class="ma-btn" href="{{ route('restaurant.menu.index',['location_id'=>$location->id]) }}">العودة إلى منيو المطعم</a>
    </header>
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger ma-error" role="alert"><strong>يرجى مراجعة البيانات:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="POST" id="mealAdminForm" data-filter-key="{{ $filterStorageKey }}" action="{{ route('restaurant.menu.meal-schedules.update',['location_id'=>$location->id]) }}">
        @csrf
        <div class="ma-grid">
            @foreach($periodLabels as $key=>$name)
                @php
                    $enabled = (bool) old($key.'.enabled',$settings[$key]['enabled']);
                    $from = old($key.'.start',$settings[$key]['start']);
                    $to = old($key.'.end',$settings[$key]['end']);
                    $saved = $settings[$key];
                    $state = !$saved['enabled'] ? 'disabled' : ($clockNow < $saved['start'] ? 'upcoming' : ($clockNow < $saved['end'] ? 'open' : 'ended'));
                    $stateText = ['disabled'=>'معطلة','upcoming'=>'لم تبدأ بعد','open'=>'متاحة الآن','ended'=>'انتهى موعد اليوم'][$state];
                @endphp
                <section class="ma-card">
                    <div class="ma-card-head">
                        <div class="ma-title">
                            <span class="ma-icon" aria-hidden="true">
                                @if($key === 'breakfast')
                                <svg viewBox="0 0 24 24"><path d="M2 18h20M5 14h14M7 14a5 5 0 0 1 10 0M12 3v3M4 7l2 2M20 7l-2 2"/></svg>
                                @else
                                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3M5 5l2 2m10 10 2 2M19 5l-2 2M7 17l-2 2"/></svg>
                                @endif
                            </span>
                            <div><h2>وجبات {{ $name }}</h2><span class="ma-status" data-state="{{ $state }}" title="حالة الجدول المحفوظ عند فتح الصفحة">{{ $stateText }}</span></div>
                        </div>
                        <label class="ma-toggle"><input type="hidden" name="{{ $key }}[enabled]" value="0"><input type="checkbox" name="{{ $key }}[enabled]" value="1" @checked($enabled)> تفعيل</label>
                    </div>
                    <div class="ma-times">
                        <label class="ma-field">من الساعة
                            <input class="form-input ma-time" type="text" name="{{ $key }}[start]" value="{{ $from }}" placeholder="08:00" dir="ltr" maxlength="5" pattern="([01][0-9]|2[0-3]):[0-5][0-9]" title="أدخل الوقت بصيغة 24 ساعة مثل 08:00" required>
                        </label>
                        <label class="ma-field">إلى الساعة
                            <input class="form-input ma-time" type="text" name="{{ $key }}[end]" value="{{ $to }}" placeholder="16:00" dir="ltr" maxlength="5" pattern="([01][0-9]|2[0-3]):[0-5][0-9]" title="أدخل الوقت بصيغة 24 ساعة مثل 16:00" required>
                        </label>
                    </div>
                    <p class="ma-time-note">مثال: 08:00 صباحًا، 13:00 ظهرًا، 16:00 عصرًا.</p>
                    <div class="ma-card-foot"><span>الأصناف المختارة: <b data-meal-count="{{ $key }}">0</b></span><span>يتكرر يوميًا</span></div>
                </section>
            @endforeach
        </div>
        <p class="ma-info">الأصناف المرتبطة بفترة لا تُطلب إلكترونيًا خارج وقتها أو عند تعطيلها. الأصناف دون قيد زمني تبقى متاحة حسب إعدادات المنيو والمخزون. لا يتغير عمل الكاشير.</p>
        <section class="ma-products">
            <div class="ma-section-head"><div><h2>توزيع الأصناف</h2><p class="ma-sub">اختر الفترة لكل صنف؛ تظهر التغييرات بعد الحفظ.</p></div><span class="ma-count">{{ $items->count() }} صنف</span></div>
            <div class="ma-summary">
                @foreach([''=>'بدون قيد زمني','breakfast'=>'الفطور','lunch'=>'الغداء'] as $value=>$label)
                    <div class="ma-stat"><button type="button" data-period-filter="{{ $value === '' ? 'none' : $value }}" aria-pressed="false">{{ $label }}</button><b data-meal-count="{{ $value }}">0</b></div>
                @endforeach
            </div>
            <div class="ma-toolbar">
                <label class="ma-field ma-search">بحث عن صنف<input id="mealSearch" type="search" class="form-input" placeholder="اسم الصنف أو الرمز"></label>
                <label class="ma-field ma-filter">الفترة<select id="mealFilter" class="form-input"><option value="all">كل الفترات</option><option value="none">بدون قيد زمني</option><option value="breakfast">الفطور</option><option value="lunch">الغداء</option></select></label>
                <label class="ma-field ma-filter">الفئة<select id="mealCategory" class="form-input">
                    <option value="all">كل الفئات</option>
                    @foreach($mealCategories as $category)
                        <option value="{{ $category->id }}">{{ $category->name_ar ?: $category->name }}</option>
                    @endforeach
                    <option value="none">بدون فئة</option>
                </select></label>
                <label class="ma-field ma-filter">النشر الإلكتروني<select id="mealPublication" class="form-input"><option value="all">كل الحالات</option><option value="published">منشور</option><option value="unpublished">غير منشور</option></select></label>
            </div>
            <div class="ma-filter-actions">
                <button type="button" class="ma-btn" id="mealClearFilters">مسح الفلاتر</button>
                <span class="ma-count" id="mealVisibleCount" aria-live="polite"></span>
                <span class="ma-filter-note" id="mealFilterNote">تُحفظ الفلاتر لهذا الفرع على هذا المتصفح.</span>
            </div>
            <div class="ma-list">
                <div class="ma-list-head" aria-hidden="true"><span>الصنف</span><span>فترة الإتاحة</span></div>
                @foreach($items as $item)
                    <div class="meal-admin-row" data-category="{{ $item->product?->category?->id ?? 'none' }}" data-publication="{{ $item->is_active && $item->show_in_qr ? 'published' : 'unpublished' }}" data-search="{{ $item->displayName() }} {{ $item->product?->name }} {{ $item->product?->name_ar }} {{ $item->product?->sku }} {{ $item->product?->category?->name_ar }} {{ $item->product?->category?->name }}">
                        <div>
                            <label class="ma-product-name" for="mealAssignment-{{ $item->product_id }}">{{ $item->displayName() }}</label>
                            <small class="ma-product-meta">{{ $item->product?->category?->name_ar ?: ($item->product?->category?->name ?? 'بدون فئة') }} · {{ $item->product?->sku }} @if(!$item->is_active || !$item->show_in_qr)<span class="ma-unpublished"> · غير منشور إلكترونيًا</span>@endif</small>
                        </div>
                        <select class="form-input" id="mealAssignment-{{ $item->product_id }}" name="assignments[{{ $item->product_id }}]">
                            @foreach($periodOptions as $value=>$label)
                                <option value="{{ $value }}" @selected((string) old('assignments.'.$item->product_id,$assigned[$item->product_id] ?? '') === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                <p id="mealEmpty" class="ma-empty" hidden>لا توجد أصناف مطابقة للبحث.</p>
            </div>
        </section>
        <input type="hidden" name="form_complete" value="1">
        <div class="ma-save"><span class="ma-sub" id="mealSaveHint" role="status">احفظ المواعيد وتوزيع الأصناف معًا.</span><button class="ma-btn ma-btn-primary" type="submit">حفظ التغييرات</button></div>
    </form>
</div>
<script>
(() => {
    const form=document.getElementById('mealAdminForm');
    if(!form)return;
    const rows=[...form.querySelectorAll('.meal-admin-row')];
    const search=document.getElementById('mealSearch'),period=document.getElementById('mealFilter');
    const category=document.getElementById('mealCategory'),publication=document.getElementById('mealPublication');
    const note=document.getElementById('mealFilterNote');
    const controls=[search,period,category,publication];
    const key=form.dataset.filterKey;
    const normalize=value=>String(value).normalize('NFKC').replace(/[\u064b-\u065f\u0670\u0640]/g,'').replace(/[أإآ]/g,'ا').toLowerCase().trim();
    function selectSaved(select,value){
        if(typeof value==='string'&&Array.from(select.options).some(option=>option.value===value))select.value=value;
    }
    function restore(){
        try{
            const saved=JSON.parse(localStorage.getItem(key)||'null');
            if(!saved||typeof saved!=='object')return;
            if(typeof saved.search==='string')search.value=saved.search;
            selectSaved(period,saved.period);
            selectSaved(category,saved.category);
            selectSaved(publication,saved.publication);
        }catch(_){note.textContent='تعذّر استعادة الفلاتر المحفوظة على هذا المتصفح.';}
    }
    function persist(){
        try{
            localStorage.setItem(key,JSON.stringify({search:search.value,period:period.value,category:category.value,publication:publication.value}));
            note.textContent='تم حفظ الفلاتر لهذا الفرع على هذا المتصفح.';
        }catch(_){note.textContent='الفلاتر تعمل، لكن المتصفح لا يسمح بحفظها.';}
    }
    function refresh(){
        const counts={'':0,breakfast:0,lunch:0};
        let visible=0;
        const q=normalize(search.value);
        for(const row of rows){
            const value=row.querySelector('select').value;
            if(Object.prototype.hasOwnProperty.call(counts,value))counts[value]++;
            const matches=normalize(row.dataset.search).includes(q)
                &&(period.value==='all'||(period.value==='none'?'':period.value)===value)
                &&(category.value==='all'||row.dataset.category===category.value)
                &&(publication.value==='all'||row.dataset.publication===publication.value);
            // Hidden rows must still submit their assignment values.
            row.hidden=!matches;
            if(matches)visible++;
        }
        form.querySelectorAll('[data-meal-count]').forEach(el=>el.textContent=counts[el.dataset.mealCount]||0);
        form.querySelectorAll('[data-period-filter]').forEach(button=>{
            const active=button.dataset.periodFilter===period.value;
            button.setAttribute('aria-pressed',String(active));
            button.closest('.ma-stat').dataset.filterActive=String(active);
        });
        document.getElementById('mealVisibleCount').textContent='عرض '+visible+' من '+rows.length+' صنف';
        document.getElementById('mealEmpty').hidden=visible!==0;
    }
    function apply(){refresh();persist();}
    function validateTimes(){
        for(const name of ['breakfast','lunch']){
            const from=form.elements[name+'[start]'],to=form.elements[name+'[end]'];
            to.setCustomValidity('');
            if(from.validity.valid&&to.validity.valid&&from.value>=to.value)to.setCustomValidity('يجب أن تكون ساعة النهاية بعد البداية.');
        }
    }
    search.addEventListener('input',apply);
    for(const select of [period,category,publication])select.addEventListener('change',apply);
    form.querySelectorAll('[data-period-filter]').forEach(button=>button.addEventListener('click',()=>{
        period.value=period.value===button.dataset.periodFilter?'all':button.dataset.periodFilter;
        apply();
    }));
    document.getElementById('mealClearFilters').addEventListener('click',()=>{
        search.value='';period.value='all';category.value='all';publication.value='all';apply();
    });
    form.addEventListener('input',event=>{
        if(controls.includes(event.target))return;
        validateTimes();
        document.getElementById('mealSaveHint').textContent='هناك تغييرات لم تُحفظ بعد.';
    });
    form.addEventListener('change',event=>{if(!controls.includes(event.target))refresh();});
    form.addEventListener('submit',event=>{validateTimes();if(!form.reportValidity())event.preventDefault();else persist();});
    window.addEventListener('pageshow',event=>{if(event.persisted){restore();refresh();}});
    restore();validateTimes();refresh();
})();
</script>
@endsection
