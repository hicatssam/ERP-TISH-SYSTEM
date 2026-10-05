@extends('layouts.app')

@section('title', 'بنرات المنيو')
@section('page-title', 'بنرات المنيو')

@push('styles')
<style>
.menu-banner-page {
    display:grid;
    gap:1rem;
}
.menu-banner-card {
    overflow:hidden;
}
.menu-banner-card .card-header {
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:1rem;
    flex-wrap:wrap;
}
.menu-banner-heading h3 {
    margin:0;
    font-size:1.05rem;
    font-weight:900;
}
.menu-banner-heading p {
    margin:.3rem 0 0;
    color:var(--text-muted);
    font-size:.8rem;
}
.menu-banner-location {
    min-width:220px;
}
.menu-banner-form {
    display:grid;
    gap:1rem;
}
.menu-banner-section {
    display:grid;
    gap:.85rem;
    padding:1rem;
    border:1px solid var(--border);
    border-radius:12px;
    background:var(--surface);
}
.menu-banner-section-title {
    font-size:.82rem;
    font-weight:900;
    padding-bottom:.55rem;
    border-bottom:1px solid var(--border);
}
.menu-banner-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:.85rem;
}
.menu-banner-grid.three {
    grid-template-columns:repeat(3,minmax(0,1fr));
}
.menu-banner-image-field {
    grid-column:1 / -1;
}
.menu-banner-file {
    position:relative;
    border:1px dashed var(--border);
    border-radius:12px;
    background:var(--card-bg,#fff);
    padding:1rem;
}
.menu-banner-file input[type="file"] {
    width:100%;
}
.menu-banner-help {
    display:block;
    margin-top:.35rem;
    color:var(--text-muted);
    font-size:.72rem;
}
.menu-banner-switch {
    display:flex;
    align-items:center;
    gap:.55rem;
    padding:.8rem 1rem;
    border:1px solid var(--border);
    border-radius:10px;
    background:var(--card-bg,#fff);
}
.menu-banner-actions {
    display:flex;
    justify-content:flex-end;
}
.menu-banner-actions .btn {
    min-width:150px;
}
.menu-banner-list {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(320px,1fr));
    gap:1rem;
}
.menu-banner-item {
    overflow:hidden;
}
.menu-banner-image {
    aspect-ratio:16/6;
    overflow:hidden;
    background:var(--surface);
}
.menu-banner-image img {
    display:block;
    width:100%;
    height:100%;
    object-fit:cover;
}
.menu-banner-meta {
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:.75rem;
}
.menu-banner-meta-title {
    font-weight:900;
}
.menu-banner-subtitle {
    color:var(--text-muted);
    font-size:.8rem;
    margin-top:.25rem;
}
.menu-banner-tags {
    display:flex;
    flex-wrap:wrap;
    gap:.35rem;
    margin-top:.7rem;
}
.menu-banner-footer {
    display:flex;
    gap:.5rem;
    flex-wrap:wrap;
    margin-top:1rem;
}
.menu-banner-edit {
    margin-top:.8rem;
    padding:.9rem;
    border:1px solid var(--border);
    border-radius:10px;
    background:var(--surface);
}
.menu-banner-edit-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:.65rem;
}
@media(max-width:760px) {
    .menu-banner-page { gap:.75rem; }
    .menu-banner-card .card-header { padding:.9rem; }
    .menu-banner-card .card-body { padding:.9rem; }
    .menu-banner-heading { width:100%; }
    .menu-banner-heading h3 { font-size:1rem; }
    .menu-banner-heading p { font-size:.74rem; line-height:1.6; }

    .menu-banner-grid,
    .menu-banner-grid.three,
    .menu-banner-edit-grid {
        grid-template-columns:1fr;
        gap:.7rem;
    }

    .menu-banner-section {
        padding:.8rem;
        gap:.7rem;
        border-radius:10px;
    }

    .menu-banner-location {
        min-width:100%;
        width:100%;
    }

    .menu-banner-file {
        padding:.8rem;
    }

    .menu-banner-file input[type="file"] {
        font-size:.78rem;
        max-width:100%;
    }

    .menu-banner-form .form-input,
    .menu-banner-form .form-select {
        width:100%;
        min-height:44px;
        font-size:16px;
    }

    .menu-banner-switch {
        padding:.72rem .8rem;
        align-items:flex-start;
    }

    .menu-banner-actions {
        position:sticky;
        bottom:.5rem;
        z-index:4;
        padding-top:.25rem;
    }

    .menu-banner-actions .btn {
        width:100%;
        min-height:46px;
        box-shadow:0 8px 22px rgba(15,23,42,.12);
    }

    .menu-banner-list {
        grid-template-columns:1fr;
        gap:.75rem;
    }

    .menu-banner-image {
        aspect-ratio:16/7;
    }

    .menu-banner-meta {
        gap:.5rem;
    }

    .menu-banner-footer {
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:.5rem;
    }

    .menu-banner-footer > form,
    .menu-banner-footer > details {
        width:100%;
        min-width:0 !important;
    }

    .menu-banner-footer .btn,
    .menu-banner-footer summary {
        width:100%;
    }

    .menu-banner-edit {
        grid-column:1/-1;
        padding:.7rem;
    }
}

@media(max-width:420px) {
    .menu-banner-footer {
        grid-template-columns:1fr;
    }

    .menu-banner-section-title {
        font-size:.78rem;
    }
}
</style>
@endpush

@section('content')
<div class="menu-banner-page">

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>تعذر حفظ البانر:</strong>
            <ul style="margin:.4rem 0 0;padding-inline-start:1.1rem">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card menu-banner-card">
        <div class="card-header">
            <div class="menu-banner-heading">
                <h3>بنرات منيو الفرع</h3>
                <p>إدارة الصور الترويجية التي تظهر أعلى منيو العملاء.</p>
            </div>

            @if($locations->count() > 1)
                <form method="GET" action="{{ route('restaurant.menu.banners.index') }}" class="menu-banner-location">
                    <label class="form-label" for="banner-location">الفرع</label>
                    <select
                        id="banner-location"
                        name="location_id"
                        class="form-select"
                        onchange="this.form.submit()"
                    >
                        @foreach($locations as $branch)
                            <option
                                value="{{ $branch->id }}"
                                {{ (int) $branch->id === (int) $location->id ? 'selected' : '' }}
                            >
                                {{ $branch->name_ar ?? $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>

        @can('restaurant_menu.manage')
            <div class="card-body">
                <form
                    method="POST"
                    action="{{ route('restaurant.menu.banners.store') }}"
                    enctype="multipart/form-data"
                    class="menu-banner-form"
                >
                    @csrf
                    <input type="hidden" name="location_id" value="{{ $location->id }}">

                    <section class="menu-banner-section">
                        <div class="menu-banner-section-title">الصورة والمحتوى</div>

                        <div class="menu-banner-grid">
                            <div class="form-group menu-banner-image-field">
                                <label class="form-label">الصورة *</label>
                                <div class="menu-banner-file">
                                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
                                    <small class="menu-banner-help">JPG / PNG / WebP — حتى 8MB. يفضّل مقاس أفقي عريض.</small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">العنوان</label>
                                <input class="form-input" type="text" name="title" maxlength="150" value="{{ old('title') }}" placeholder="مثال: عرض نهاية الأسبوع">
                            </div>

                            <div class="form-group">
                                <label class="form-label">النص الفرعي</label>
                                <input class="form-input" type="text" name="subtitle" maxlength="255" value="{{ old('subtitle') }}" placeholder="وصف قصير للعرض">
                            </div>

                            <div class="form-group">
                                <label class="form-label">الشارة</label>
                                <input class="form-input" type="text" name="badge_text" maxlength="80" value="{{ old('badge_text') }}" placeholder="مثال: جديد">
                            </div>

                            <div class="form-group">
                                <label class="form-label">الرابط</label>
                                <input class="form-input" type="text" name="link_url" maxlength="1000" value="{{ old('link_url') }}" placeholder="اختياري">
                            </div>
                        </div>
                    </section>

                    <section class="menu-banner-section">
                        <div class="menu-banner-section-title">الجدولة والظهور</div>

                        <div class="menu-banner-grid three">
                            <div class="form-group">
                                <label class="form-label">الترتيب</label>
                                <input class="form-input" type="number" min="0" max="9999" name="sort_order" value="{{ old('sort_order', 0) }}">
                            </div>

                            <div class="form-group">
                                <label class="form-label">يبدأ من</label>
                                <input class="form-input" type="datetime-local" name="starts_at" value="{{ old('starts_at') }}">
                            </div>

                            <div class="form-group">
                                <label class="form-label">ينتهي في</label>
                                <input class="form-input" type="datetime-local" name="ends_at" value="{{ old('ends_at') }}">
                            </div>
                        </div>

                        <label class="menu-banner-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                            <span>البانر فعال ويظهر في المنيو</span>
                        </label>
                    </section>

                    <div class="menu-banner-actions">
                        <button class="btn btn-gold" type="submit">إضافة البانر</button>
                    </div>
                </form>
            </div>
        @endcan
    </div>

    <div class="menu-banner-list">
        @forelse($banners as $banner)
            <div class="card menu-banner-item">
                <div class="menu-banner-image">
                    <img
                        src="{{ asset('storage/' . ltrim($banner->image, '/')) }}"
                        alt="{{ $banner->title ?: 'بانر المنيو' }}"
                    >
                </div>

                <div class="card-body">
                    <div class="menu-banner-meta">
                        <div>
                            <div class="menu-banner-meta-title">{{ $banner->title ?: 'بدون عنوان' }}</div>

                            @if($banner->subtitle)
                                <div class="menu-banner-subtitle">{{ $banner->subtitle }}</div>
                            @endif
                        </div>

                        <span class="badge {{ $banner->is_active ? 'badge-active' : 'badge-inactive' }}">
                            {{ $banner->is_active ? 'فعال' : 'متوقف' }}
                        </span>
                    </div>

                    <div class="menu-banner-tags">
                        <span class="badge">{{ $banner->location_id ? ($location->name_ar ?? $location->name) : 'عام لجميع الفروع' }}</span>
                        <span class="badge">الترتيب {{ $banner->sort_order }}</span>
                        @if($banner->badge_text)
                            <span class="badge">{{ $banner->badge_text }}</span>
                        @endif
                    </div>

                    @if($banner->location_id)
                        @can('restaurant_menu.manage')
                            <div class="menu-banner-footer">
                                <form method="POST" action="{{ route('restaurant.menu.banners.toggle-active', $banner) }}">
                                    @csrf
                                    <input type="hidden" name="location_id" value="{{ $location->id }}">
                                    <button class="btn btn-outline btn-sm" type="submit">
                                        {{ $banner->is_active ? 'إيقاف' : 'تفعيل' }}
                                    </button>
                                </form>

                                <details style="flex:1;min-width:120px">
                                    <summary class="btn btn-outline btn-sm" style="cursor:pointer;list-style:none;text-align:center">تعديل</summary>

                                    <div class="menu-banner-edit">
                                        <form
                                            method="POST"
                                            action="{{ route('restaurant.menu.banners.update', $banner) }}"
                                            enctype="multipart/form-data"
                                            style="display:grid;gap:.7rem"
                                        >
                                            @csrf
                                            <input type="hidden" name="location_id" value="{{ $location->id }}">

                                            <div class="menu-banner-edit-grid">
                                                <div class="form-group" style="grid-column:1/-1">
                                                    <label class="form-label">استبدال الصورة</label>
                                                    <input class="form-input" type="file" name="image" accept="image/jpeg,image/png,image/webp">
                                                </div>

                                                <div class="form-group">
                                                    <label class="form-label">العنوان</label>
                                                    <input class="form-input" type="text" name="title" value="{{ $banner->title }}">
                                                </div>

                                                <div class="form-group">
                                                    <label class="form-label">النص الفرعي</label>
                                                    <input class="form-input" type="text" name="subtitle" value="{{ $banner->subtitle }}">
                                                </div>

                                                <div class="form-group">
                                                    <label class="form-label">الشارة</label>
                                                    <input class="form-input" type="text" name="badge_text" value="{{ $banner->badge_text }}">
                                                </div>

                                                <div class="form-group">
                                                    <label class="form-label">الرابط</label>
                                                    <input class="form-input" type="text" name="link_url" value="{{ $banner->link_url }}">
                                                </div>

                                                <div class="form-group">
                                                    <label class="form-label">الترتيب</label>
                                                    <input class="form-input" type="number" min="0" max="9999" name="sort_order" value="{{ $banner->sort_order }}">
                                                </div>

                                                <div class="form-group">
                                                    <label class="form-label">يبدأ من</label>
                                                    <input class="form-input" type="datetime-local" name="starts_at" value="{{ optional($banner->starts_at)->format('Y-m-d\TH:i') }}">
                                                </div>

                                                <div class="form-group">
                                                    <label class="form-label">ينتهي في</label>
                                                    <input class="form-input" type="datetime-local" name="ends_at" value="{{ optional($banner->ends_at)->format('Y-m-d\TH:i') }}">
                                                </div>
                                            </div>

                                            <label class="menu-banner-switch">
                                                <input type="hidden" name="is_active" value="0">
                                                <input type="checkbox" name="is_active" value="1" {{ $banner->is_active ? 'checked' : '' }}>
                                                <span>فعال</span>
                                            </label>

                                            <button class="btn btn-gold" type="submit">حفظ التعديل</button>
                                        </form>
                                    </div>
                                </details>

                                <form
                                    method="POST"
                                    action="{{ route('restaurant.menu.banners.destroy', $banner) }}"
                                    onsubmit="return confirm('هل تريد حذف هذا البانر؟')"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="location_id" value="{{ $location->id }}">
                                    <button class="btn btn-danger btn-sm" type="submit">حذف</button>
                                </form>
                            </div>
                        @endcan
                    @else
                        <div style="margin-top:1rem;color:var(--text-muted);font-size:.78rem">
                            البانر العام يظهر هنا للمعاينة فقط ولا يتم تعديله من شاشة الفرع.
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="card">
                <div class="card-body" style="text-align:center;color:var(--text-muted)">
                    لا توجد بنرات لهذا الفرع حتى الآن.
                </div>
            </div>
        @endforelse
    </div>
</div>
@endsection
