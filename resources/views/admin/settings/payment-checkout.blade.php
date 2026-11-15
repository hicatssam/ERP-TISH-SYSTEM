@extends('layouts.app')
@section('title', 'إعدادات الدفع والطلبات')
@section('page-title', 'إعدادات الدفع والطلبات')
@section('content')
<div class="page-header"><div class="page-header-text">
<h1 class="page-heading">إعدادات الدفع والطلبات</h1>
<p class="page-subheading">تحكم بترتيبات الدفع المتاحة للكاشير وخيارات الدفع في منيو العميل. التغييرات تسري على الطلبات الجديدة فقط.</p>
</div><div class="page-header-actions"><a href="{{ route('payment-methods.index') }}" class="btn btn-outline btn-sm">إدارة طرق الدفع</a></div></div>
@if(session('success'))<div class="alert alert-success" style="margin-bottom:1rem">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" style="margin-bottom:1rem">{{ $errors->first() }}</div>@endif
<form action="{{ route('settings.payment-checkout.update') }}" method="POST">
@csrf
<div class="card" style="margin-bottom:1rem"><div class="card-header"><span class="card-title">خيارات نقطة بيع الكاشير</span></div>
<div class="card-body"><p class="page-subheading" style="margin-bottom:1rem">اختر ترتيبات الدفع التي يمكن للكاشير استخدامها. إخفاء الخيار لا يكفي وحده؛ النظام يرفض تسجيل طلب جديد بترتيب معطل.</p>
@foreach($arrangements as $arrangement)
<label style="display:flex;align-items:center;gap:.75rem;padding:.75rem;border-bottom:1px solid var(--border)">
<input type="checkbox" name="pos_arrangements[]" value="{{ $arrangement->value }}" @checked(in_array($arrangement->value, old('pos_arrangements', $enabledPos), true))>
<span>{{ $arrangement->label() }}</span>
</label>
@endforeach
</div></div>
<div class="card" style="margin-bottom:1rem"><div class="card-header"><span class="card-title">خيارات منيو العميل (QR)</span></div>
<div class="card-body"><p class="page-subheading" style="margin-bottom:1rem">العميل يختار طريقة الدفع: النقدية تعني الدفع عند الاستلام، والتحويل/البطاقة/المحفظة تعني الدفع الآن أو انتظار التحقق حسب إعداد الطريقة.</p>
@foreach([['name' => 'customer_cash', 'enabled' => $customerCash, 'label' => 'السماح بالدفع النقدي عند الاستلام'], ['name' => 'customer_non_cash', 'enabled' => $customerNonCash, 'label' => 'السماح بالدفع الإلكتروني والتحويلات']] as $option)
<div style="display:flex;align-items:center;gap:1rem;padding:.6rem 0">
<input type="hidden" name="{{ $option['name'] }}" value="0">
<label style="display:flex;align-items:center;gap:.75rem">
<input type="checkbox" name="{{ $option['name'] }}" value="1" @checked((bool) old($option['name'], $option['enabled']))>
{{ $option['label'] }}
</label></div>
@endforeach
<p class="page-subheading">لا يتم تفعيل «على الحساب» لمنيو العميل، لأنه يتطلب عميلًا مسجلًا وصلاحيات مالية في الكاشير.</p>
</div></div>
<div class="page-header-actions"><button type="submit" class="btn btn-gold">حفظ إعدادات الدفع</button></div>
</form>
@endsection
