@extends('layouts.app')
@section('title','مواعيد الوجبات')
@section('page-title','مواعيد الوجبات')
@section('content')
<div class="page-header"><h1>مواعيد الفطور والغداء</h1><p>{{ $location->name }} — توقيت غزة (Asia/Gaza)</p></div>
<a href="{{ route('restaurant.menu.index',['location_id'=>$location->id]) }}">العودة إلى منيو المطعم</a>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('restaurant.menu.meal-schedules.update',['location_id'=>$location->id]) }}">
@csrf
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;margin:16px 0">
@foreach(['breakfast'=>'وجبات الفطور','lunch'=>'وجبات الغداء'] as $key=>$name)
<div class="card" style="padding:18px">
<h2>{{ $name }}</h2>
<input type="hidden" name="{{ $key }}[enabled]" value="0">
<label><input type="checkbox" name="{{ $key }}[enabled]" value="1" @checked(old($key.'.enabled',$settings[$key]['enabled']))> تفعيل الفترة في المنيو الإلكتروني</label>
<div style="display:flex;gap:16px;flex-wrap:wrap;margin:12px 0">
<label>من <input class="form-input" type="time" name="{{ $key }}[start]" value="{{ old($key.'.start',$settings[$key]['start']) }}" required></label>
<label>إلى <input class="form-input" type="time" name="{{ $key }}[end]" value="{{ old($key.'.end',$settings[$key]['end']) }}" required></label>
</div>
</div>
@endforeach
</div>
<p>الجدول يتكرر يوميًا. المنتجات المرتبطة بفترة معطلة أو منتهية لا يمكن طلبها إلكترونيًا. المنتجات غير المرتبطة تبقى دون قيد زمني. الكاشير لا يتغير.</p>
<div class="card" style="padding:18px">
<h2>توزيع الأصناف</h2>
<label for="mealSearch">بحث</label>
<input id="mealSearch" type="search" class="form-input" placeholder="ابحث عن صنف" style="margin-bottom:12px">
@foreach($items as $item)
<div class="meal-admin-row" style="display:grid;grid-template-columns:minmax(0,1fr) minmax(140px,220px);gap:12px;align-items:center;padding:9px 0;border-bottom:1px solid var(--border)">
<span>{{ $item->displayName() }} @if(!$item->is_active || !$item->show_in_qr)<small> (غير منشور إلكترونيًا)</small>@endif</span>
<select class="form-input" name="assignments[{{ $item->product_id }}]">
@foreach([''=>'بدون قيد زمني','breakfast'=>'الفطور','lunch'=>'الغداء'] as $value=>$label)
<option value="{{ $value }}" @selected(old('assignments.'.$item->product_id,$assigned[$item->product_id] ?? '') === $value)>{{ $label }}</option>
@endforeach
</select>
</div>
@endforeach
</div>
<input type="hidden" name="form_complete" value="1">
<button class="btn btn-gold" type="submit" style="margin:16px 0">حفظ مواعيد الوجبات والأصناف</button>
</form>
<script>
document.getElementById('mealSearch').addEventListener('input', function(){
    const q = this.value.trim().toLowerCase();
    document.querySelectorAll('.meal-admin-row').forEach(row => row.hidden = !row.querySelector('span').textContent.toLowerCase().includes(q));
});
</script>
@endsection
