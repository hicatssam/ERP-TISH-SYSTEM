@extends('layouts.app')
@section('title', 'الفروع والمواقع')
@section('content')
<div class="page-actions">
    <div class="page-actions-title">الفروع والمواقع</div>
    <div class="action-btns">
        @can('locations.manage')
        <a href="{{ route('locations.create') }}" class="btn btn-gold">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            إضافة موقع
        </a>
        @endcan
    </div>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>الاسم</th>
                <th>الرمز</th>
                <th>النوع</th>
                <th>الهاتف</th>
                <th>الموظفون</th>
                <th>الحالة</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
        @forelse($locations as $location)
            <tr>
                <td><strong>{{ $location->name }}</strong></td>
                <td><code>{{ $location->code }}</code></td>
                <td>{{ $location->type === 'factory' ? 'مصنع' : 'فرع' }}</td>
                <td>{{ $location->phone ?? '—' }}</td>
                <td>{{ $location->employees_count ?? 0 }}</td>
                <td>
                    <span class="badge {{ $location->is_active ? 'badge-active' : 'badge-inactive' }}">
                        {{ $location->is_active ? 'نشط' : 'معطل' }}
                    </span>
                </td>
                <td>
                    <div class="actions">
                        <a href="{{ route('locations.show', $location) }}" class="btn btn-ghost btn-sm">عرض</a>
                        <a href="{{ route('locations.payment-accounts.index', $location) }}" class="btn btn-ghost btn-sm">حسابات الدفع</a>
                        @can('locations.manage')
                        <a href="{{ route('locations.edit', $location) }}" class="btn btn-outline btn-sm">تعديل</a>
                        <form action="{{ route('locations.toggle', $location) }}" method="POST" style="display:inline">
                            @csrf
                            <input type="hidden" name="expected_active" value="{{ $location->is_active ? 1 : 0 }}">
                            <button type="button" class="btn btn-ghost btn-sm" data-open-dialog="location-status-{{ $location->id }}">{{ $location->is_active ? 'تعطيل' : 'تفعيل' }}</button>
                            <x-action-dialog id="location-status-{{ $location->id }}" title="{{ $location->is_active ? 'تأكيد تعطيل الموقع' : 'تأكيد تفعيل الموقع' }}" description="الموقع: {{ $location->name }}">
                                <p>{{ $location->is_active ? 'سيتم إيقاف الموقع عن العمل في النظام. تأكد من عدم وجود عمليات جارية.' : 'سيصبح الموقع نشطًا في النظام.' }}</p>
                                <div class="action-btns"><button type="submit" class="btn btn-gold">{{ $location->is_active ? 'تأكيد التعطيل' : 'تأكيد التفعيل' }}</button><button type="button" class="btn btn-ghost" data-close-dialog>إلغاء</button></div>
                            </x-action-dialog>
                        </form>
                        @endcan
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty-state-sm">لا توجد مواقع مسجلة.</div></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
