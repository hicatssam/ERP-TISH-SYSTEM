@extends('layouts.app')
@section('title', 'طلب حلويات جديد للفرع')

@section('content')
<div class="page-header">
    <h1 class="page-heading">طلب حلويات جديد للفرع</h1>
    <p class="page-subheading">
        <a href="{{ route('showroom-sweets-requests.index') }}">طلبات حلويات الفروع</a> &laquo; جديد
    </p>
</div>

@if($errors->any())
    <div class="alert alert-danger ssr-errors" role="alert">
        <strong>يرجى تصحيح الأخطاء التالية:</strong>
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('showroom-sweets-requests.store') }}" method="POST" id="ssrForm" data-daily-order-form>
    @csrf

    <div style="display:grid;gap:1.5rem;max-width:1100px">
        <div class="card">
            <div class="card-header">
                <span class="card-title">بيانات الطلب</span>
            </div>
            <div class="card-body ssr-grid ssr-grid-2">
                <div class="form-group">
                    <label class="form-label">الفرع الطالب *</label>

                    @if($canChooseBranch)
                        <select name="requesting_location_id" class="form-select" required>
                            <option value="">اختر الفرع</option>
                            @foreach($branches as $location)
                                <option value="{{ $location->id }}"
                                    {{ (string) old('requesting_location_id') === (string) $location->id ? 'selected' : '' }}>
                                    {{ $location->name }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <div class="form-input"
                             style="display:flex;align-items:center;min-height:46px;background:#f8fafc;font-weight:700;cursor:not-allowed">
                            {{ $branch?->name ?? 'غير محدد' }}
                        </div>

                        <input type="hidden"
                               name="requesting_location_id"
                               value="{{ $branch?->id }}">

                        <small class="form-help">
                            هذا هو الفرع المرتبط بحسابك، ولا يمكن تغييره من هذه الشاشة.
                        </small>
                    @endif
                </div>

                <div class="form-group">
                    <label class="form-label">المصنع المرسل إليه *</label>
                    <select name="factory_location_id" class="form-select" required>
                        <option value="">اختر المصنع</option>
                        @foreach($factories as $factory)
                            <option value="{{ $factory->id }}"
                                {{ (string) old('factory_location_id', $factories->count() === 1 ? $factory->id : '') === (string) $factory->id ? 'selected' : '' }}>
                                {{ $factory->name }}
                            </option>
                        @endforeach
                    </select>
                    @if($factories->isEmpty())
                        <small class="form-error">لا يوجد مصنع فعال في النظام حاليًا.</small>
                    @endif
                </div>

                <div class="form-group">
                    <label class="form-label">تاريخ الحاجة</label>
                    <input type="date"
                           name="needed_by"
                           class="form-input"
                           value="{{ old('needed_by', now()->addDay()->toDateString()) }}"
                           min="{{ now()->format('Y-m-d') }}">
                </div>

                <div class="form-group" style="grid-column:1/-1">
                    <label class="form-label">ملاحظات عامة</label>
                    <textarea name="notes"
                              class="form-textarea"
                              rows="2"
                              placeholder="مثال: الطلب للعرض الصباحي، تجهيز قبل الساعة 9...">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">كتالوج حلويات الفروع</span></div>
            <div class="card-body">
                <p class="ssr-catalog-hint">كل المنتجات المسجلة ضمن فئات الحلويات تظهر هنا بصورة المنتج الأصلية. اختر المنتجات المطلوبة وحدد كمية المعرض والمحجوز للعملاء.</p>
                <input id="sweetsCatalogSearch" class="form-input" type="search" placeholder="ابحث باسم المنتج..." aria-label="بحث في الحلويات">
                <div class="ssr-catalog-list" id="sweetsCatalog">
                    @forelse($sweetProducts as $product)
                        @php
                            $name = $product->name_ar ?: $product->name;
                            $image = $product->image
                                ? (\Illuminate\Support\Str::startsWith($product->image, ['http://', 'https://', '/'])
                                    ? $product->image : asset('storage/' . $product->image))
                                : null;
                        @endphp
                        <div class="ssr-catalog-item" data-sweet-product data-name="{{ mb_strtolower($name) }}" data-id="{{ $product->id }}" data-unit="{{ $product->unit ?: 'حبة' }}">
                            <div class="ssr-catalog-identity">
                                <label class="ssr-catalog-name">
                                    <input class="ssr-selected" type="checkbox" aria-label="اختيار {{ $name }}">
                                    @if($image)
                                        <img src="{{ $image }}" alt="" loading="lazy" width="54" height="54">
                                    @else
                                        <span class="ssr-catalog-image-empty" aria-hidden="true">🍬</span>
                                    @endif
                                    <span><strong>{{ $name }}</strong><small>{{ $product->category?->name_ar ?: $product->category?->name }}</small></span>
                                </label>
                            </div>
                            <div class="ssr-catalog-fields">
                                <label>للمعرض <input type="number" class="form-input ssr-showroom" min="0" step="0.001" value="0"></label>
                                <label>محجوز للعملاء <input type="number" class="form-input ssr-reserved" min="0" step="0.001" value="0"></label>
                                <label>الوحدة <input type="text" class="form-input ssr-unit" list="requestUnitOptions" value="{{ $product->unit ?: 'حبة' }}" maxlength="50" required></label>
                                <label>تفاصيل الحجز <input type="text" class="form-input ssr-reservation-notes" maxlength="1000" placeholder="اختياري"></label>
                                <label>ملاحظة <input type="text" class="form-input ssr-notes" maxlength="500" placeholder="اختياري"></label>
                            </div>
                        </div>
                    @empty
                        <p>لا توجد منتجات حلويات نشطة في الكتالوج.</p>
                    @endforelse
                </div>
                <div class="ssr-catalog-summary" aria-live="polite">منتجات مختارة: <strong id="ssrSelected">0</strong> — للمعرض: <strong id="ssrShowroomTotal">0</strong> — محجوز: <strong id="ssrReservedTotal">0</strong></div>
            </div>
        </div>

        @can('create', \App\Models\StockRequest::class)
            @include('sales.partials.daily-raw-materials-form')
        @endcan
        <div class="alert alert-danger" data-order-error role="alert" tabindex="-1" hidden></div>

        <div style="display:flex;gap:.75rem;flex-wrap:wrap">
            <button class="btn btn-gold" type="submit" {{ $factories->isEmpty() ? 'disabled' : '' }}>
                إرسال الطلب للمصنع
            </button>
            <a href="{{ route('showroom-sweets-requests.index') }}" class="btn btn-ghost">إلغاء</a>
        </div>
    </div>
</form>

<datalist id="requestUnitOptions">
    <option value="صدر"></option>
    <option value="صينية"></option>
    <option value="علبة"></option>
    <option value="كغم"></option>
    <option value="حبة"></option>
    <option value="ربطة"></option>
</datalist>

<style>
.ssr-grid{display:grid;gap:1rem}.ssr-grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}
.ssr-catalog-hint{font-size:.83rem;color:var(--text-muted);line-height:1.75;margin-bottom:.75rem}
.ssr-catalog-list{display:grid;gap:.75rem;margin-top:1rem}
.ssr-catalog-item{padding:.9rem 1rem;border:1px solid var(--border-light);border-radius:14px;background:var(--surface,#fff);transition:border-color .2s,background .2s}
.ssr-catalog-item:has(.ssr-selected:checked){border-color:rgba(212,160,23,.55);background:rgba(212,160,23,.045)}
.ssr-catalog-name{display:flex;align-items:center;gap:.85rem;cursor:pointer}
.ssr-catalog-name input{accent-color:#b68c2b;width:19px;height:19px;flex-shrink:0}
.ssr-catalog-name img,.ssr-catalog-image-empty{width:54px;height:54px;border-radius:10px;object-fit:cover;flex-shrink:0}
.ssr-catalog-image-empty{display:grid;place-items:center;background:rgba(212,160,23,.1)}
.ssr-catalog-name strong{display:block;font-size:.88rem}
.ssr-catalog-name small{display:block;color:var(--text-muted);font-size:.73rem}
.ssr-catalog-fields{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.65rem;margin-top:.75rem}
.ssr-catalog-fields label{display:flex;flex-direction:column;gap:.25rem;font-size:.73rem;font-weight:700;color:var(--text-muted)}
.ssr-catalog-fields input{width:100%;min-width:0;height:40px}
.ssr-catalog-summary{margin-top:1rem;padding:.85rem 1rem;border-radius:12px;background:rgba(212,160,23,.08);font-size:.85rem}
.ssr-catalog-summary strong{color:var(--gold-deep,#a77b16)}
.ssr-errors{max-width:1100px;margin-bottom:1.25rem;padding:1rem 1.25rem;border:1px solid #dc3545;border-radius:12px;background:rgba(220,53,69,.12);color:#dc3545}
.ssr-errors ul{margin:.65rem 0 0;padding-inline-start:1.25rem}
@media(max-width:850px){.ssr-grid-2{grid-template-columns:1fr}.ssr-catalog-fields{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:450px){.ssr-catalog-fields{grid-template-columns:1fr}}
@media(prefers-reduced-motion:reduce){.ssr-catalog-item{transition:none}}
</style>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const form=document.getElementById('ssrForm');
 const cards=Array.from(document.querySelectorAll('[data-sweet-product]'));
 const error=form.querySelector('[data-order-error]');
 const oldItems={{ \Illuminate\Support\Js::from(old('items', [])) }};
 const oldById=new Map((Array.isArray(oldItems)?oldItems:[]).map(item=>[String(item.product_id),item]));
 const number=value=>Number(value||0);
 function update(){
  let selected=0,showroom=0,reserved=0;
  cards.forEach(card=>{
   if(!card.querySelector('.ssr-selected').checked)return;
   selected++;
   showroom+=number(card.querySelector('.ssr-showroom').value);
   reserved+=number(card.querySelector('.ssr-reserved').value);
  });
  document.getElementById('ssrSelected').textContent=selected;
  document.getElementById('ssrShowroomTotal').textContent=showroom.toLocaleString('en-US',{maximumFractionDigits:3});
  document.getElementById('ssrReservedTotal').textContent=reserved.toLocaleString('en-US',{maximumFractionDigits:3});
 }
 cards.forEach(card=>{
  const old=oldById.get(card.dataset.id);
  if(old){
   card.querySelector('.ssr-selected').checked=true;
   card.querySelector('.ssr-reserved').value=old.reserved_quantity??0;
   card.querySelector('.ssr-showroom').value=Math.max(0,number(old.quantity)-number(old.reserved_quantity));
   card.querySelector('.ssr-unit').value=old.requested_unit||card.dataset.unit;
   card.querySelector('.ssr-reservation-notes').value=old.reservation_notes||'';
   card.querySelector('.ssr-notes').value=old.notes||'';
  }
  card.addEventListener('input',event=>{
   if(event.target.matches('.ssr-showroom,.ssr-reserved') && number(event.target.value)>0)
    card.querySelector('.ssr-selected').checked=true;
   update();
  });
  card.addEventListener('change',update);
 });
 document.getElementById('sweetsCatalogSearch').addEventListener('input',event=>{
  const q=event.target.value.trim().toLocaleLowerCase();
  cards.forEach(card=>{card.hidden=!card.dataset.name.includes(q)});
 });
 form.addEventListener('submit',event=>{
  form.querySelectorAll('[data-generated-sweet]').forEach(el=>el.remove());
  let index=0;
  for(const card of cards){
   if(!card.querySelector('.ssr-selected').checked)continue;
   const showroom=number(card.querySelector('.ssr-showroom').value),reserved=number(card.querySelector('.ssr-reserved').value);
   const unit=card.querySelector('.ssr-unit').value.trim();
   if(!Number.isFinite(showroom)||!Number.isFinite(reserved)||showroom<0||reserved<0||showroom+reserved<.001||!unit){
    event.preventDefault();error.textContent='تحقق من الكمية والوحدة لكل منتج مختار.';error.hidden=false;error.focus();return;
   }
   const values={product_id:card.dataset.id,quantity:(showroom+reserved).toFixed(3),reserved_quantity:reserved.toFixed(3),requested_unit:unit,reservation_notes:card.querySelector('.ssr-reservation-notes').value,notes:card.querySelector('.ssr-notes').value};
   Object.entries(values).forEach(([key,value])=>{
    const el=document.createElement('input');el.type='hidden';el.name='items['+index+']['+key+']';el.value=value;el.dataset.generatedSweet='';form.appendChild(el);
   });
   index++;
  }
  if(!index){event.preventDefault();error.textContent='اختر صنف حلويات واحدًا على الأقل.';error.hidden=false;error.focus();return;}
  error.hidden=true;
 });
 update();
});
</script>
@endsection


@push('scripts')
<script src="{{ asset('assets/js/daily-order-form.js') }}"></script>
@endpush
