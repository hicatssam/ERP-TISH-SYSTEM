@extends('layouts.app')

@section('title', 'إضافة دور')
@section('page-title', 'إضافة دور')

@php
    $groupLabels = \App\Support\PermissionUi::groupLabels();
    $permissionLabels = \App\Support\PermissionUi::permissionLabels();
@endphp

@section('content')
<div class="page-header">
    <h1 class="page-heading">إضافة دور جديد</h1>
    <p class="page-subheading">
        <a href="{{ route('roles.index') }}">الأدوار</a>
        &laquo; إضافة
    </p>
</div>

<div class="card" style="max-width:900px">
    <div class="card-body">
        <form action="{{ route('roles.store') }}" method="POST">
            @csrf

            <div style="display:grid;gap:1.5rem">
                <div class="form-group">
                    <label class="form-label" for="role-name">اسم الدور *</label>

                    <input
                        id="role-name"
                        type="text"
                        name="name"
                        maxlength="50"
                        class="form-input @error('name') is-invalid @enderror"
                        value="{{ old('name') }}"
                        required
                    >

                    @error('name')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <div class="form-label" style="margin-bottom:.75rem">الصلاحيات</div>

                    @error('permissions')
                        <span class="form-error" style="display:block;margin-bottom:.75rem">{{ $message }}</span>
                    @enderror

                    @error('permissions.*')
                        <span class="form-error" style="display:block;margin-bottom:.75rem">{{ $message }}</span>
                    @enderror

                    <p style="font-size:.83rem;color:var(--text-muted);margin-bottom:10px">
                        جميع صلاحيات النظام مفصولة حسب القسم والإجراء، بما فيها صلاحيات الكيك الخاص وكيك الفروع وحلويات الفروع. الأكواد لا تتغير، والبحث لا يحذف صلاحيات الدور.
                    </p>
                    @include('admin.roles.partials.permission-matrix', [
                        'permissions' => $permissions,
                        'selectedPermissions' => old('permissions', []),
                    ])
                </div>

                <div style="display:flex;gap:.75rem">
                    <button class="btn btn-gold" type="submit">حفظ الدور</button>
                    <a href="{{ route('roles.index') }}" class="btn btn-ghost">إلغاء</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection