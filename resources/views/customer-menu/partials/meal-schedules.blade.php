@php
    $mealStatus = app(\App\Services\Restaurant\MealScheduleService::class)->publicStatus((int) $location->id);
    $mealEn = \App\Support\CustomerMenuLanguage::isEnglish();
    $mealLabels = $mealEn ? ['breakfast'=>'Breakfast','lunch'=>'Lunch'] : ['breakfast'=>'وجبات الفطور','lunch'=>'وجبات الغداء'];
    $mealClient = ['initial'=>$mealStatus,'url'=>route('customer-menu.meal-schedules',$location->code,false),'en'=>$mealEn];
    // Display only: the stored 24-hour values and server cutoff are unchanged.
    $mealTime12 = function (string $time) use ($mealEn): string {
        [$hour, $minute] = array_map('intval', explode(':', $time));
        $period = $hour >= 12 ? ($mealEn ? 'PM' : 'م') : ($mealEn ? 'AM' : 'ص');
        return (string) ($hour % 12 ?: 12).($minute ? ':'.sprintf('%02d', $minute) : '').' '.$period;
    };
    $mealRange = fn ($start, $end) => $mealEn
        ? $mealTime12($start).' – '.$mealTime12($end)
        : 'من '.$mealTime12($start).' إلى '.$mealTime12($end);
@endphp
<section class="tish-meals" id="tishMealSchedule" aria-label="{{ $mealEn ? 'Meal times' : 'مواعيد الوجبات' }}" @if(!collect($mealStatus['windows'])->contains(fn($row)=>$row['enabled'] && $row['count'])) hidden @endif>
<style>
.tish-meals[hidden],.tish-meal-card[hidden],.tish-meal-card [hidden]{display:none!important}
.tish-meals{margin:20px 0 24px;color:#28221d;min-width:0}
.tish-meals h2{font-size:clamp(19px,3vw,28px);margin:0 0 14px;font-weight:900}
.tish-meals-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:clamp(10px,2vw,22px)}
.tish-meal-card{position:relative;isolation:isolate;display:flex;flex-direction:column;align-items:stretch;min-width:0;min-height:232px;padding:clamp(17px,2.5vw,28px);gap:8px;text-decoration:none!important;color:#28221d!important;background:none!important;border:0!important;box-shadow:none!important;transition:transform .22s ease}
.tish-meal-card::before{content:'';position:absolute;inset:0;z-index:-1;pointer-events:none;background:url('/assets/tish-paper/paper/category-ivory.webp') center/100% 100% no-repeat;filter:drop-shadow(0 5px 3px #573b2333)}
.tish-meal-heading{display:flex;align-items:center;justify-content:space-between;gap:8px;min-width:0}
.tish-meal-heading strong{font-size:clamp(15px,2.5vw,25px);font-weight:900;line-height:1.4;overflow-wrap:anywhere}
.meal-state-mark{flex:none;width:10px;height:10px;border-radius:50%;background:#a87528;box-shadow:0 0 0 4px #a8752820}
.tish-meal-card[data-state="open"] .meal-state-mark{background:#25834b;box-shadow:0 0 0 4px #25834b20}
.tish-meal-card[data-state="ended"] .meal-state-mark{background:#77716a;box-shadow:none}
.tish-meal-card .meal-hours{display:inline-block;align-self:flex-start;font-size:clamp(12px,1.7vw,16px);font-weight:800;color:#67594c;direction:ltr;unicode-bidi:isolate;font-variant-numeric:tabular-nums;white-space:nowrap}
.tish-meal-card .meal-message{font-size:clamp(11px,1.6vw,15px);color:#67594c;font-weight:700;line-height:1.45}
.meal-clock{display:flex;align-items:center;gap:3px;min-height:51px;color:#a91f17;font-weight:900;direction:ltr;unicode-bidi:isolate;font-variant-numeric:tabular-nums;line-height:1.15}
.meal-clock .meal-digit{display:inline-grid;place-items:center;min-width:1.6em;padding:5px 4px;background:#ac21170a;border:1px solid #a91f1725;border-radius:5px;font-size:clamp(22px,3.7vw,39px);letter-spacing:.015em}
.meal-clock .meal-colon{font-size:clamp(19px,3vw,32px);line-height:1;opacity:.8}
.meal-next{display:flex;flex-wrap:wrap;gap:5px;align-items:baseline;min-height:51px;color:#6c655c;font-size:clamp(14px,2vw,21px);font-weight:800}
.meal-next bdi{font-size:clamp(22px,3.3vw,36px);font-variant-numeric:tabular-nums}
.meal-progress{height:5px;width:100%;border-radius:4px;background:#a91f1719;overflow:hidden}
.meal-progress span{display:block;width:100%;height:100%;background:#a91f17;border-radius:4px;transition:width .8s linear}
.meal-cta{display:block;margin-top:auto;width:100%;text-align:center;padding:11px 8px;color:#fff4e7;background:url('/assets/tish-paper/paper/price-ticket.webp') center/100% 100% no-repeat;font-weight:900;font-size:clamp(11px,1.7vw,16px);line-height:1.4}
.tish-meal-card[data-state="upcoming"] .meal-cta,.tish-meal-card[data-state="ended"] .meal-cta{background:url('/assets/tish-paper/paper/nav-charcoal.webp') center/100% 100% no-repeat}
.tish-meal-card[data-state="open"]{cursor:pointer}
.tish-meal-card[data-state="open"]:hover{transform:translateY(-3px)}
.tish-meal-card[aria-disabled="true"]{cursor:default;transform:none!important}
.tish-meal-card[data-state="ended"]{color:#655f57!important}
.tish-meal-card[data-state="ended"]::before{filter:grayscale(1) brightness(.93) drop-shadow(0 3px 2px #573b2325)}
.tish-meal-card[data-state="ended"] .meal-cta{filter:grayscale(1);opacity:.68}
.tish-meal-card[data-state="ended"] .meal-hours,.tish-meal-card[data-state="ended"] .meal-message{color:#686158}
.tish-meal-card:focus-visible{outline:2px solid #a91f17;outline-offset:3px}
.tish-meals-all{display:inline-block;margin-top:12px;color:#a91f17!important;font-weight:800}
@media(max-width:600px){
 .tish-meals-grid{gap:8px}
 .tish-meal-card{min-height:190px;padding:14px 12px 17px;gap:6px}
 .meal-clock{min-height:40px;gap:2px}
 .meal-clock .meal-digit{font-size:clamp(17px,5.1vw,24px);min-width:1.5em;padding:3px 2px}
 .meal-clock .meal-colon{font-size:18px}
 .meal-next{min-height:40px}
 .meal-cta{padding:9px 5px}
}
@media(max-width:340px){.tish-meals-grid{grid-template-columns:1fr}}
@media(prefers-reduced-motion:reduce){.tish-meal-card,.meal-progress span{transition:none!important}}

/* Compact 12-hour display; preserve the existing TISH paper textures. */
.tish-meals{margin:18px 0 24px}
.tish-meals h2{margin-bottom:10px}
.tish-meals-grid{gap:clamp(8px,1.6vw,18px);max-width:1120px;margin-inline:auto}
.tish-meal-card{min-height:220px;padding:clamp(16px,2vw,23px) clamp(17px,2.4vw,27px) clamp(19px,2.5vw,28px);gap:7px}
.tish-meal-heading strong{font-size:clamp(16px,2.2vw,23px)}
.tish-meal-card .meal-hours{direction:inherit;white-space:normal;font-size:clamp(13px,1.7vw,17px);color:#493d30;font-weight:800;line-height:1.5}
.tish-meal-card .meal-message{font-size:clamp(12px,1.5vw,15px);color:#544637}
.tish-meal-card[data-state="open"] .meal-message{color:#32563b}
.meal-clock{justify-content:center;gap:2px;min-height:43px;margin:1px 0;color:#a91f17}
.meal-clock .meal-digit{font-size:clamp(23px,3.1vw,34px);padding:2px 0;background:transparent;border:0;min-width:1.24em;letter-spacing:0}
.meal-clock .meal-colon{font-size:clamp(18px,2.6vw,27px)}
.meal-next{min-height:43px;justify-content:center;align-items:center;gap:5px;font-size:clamp(12px,1.7vw,17px);color:#4e453b}
.meal-next bdi{font-size:clamp(18px,2.4vw,24px);white-space:nowrap}
.meal-progress{height:3px;background:#a91f1720}
.meal-cta{font-size:clamp(13px,1.6vw,16px);padding:10px 6px;min-height:39px}
.tish-meal-card[data-state="ended"]{color:#4e453b!important}
.tish-meal-card[data-state="ended"]::before{filter:grayscale(.65) brightness(.98) drop-shadow(0 3px 2px #573b2325)}
.tish-meal-card[data-state="ended"] .meal-hours,.tish-meal-card[data-state="ended"] .meal-message{color:#51483d}
.tish-meal-card[data-state="ended"] .meal-cta{opacity:.9}
@media(max-width:600px){
 .tish-meal-card{min-height:186px;padding:13px 12px 17px;gap:5px}
 .tish-meal-heading strong{font-size:clamp(14px,3.8vw,18px)}
 .tish-meal-card .meal-hours{font-size:12px}
 .tish-meal-card .meal-message{font-size:11px}
 .meal-clock{min-height:33px}
 .meal-clock .meal-digit{font-size:clamp(20px,5.2vw,25px)}
 .meal-clock .meal-colon{font-size:19px}
 .meal-next{min-height:33px;font-size:11px}
 .meal-next bdi{font-size:19px}
 .meal-cta{min-height:34px;padding:8px 5px;font-size:12px}
}
</style>
<h2>{{ $mealEn ? 'On the menu today' : 'وجبات اليوم' }}</h2>
<div class="tish-meals-grid">
@foreach($mealLabels as $key=>$label)
@php
    $window = $mealStatus['windows'][$key];
    $mealOpen = $window['state'] === 'open';
    $mealEnded = $window['state'] === 'ended';
    $mealLink = route('customer-menu.products',['location'=>$location->code,'meal'=>$key]);
    $remainingSeconds = max(0, (int) ceil((($mealOpen ? $window['ends_ms'] : $window['starts_ms']) - $mealStatus['server_ms']) / 1000));
    $mealDigits = [sprintf('%02d', intdiv($remainingSeconds,3600)),sprintf('%02d', intdiv($remainingSeconds % 3600,60)),sprintf('%02d',$remainingSeconds % 60)];
@endphp
<a class="tish-meal-card" data-meal="{{ $key }}" data-state="{{ $window['state'] }}" data-meal-url="{{ $mealLink }}" @if($mealOpen) href="{{ $mealLink }}" @else role="link" aria-disabled="true" tabindex="-1" @endif @if(!$window['enabled'] || !$window['count']) hidden @endif>
    <span class="tish-meal-heading"><strong>{{ $label }}</strong><span class="meal-state-mark" aria-hidden="true"></span></span>
    <small class="meal-hours" dir="{{ $mealEn ? 'ltr' : 'rtl' }}">{{ $mealRange($window['start'], $window['end']) }}</small>
    <small class="meal-message">{{ $mealOpen ? ($mealEn ? 'Available now · ends in' : 'متاح الآن · ينتهي بعد') : ($mealEnded ? ($mealEn ? 'Closed for today' : 'انتهى موعد اليوم') : ($mealEn ? 'Starts in' : 'يبدأ بعد')) }}</small>
    <span class="meal-clock" role="timer" aria-live="off" dir="ltr" @if($mealEnded) hidden @endif>
        <span class="meal-digit" data-part="hours">{{ $mealDigits[0] }}</span><span class="meal-colon" aria-hidden="true">:</span><span class="meal-digit" data-part="minutes">{{ $mealDigits[1] }}</span><span class="meal-colon" aria-hidden="true">:</span><span class="meal-digit" data-part="seconds">{{ $mealDigits[2] }}</span>
    </span>
    <span class="meal-next" @unless($mealEnded) hidden @endunless><span>{{ $mealEn ? 'Opens tomorrow at' : 'يبدأ غدًا الساعة' }}</span><bdi class="meal-next-time" dir="{{ $mealEn ? 'ltr' : 'rtl' }}">{{ $mealTime12($window['start']) }}</bdi></span>
    <span class="meal-progress" aria-hidden="true" @unless($mealOpen) hidden @endunless><span></span></span>
    <span class="meal-cta">{{ $mealOpen ? ($mealEn ? 'Order now' : 'اطلب الآن') : ($mealEnded ? ($mealEn ? 'See you tomorrow' : 'نلقاك غدًا') : ($mealEn ? 'Coming soon' : 'قريبًا')) }}</span>
</a>
@endforeach
</div>
@if(request()->query('meal') === 'breakfast' || request()->query('meal') === 'lunch')
<a href="{{ route('customer-menu.products',$location->code) }}" class="tish-meals-all">{{ $mealEn ? 'View all meals' : 'عرض كل الأصناف' }}</a>
@endif
</section>
<script>
window.TishMealSchedule = {{ \Illuminate\Support\Js::from($mealClient) }};
</script>
<script src="{{ asset('assets/js/customer-menu/meal-schedules.js') }}?v=20261128d" defer></script>
