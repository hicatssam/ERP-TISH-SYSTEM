@extends('layouts.app')

@section('title', 'تفاصيل الدور')
@section('page-title', 'تفاصيل الدور')

@php
    $roleLabels = \App\Support\PermissionUi::roleLabels();
    $groupLabels = \App\Support\PermissionUi::groupLabels();
    $permissionLabels = \App\Support\PermissionUi::permissionLabels();

    $selectedRolePermissionNames = $role->permissions->pluck('name')->all();
    $allPermissions = \Spatie\Permission\Models\Permission::query()
        ->where('guard_name', 'web')
        ->orderBy('name')
        ->get()
        ->groupBy(fn($p) => explode('.', $p->name)[0])
        ->map(fn ($items) => $items->groupBy(
            fn ($permission) => \App\Support\PermissionUi::permissionSection($permission->name)
        ));
@endphp

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h1 class="page-heading">الدور: {{ $roleLabels[$role->name] ?? $role->name }}</h1>
        <p class="page-subheading">{{ $role->permissions->count() }} صلاحية مرتبطة بهذا الدور</p>
    </div>

    <div class="page-header-actions">
        @can('roles.manage')
            @if(!in_array(strtolower($role->name), ['admin', 'super-admin'], true))
                <a href="{{ route('roles.edit', $role) }}" class="btn btn-outline btn-sm">
                    تعديل الدور
                </a>
            @endif
        @endcan

        <a href="{{ route('roles.index') }}" class="btn btn-ghost btn-sm">
            رجوع
        </a>
    </div>
</div>

<div class="dashboard-row">
    <div class="card">
        <div class="card-header">
            <span class="card-title">معلومات الدور</span>
        </div>

        <div class="card-body">
            <div class="detail-list">
                <div class="detail-row">
                    <span class="detail-label">اسم الدور</span>
                    <span class="detail-value">
                        <strong>{{ $roleLabels[$role->name] ?? $role->name }}</strong>
                    </span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">عدد الصلاحيات</span>
                    <span class="detail-value">
                        <span class="badge badge-gold">{{ $role->permissions->count() }}</span>
                    </span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">عدد المستخدمين</span>
                    <span class="detail-value">{{ $role->users->count() ?? 0 }} مستخدم</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">تاريخ الإنشاء</span>
                    <span class="detail-value">{{ $role->created_at->format('Y/m/d') }}</span>
                </div>

                @if(in_array(strtolower($role->name), ['admin', 'super-admin'], true))
                    <div class="detail-row">
                        <span class="detail-label">النوع</span>
                        <span class="detail-value">
                            <span class="badge badge-active">دور محمي — لا يمكن تعديله</span>
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">المستخدمون بهذا الدور</span>
        </div>

        <div class="card-body">
            @if(isset($role->users) && $role->users->count() > 0)
                <div style="display:flex;flex-direction:column;gap:.625rem">
                    @foreach($role->users->take(8) as $user)
                        <div style="display:flex;align-items:center;gap:.625rem;padding:.5rem 0;border-bottom:1px solid var(--border-light)">
                            <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--gold),var(--gold-deep));display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.75rem;color:#fff;flex-shrink:0">
                                {{ mb_substr($user->employee->full_name ?? $user->username, 0, 1) }}
                            </div>

                            <div>
                                <div style="font-size:.84rem;font-weight:600;color:var(--text)">
                                    {{ $user->employee->full_name ?? $user->username }}
                                </div>
                                <div style="font-size:.74rem;color:var(--text-muted)">
                                    {{ $user->username }}
                                </div>
                            </div>
                        </div>
                    @endforeach

                    @if($role->users->count() > 8)
                        <div style="font-size:.8rem;color:var(--text-muted);text-align:center;padding:.5rem 0">
                            و {{ $role->users->count() - 8 }} آخرون...
                        </div>
                    @endif
                </div>
            @else
                <div class="empty-state-sm">لا يوجد مستخدمون بهذا الدور</div>
            @endif
        </div>
    </div>
</div>

<div class="card" style="margin-top:1rem">
    <div class="card-header">
        <span class="card-title">
            صلاحيات الدور ({{ $role->permissions->count() }})
        </span>
    </div>

    <div class="card-body">
        @if($allPermissions->isEmpty())
            <div class="empty-state-sm">لا توجد صلاحيات مسجلة في النظام</div>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:1rem">
                @foreach($allPermissions as $group => $sections)
                    @php
                        $allGroupPermissions = $sections->flatMap(fn ($items) => $items);
                        $grantedCount = $allGroupPermissions->filter(
                            fn ($item) => in_array($item->name, $selectedRolePermissionNames, true)
                        )->count();
                    @endphp
                    <div style="border:1px solid var(--border);border-radius:12px;overflow:hidden">
                        <div style="background:var(--surface);padding:.75rem 1rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
                            <strong style="font-size:.82rem">{{ \App\Support\PermissionUi::groupLabel($group) }}</strong>
                            <span style="font-size:.75rem;color:var(--text-muted)">
                                {{ $grantedCount }}/{{ $allGroupPermissions->count() }}
                            </span>
                        </div>
                        @foreach($sections as $sectionName => $sectionPermissions)
                            <div style="padding:12px;border-top:1px solid var(--border)">
                                <strong style="display:block;color:var(--gold-deep);font-size:.78rem;margin-bottom:7px">
                                    {{ $sectionName }}
                                </strong>
                                @foreach($sectionPermissions as $perm)
                                    @php($has = in_array($perm->name, $selectedRolePermissionNames, true))
                                    <div style="display:flex;align-items:flex-start;gap:.5rem;margin-top:.5rem;font-size:.79rem;color:{{ $has ? 'var(--text)' : 'var(--text-subtle)' }}">
                                        <span aria-hidden="true">{{ $has ? '✓' : '×' }}</span>
                                        <span>
                                            {{ \App\Support\PermissionUi::permissionLabel($perm->name) }}
                                            <small dir="ltr" style="display:block;font-size:.69rem;color:var(--text-muted)">{{ $perm->name }}</small>
                                            @if(\App\Support\PermissionUi::isBroadPermission($perm->name) && $has)
                                                <small style="display:block;color:var(--text-muted)">صلاحية واسعة أو حساسة</small>
                                            @endif
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection