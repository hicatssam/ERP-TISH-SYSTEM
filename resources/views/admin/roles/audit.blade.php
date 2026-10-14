@extends('layouts.app')

@section('title', 'تدقيق الأدوار والصلاحيات')
@section('page-title', 'تدقيق الأدوار والصلاحيات')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-heading">تدقيق الأدوار والصلاحيات</h1>
        <p class="page-subheading">
            تقرير قراءة فقط من الصلاحيات الممنوحة فعليًا لكل دور في قاعدة البيانات.
            لا يضيف ولا يسحب أي صلاحية.
        </p>
    </div>
    <a href="{{ route('roles.index') }}" class="btn btn-ghost">العودة للأدوار</a>
</div>

<div class="dashboard-row" style="margin-bottom:1rem">
    <div class="card"><div class="card-body">
        <small>الأدوار</small>
        <strong style="display:block;font-size:1.4rem">{{ $roleReports->count() }}</strong>
    </div></div>
    <div class="card"><div class="card-body">
        <small>الصلاحيات المسجلة</small>
        <strong style="display:block;font-size:1.4rem">{{ $permissions->count() }}</strong>
    </div></div>
    <div class="card"><div class="card-body">
        <small>أكواد بدون مسمى موثّق</small>
        <strong style="display:block;font-size:1.4rem">{{ $unlabelled->count() }}</strong>
    </div></div>
    <div class="card"><div class="card-body">
        <small>صلاحيات مسارات غير مسجلة</small>
        <strong style="display:block;font-size:1.4rem">{{ $missingRegistered->count() }}</strong>
    </div></div>
</div>

@if($unlabelled->isNotEmpty() || $ungrouped->isNotEmpty() || $missingRegistered->isNotEmpty())
    <div class="card" style="margin-bottom:1rem">
        <div class="card-header"><span class="card-title">ملاحظات تحتاج متابعة</span></div>
        <div class="card-body">
            @if($unlabelled->isNotEmpty())
                <p><strong>صلاحيات غير موثّقة في قاموس العرض ({{ $unlabelled->count() }}):</strong></p>
                <p style="font-size:.82rem;color:var(--text-muted)">
                    هذه الأكواد موجودة في قاعدة البيانات. لا تُحذف قبل فحص استخدامها وصلاحيات الأدوار المرتبطة بها.
                </p>
                <div style="display:flex;flex-wrap:wrap;gap:.35rem;margin-bottom:1rem">
                    @foreach($unlabelled as $permission)
                        <code dir="ltr" style="border:1px solid var(--border);border-radius:7px;padding:.25rem .4rem">{{ $permission->name }}</code>
                    @endforeach
                </div>
            @endif

            @if($ungrouped->isNotEmpty())
                <p><strong>مجموعات غير موثّقة:</strong>
                    {{ $ungrouped->implode('، ') }}
                </p>
            @endif

            @if($missingRegistered->isNotEmpty())
                <p><strong>صلاحيات مستخدمة في مسارات محمية ولكنها غير مسجلة في قاعدة البيانات:</strong></p>
                <div style="display:flex;flex-wrap:wrap;gap:.35rem">
                    @foreach($missingRegistered as $permissionName)
                        <code dir="ltr" style="border:1px solid var(--border);border-radius:7px;padding:.25rem .4rem">{{ $permissionName }}</code>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endif

<div class="card">
    <div class="card-header">
        <span class="card-title">الصلاحيات الفعلية بحسب الدور</span>
    </div>
    <div class="card-body">
        <p style="color:var(--text-muted);font-size:.84rem">
            افتح تفاصيل أي دور لاستعراض كل صلاحياته وأكوادها. العناصر التي تحتاج مراجعة لا
            تعني بالضرورة وجود خطأ؛ ربما مُنحت للدور عمدًا. لا يجري سحب أي شيء تلقائيًا.
        </p>

        @forelse($roleReports as $report)
            @php
                $role = $report['role'];
                $unknown = $report['unknown'];
                $review = $report['review'];
                $groups = $report['groups'];
            @endphp
            <details style="padding:.85rem 1rem;border:1px solid var(--border);border-radius:12px;margin-top:.75rem">
                <summary style="cursor:pointer;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.65rem">
                    <span>
                        <strong>{{ \App\Support\PermissionUi::roleLabel($role->name) }}</strong>
                        <small dir="ltr" style="color:var(--text-muted);margin-inline-start:.4rem">{{ $role->name }}</small>
                    </span>
                    <span style="font-size:.8rem;color:var(--text-muted)">
                        {{ $role->permissions->count() }} صلاحية · {{ $role->users_count }} مستخدم
                        @if($review->isNotEmpty())
                            · {{ $review->count() }} صلاحية مرتفعة تحتاج مراجعة
                        @endif
                        @if($unknown->isNotEmpty())
                            · {{ $unknown->count() }} بدون مسمى
                        @endif
                    </span>
                </summary>

                @if($review->isNotEmpty())
                    <div style="background:var(--surface);border-radius:10px;padding:.8rem;margin-top:1rem">
                        <strong>صلاحيات مرتفعة أو شاملة للفروع — راجع الحاجة إليها:</strong>
                        <ul style="margin:.55rem 0 0;padding-inline-start:1.4rem">
                            @foreach($review as $permission)
                                <li>
                                    {{ \App\Support\PermissionUi::permissionLabel($permission->name) }}
                                    <code dir="ltr">{{ $permission->name }}</code>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;margin-top:1rem">
                    @foreach($groups as $group => $groupPermissions)
                        <div style="padding:.8rem;border:1px solid var(--border);border-radius:10px">
                            <strong>{{ \App\Support\PermissionUi::groupLabel($group) }} ({{ $groupPermissions->count() }})</strong>
                            <ul style="padding-inline-start:1rem;margin:.5rem 0 0;font-size:.82rem">
                                @foreach($groupPermissions as $permission)
                                    <li style="margin:.4rem 0">
                                        {{ \App\Support\PermissionUi::permissionLabel($permission->name) }}
                                        <small dir="ltr" style="display:block;color:var(--text-muted)">{{ $permission->name }}</small>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>

                <a href="{{ route('roles.show', $role) }}" class="btn btn-outline btn-sm" style="margin-top:1rem">تفاصيل هذا الدور</a>
            </details>
        @empty
            <div class="empty-state-sm">لا توجد أدوار متاحة للتدقيق.</div>
        @endforelse
    </div>
</div>
@endsection
