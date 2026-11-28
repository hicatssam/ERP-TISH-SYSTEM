@php
    $mealStatus = app(\App\Services\Restaurant\MealScheduleService::class)->publicStatus((int) $location->id);
    $mealEn = \App\Support\CustomerMenuLanguage::isEnglish();
    $mealLabels = $mealEn ? ['breakfast'=>'Breakfast','lunch'=>'Lunch'] : ['breakfast'=>'وجبات الفطور','lunch'=>'وجبات الغداء'];
    $mealClient = ['initial'=>$mealStatus,'url'=>route('customer-menu.meal-schedules',$location->code,false),'en'=>$mealEn];
@endphp
<section class="tish-meals" id="tishMealSchedule" aria-label="{{ $mealEn ? 'Meal times' : 'مواعيد الوجبات' }}" @if(!collect($mealStatus['windows'])->contains(fn($row)=>$row['enabled'] && $row['count'])) hidden @endif>
<style>
.tish-meals[hidden]{display:none!important}
.tish-meals{margin:12px 0 20px;color:#28221d;font-family:"TISH Noto",Arial,sans-serif}
.tish-meals h2{font-size:clamp(17px,4vw,24px);margin:0 0 8px;font-weight:900}
.tish-meals-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}
.tish-meal-card{position:relative;isolation:isolate;display:flex;flex-direction:column;align-items:flex-start;padding:14px 13px 17px;gap:5px;min-width:0;text-decoration:none!important;color:#28221d!important;background:url('/assets/tish-paper/paper/category-ivory.webp') center/100% 100% no-repeat;filter:drop-shadow(0 4px 3px #573b232b)}
.tish-meal-card[hidden]{display:none!important}
.tish-meal-card strong{font-size:clamp(13px,3.5vw,20px);font-weight:900;line-height:1.4}
.tish-meal-card small{font-size:clamp(10px,2.6vw,13px);color:#67594c;font-weight:700}
.tish-meal-card .meal-hours{direction:ltr;unicode-bidi:isolate;font-variant-numeric:tabular-nums}
.tish-meal-card .meal-clock{font-size:clamp(17px,4vw,27px);font-weight:900;color:#a91f17;direction:ltr;unicode-bidi:isolate;font-variant-numeric:tabular-nums;line-height:1.4}
.tish-meal-card .meal-cta{width:100%;text-align:center;padding:8px 5px;color:#fff4e7;background:url('/assets/tish-paper/paper/price-ticket.webp') center/100% 100% no-repeat;font-weight:900;font-size:clamp(10px,2.8vw,15px)}
.tish-meal-card[data-state="upcoming"] .meal-cta,.tish-meal-card[data-state="ended"] .meal-cta{background:url('/assets/tish-paper/paper/nav-charcoal.webp') center/100% 100% no-repeat}
.tish-meal-card:focus-visible{outline:2px solid #a91f17;outline-offset:3px}
</style>
<h2>{{ $mealEn ? 'On the menu today' : 'وجبات اليوم' }}</h2>
<div class="tish-meals-grid">
@foreach($mealLabels as $key=>$label)
@php($window = $mealStatus['windows'][$key])
<a class="tish-meal-card" data-meal="{{ $key }}" data-state="{{ $window['state'] }}" href="{{ route('customer-menu.products',['location'=>$location->code,'meal'=>$key]) }}" @if(!$window['enabled'] || !$window['count']) hidden @endif>
<strong>{{ $label }}</strong>
<small class="meal-hours">{{ $window['start'] }} – {{ $window['end'] }}</small>
<small class="meal-message">{{ $window['state'] === 'open' ? ($mealEn ? 'Available now' : 'متاح الآن') : ($window['state'] === 'ended' ? ($mealEn ? 'Today’s window ended' : 'انتهى موعد اليوم') : ($mealEn ? 'Coming soon' : 'قريبًا')) }}</small>
<span class="meal-clock"></span>
<span class="meal-cta">{{ $mealEn ? 'View meals' : 'عرض الوجبات' }}</span>
</a>
@endforeach
</div>
</section>
@if(request()->query('meal') === 'breakfast' || request()->query('meal') === 'lunch')
<a href="{{ route('customer-menu.products',$location->code) }}" style="display:inline-block;margin-top:9px;color:#a91f17;font-weight:800">{{ $mealEn ? 'View all meals' : 'عرض كل الأصناف' }}</a>
@endif
<script>
window.TishMealSchedule = {{ \Illuminate\Support\Js::from($mealClient) }};
</script>
