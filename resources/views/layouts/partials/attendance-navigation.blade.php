@php
    $attendanceFeatures = app(
        \App\Services\AttendanceFeatureService::class
    );
@endphp

@if($attendanceFeatures->attendanceEnabled())
    @can('attendance.view')
        @if(\Illuminate\Support\Facades\Route::has('attendance.index'))
            <a
                href="{{ route('attendance.index') }}"
                class="nav-item {{ request()->routeIs('attendance.*') && !request()->routeIs('attendance.leaves.*', 'attendance.corrections.*') ? 'active' : '' }}"
            >
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="9"/>
                    <path d="M12 7v5l3 2"/>
                </svg>
                <span>الحضور والدوام</span>
            </a>
        @endif
    @endcan
@endif

@if($attendanceFeatures->attendanceEnabled())
    @can('attendance.leaves.view')
        <a href="{{ route('attendance.leaves.index') }}"
           class="nav-item {{ request()->routeIs('attendance.leaves.*') ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="17" rx="2"/>
                <path d="M8 2v4M16 2v4M3 10h18M8 15l2 2 5-5"/>
            </svg>
            <span>إجازات الموظفين</span>
        </a>
    @endcan
@endif

@if($attendanceFeatures->attendanceEnabled())
    @can('attendance.approve')
        <a href="{{ route('attendance.corrections.index') }}"
           class="nav-item {{ request()->routeIs('attendance.corrections.*') ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="17" rx="2"/>
                <path d="M8 12h8M8 16h5M8 8h8"/>
            </svg>
            <span>تصحيحات الحضور</span>
        </a>
    @endcan
@endif

@if($attendanceFeatures->attendanceEnabled() && $attendanceFeatures->biometricEnabled())
    @can('attendance.manage')
        @if(app(\App\Services\FaceAttendanceService::class)->configured())
            <a href="{{ route('attendance.face.kiosk') }}" class="nav-item">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M8 3H3v5M16 3h5v5M3 16v5h5M21 16v5h-5"/>
                    <circle cx="12" cy="10" r="3"/><path d="M7 18c0-4 10-4 10 0"/>
                </svg>
                <span>كشك الحضور بالوجه</span>
            </a>
        @endif
    @endcan
@endif


@if($attendanceFeatures->attendanceEnabled())
    @can('attendance.manage')
        <a href="{{ route('attendance.webauthn.index') }}" class="nav-item {{ request()->routeIs('attendance.webauthn.*') ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="6" y="2" width="12" height="20" rx="3"/><path d="M10 18h4M9 10l2 2 4-4"/></svg>
            <span>مفاتيح التحقق بالجوال</span>
        </a>
    @endcan
@endif
