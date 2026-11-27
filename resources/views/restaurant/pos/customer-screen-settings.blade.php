@extends('layouts.app')
@section('title', 'إعلانات شاشة الزبون')
@section('page-title', 'إعلانات شاشة الزبون')
@section('content')
<div class="page-header"><div class="page-header-text">
<h1 class="page-heading">إعلانات شاشة الزبون</h1>
<p class="page-subheading">{{ $location->name }} — تظهر التغييرات على الشاشة المفتوحة تلقائيًا.</p>
</div></div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="card" style="margin-bottom:1rem"><div class="card-body">
<form method="POST" action="{{ route('restaurant.pos.customer-screen.update', ['location_id' => $location->id]) }}">
@csrf
<input type="hidden" name="action" value="save">
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:1rem">
<label>مدة عرض الصورة (ثانية)<input class="form-input" type="number" name="image_seconds" min="3" max="60" value="{{ $config['image_seconds'] }}" required></label>
<label>مدة رسالة الشكر (ثانية)<input class="form-input" type="number" name="thanks_seconds" min="4" max="30" value="{{ $config['thanks_seconds'] }}" required></label>
<label>نسبة الإعلان أثناء الطلب (%)<input class="form-input" type="number" name="ad_percent" min="30" max="70" value="{{ $config['ad_percent'] }}" required></label>
</div>
<p style="margin:.8rem 0">الفيديو يُشغل تلقائيًا بدون صوت. يمكن تشغيل الصوت من شاشة الزبون نفسها.</p>
<button class="btn btn-gold" type="submit">حفظ الإعدادات</button>
</form>
</div></div>
<div class="card"><div class="card-body">
<h2>قائمة تشغيل الإعلانات ({{ count($config['media']) }}/10)</h2>
<form method="POST" enctype="multipart/form-data" action="{{ route('restaurant.pos.customer-screen.update', ['location_id' => $location->id]) }}" style="margin:1rem 0;display:flex;gap:1rem;flex-wrap:wrap;align-items:center">
@csrf
<input type="hidden" name="action" value="add">
<input type="file" name="media" accept="image/jpeg,image/png,image/webp,video/mp4" required>
<button class="btn btn-gold" type="submit">إضافة إعلان</button>
<small>MP4 / JPEG / PNG / WebP، حتى 50 MB بحسب حد رفع الخادم.</small>
</form>
@foreach($config['media'] as $media)
<div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;padding:12px 0;border-bottom:1px solid var(--border)">
<span>{{ $loop->iteration }}</span>
@if($media['type'] === 'video')
<video src="{{ $media['url'] }}" controls muted preload="metadata" style="width:180px;max-height:110px;object-fit:contain"></video>
@else
<img src="{{ $media['url'] }}" alt="إعلان" style="width:180px;max-height:110px;object-fit:contain">
@endif
<form method="POST" action="{{ route('restaurant.pos.customer-screen.update', ['location_id' => $location->id]) }}">
@csrf
<input type="hidden" name="media_id" value="{{ $media['id'] }}">
<button class="btn btn-outline btn-sm" name="action" value="up" @disabled($loop->first)>↑</button>
<button class="btn btn-outline btn-sm" name="action" value="down" @disabled($loop->last)>↓</button>
<button class="btn btn-outline btn-sm" name="action" value="remove" onclick="return confirm('حذف الإعلان؟')">حذف</button>
</form>
</div>
@endforeach
@if(empty($config['media']))<p>لا توجد إعلانات مرفوعة. يظهر شعار TISH على شاشة الانتظار.</p>@endif
</div></div>
@endsection
