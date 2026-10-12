@extends('layouts.app')
@section('title', 'مفاتيح التحقق بالجوال')
@section('page-title', 'مفاتيح التحقق بالجوال')
@section('content')
<div class="page-actions">
    <div><h1 class="page-heading">مفاتيح التحقق بالجوال</h1><p class="page-subheading">تحقق من هوية الموظف وطلبه قبل اعتماد المفتاح. ألغِ المفتاح عند فقدانه أو تغيّر صاحبه.</p></div>
    <a class="btn btn-outline" href="{{ route('attendance.index') }}">الحضور والدوام</a>
</div>
@if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
<div class="card"><div class="card-body" style="overflow-x:auto">
    <table class="table"><thead><tr><th>الموظف</th><th>اسم المفتاح</th><th>الحالة</th><th>تاريخ التسجيل</th><th>آخر استخدام</th><th>إجراء</th></tr></thead><tbody>
    @forelse($credentials as $credential)
        <tr><td>{{ $credential->employee->full_name }}<small style="display:block">{{ $credential->employee->employee_number }}</small></td>
        <td>{{ $credential->label }}</td><td>{{ match($credential->status) { 'active' => 'معتمد', 'pending' => 'بانتظار الاعتماد', default => 'ملغى' } }}</td>
        <td>{{ $credential->created_at->format('Y-m-d H:i') }}</td><td>{{ $credential->last_used_at?->format('Y-m-d H:i') ?? '—' }}</td>
        <td>
            @if($credential->status === 'pending')
                <form method="POST" action="{{ route('attendance.webauthn.approve', $credential) }}" style="display:inline">@csrf<button class="btn btn-gold" type="submit">اعتماد</button></form>
            @endif
            @if($credential->status !== 'revoked')
                <form method="POST" action="{{ route('attendance.webauthn.revoke', $credential) }}" style="display:inline">@csrf<button class="btn btn-outline" type="submit">إلغاء المفتاح</button></form>
            @endif
        </td></tr>
    @empty<tr><td colspan="6">لم يسجّل أي موظف مفتاح تحقق بعد.</td></tr>@endforelse
    </tbody></table>
    {{ $credentials->links() }}
</div></div>
@endsection
