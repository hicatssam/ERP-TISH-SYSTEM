@extends('layouts.app')
@section('title','وحدات القياس')

@push('styles')
<style>
.unit-actions {
    display:flex;
    align-items:center;
    gap:.4rem;
    flex-wrap:wrap;
}

.unit-edit-modal {
    position:fixed;
    inset:0;
    z-index:99999;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:1rem;
    background:rgba(15,23,42,.58);
    backdrop-filter:blur(3px);
    opacity:0;
    visibility:hidden;
    pointer-events:none;
    transition:.18s ease;
}

.unit-edit-modal.is-open {
    opacity:1;
    visibility:visible;
    pointer-events:auto;
}

.unit-edit-dialog {
    width:min(700px,96vw);
    max-height:90vh;
    overflow:hidden;
    background:var(--card-bg,#fff);
    border:1px solid var(--border);
    border-radius:18px;
    box-shadow:0 24px 70px rgba(0,0,0,.25);
    transform:translateY(14px) scale(.985);
    transition:.18s ease;
}

.unit-edit-modal.is-open .unit-edit-dialog {
    transform:translateY(0) scale(1);
}

.unit-edit-header {
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:1rem;
    padding:1rem 1.2rem;
    border-bottom:1px solid var(--border);
}

.unit-edit-header h3 {
    margin:0;
    font-size:1.05rem;
    font-weight:900;
}

.unit-edit-header p {
    margin:.3rem 0 0;
    color:var(--text-muted);
    font-size:.75rem;
}

.unit-edit-close {
    width:38px;
    height:38px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border:1px solid var(--border);
    border-radius:50%;
    background:var(--surface,#fff);
    color:var(--text-muted);
    font-size:1.35rem;
    cursor:pointer;
}

.unit-edit-body {
    max-height:calc(90vh - 150px);
    overflow-y:auto;
    padding:1.2rem;
}

.unit-edit-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:.8rem;
}

.unit-edit-footer {
    display:flex;
    justify-content:flex-end;
    gap:.6rem;
    margin-top:1rem;
}

@media(max-width:650px) {
    .unit-edit-grid {
        grid-template-columns:1fr;
    }

    .unit-edit-footer {
        flex-direction:column-reverse;
    }

    .unit-edit-footer .btn {
        width:100%;
    }
}
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-heading">وحدات القياس</h1>
        <p class="page-subheading">الوحدات المستخدمة في المنتجات الحالية والمستقبلية.</p>
    </div>
</div>

@include('admin.catalog._nav')

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:1rem">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:1rem">
        <strong>تعذر تنفيذ العملية:</strong>
        <ul style="margin:.4rem 0 0;padding-inline-start:1.1rem">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="dashboard-row">
    <div class="card">
        <div class="card-header">
            <span class="card-title">إضافة وحدة</span>
        </div>

        <div class="card-body">
            <form method="POST" action="{{ route('catalog.units.store') }}">
                @csrf

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
                    <div class="form-group">
                        <label class="form-label">الكود *</label>
                        <input name="code" class="form-input" required placeholder="piece">
                    </div>

                    <div class="form-group">
                        <label class="form-label">الاسم الإنجليزي *</label>
                        <input name="name" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">الاسم العربي</label>
                        <input name="name_ar" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">الرمز</label>
                        <input name="symbol" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">الدقة العشرية</label>
                        <input name="precision" type="number" min="0" max="6" value="0" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">الترتيب</label>
                        <input name="sort_order" type="number" min="0" value="0" class="form-input">
                    </div>
                </div>

                <label style="display:flex;gap:.5rem;align-items:center;margin:.8rem 0">
                    <input type="checkbox" name="allow_decimal" value="1">
                    السماح بالكميات العشرية
                </label>

                <button class="btn btn-gold">إضافة الوحدة</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">الوحدات الحالية</span>
        </div>

        <div class="table-wrap" style="border:0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>الوحدة</th>
                        <th>الكود</th>
                        <th>الرمز</th>
                        <th>الدقة</th>
                        <th>منتجات</th>
                        <th>الحالة</th>
                        <th>الإجراء</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($units as $unit)
                        <tr>
                            <td>
                                <strong>{{ $unit->displayName() }}</strong>
                                @if($unit->name_ar && $unit->name)
                                    <div style="font-size:.72rem;color:var(--text-muted)">
                                        {{ $unit->name }}
                                    </div>
                                @endif
                            </td>

                            <td><code>{{ $unit->code }}</code></td>
                            <td>{{ $unit->symbol ?: '—' }}</td>
                            <td>{{ $unit->allow_decimal ? $unit->precision : 0 }}</td>
                            <td>{{ $unit->products_count }}</td>

                            <td>
                                <span class="badge {{ $unit->is_active ? 'badge-active' : 'badge-inactive' }}">
                                    {{ $unit->is_active ? 'مفعلة' : 'معطلة' }}
                                </span>
                            </td>

                            <td>
                                <div class="unit-actions">
                                    <button
                                        type="button"
                                        class="btn btn-outline btn-sm js-edit-unit"
                                        data-update-url="{{ route('catalog.units.update', $unit) }}"
                                        data-code="{{ $unit->code }}"
                                        data-name="{{ $unit->name }}"
                                        data-name-ar="{{ $unit->name_ar }}"
                                        data-symbol="{{ $unit->symbol }}"
                                        data-precision="{{ $unit->precision }}"
                                        data-sort-order="{{ $unit->sort_order }}"
                                        data-allow-decimal="{{ $unit->allow_decimal ? '1' : '0' }}"
                                        data-products-count="{{ $unit->products_count }}"
                                    >
                                        تعديل
                                    </button>

                                    <form method="POST" action="{{ route('catalog.units.toggle', $unit) }}">
                                        @csrf
                                        <button class="btn btn-ghost btn-sm">
                                            {{ $unit->is_active ? 'تعطيل' : 'تفعيل' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state-sm">لا توجد وحدات قياس.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="unitEditModal" class="unit-edit-modal" aria-hidden="true">
    <div class="unit-edit-dialog" role="dialog" aria-modal="true" aria-labelledby="unitEditTitle">
        <div class="unit-edit-header">
            <div>
                <h3 id="unitEditTitle">تعديل وحدة القياس</h3>
                <p id="unitEditHelp">عدّل بيانات الوحدة ثم اضغط حفظ التعديلات.</p>
            </div>

            <button
                type="button"
                class="unit-edit-close"
                id="closeUnitEditModal"
                aria-label="إغلاق"
            >×</button>
        </div>

        <div class="unit-edit-body">
            <form id="unitEditForm" method="POST" action="">
                @csrf
                @method('PUT')

                <div class="unit-edit-grid">
                    <div class="form-group">
                        <label class="form-label">الكود *</label>
                        <input id="editUnitCode" name="code" class="form-input" required maxlength="40">
                    </div>

                    <div class="form-group">
                        <label class="form-label">الاسم الإنجليزي *</label>
                        <input id="editUnitName" name="name" class="form-input" required maxlength="100">
                    </div>

                    <div class="form-group">
                        <label class="form-label">الاسم العربي</label>
                        <input id="editUnitNameAr" name="name_ar" class="form-input" maxlength="100">
                    </div>

                    <div class="form-group">
                        <label class="form-label">الرمز</label>
                        <input id="editUnitSymbol" name="symbol" class="form-input" maxlength="20">
                    </div>

                    <div class="form-group">
                        <label class="form-label">الدقة العشرية</label>
                        <input id="editUnitPrecision" name="precision" type="number" min="0" max="6" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">الترتيب</label>
                        <input id="editUnitSortOrder" name="sort_order" type="number" min="0" class="form-input">
                    </div>
                </div>

                <label style="display:flex;gap:.5rem;align-items:center;margin:1rem 0">
                    <input id="editUnitAllowDecimal" type="checkbox" name="allow_decimal" value="1">
                    السماح بالكميات العشرية
                </label>

                <div id="unitUsageWarning" style="display:none;padding:.75rem .9rem;border-radius:10px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-size:.78rem">
                    هذه الوحدة مستخدمة في منتجات. تعديل الكود أو إعدادات الدقة سيؤثر على طريقة عرض واستخدام هذه المنتجات.
                </div>

                <div class="unit-edit-footer">
                    <button type="button" class="btn btn-ghost" id="cancelUnitEditModal">إلغاء</button>
                    <button type="submit" class="btn btn-gold">حفظ التعديلات</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const modal = document.getElementById('unitEditModal');
    const form = document.getElementById('unitEditForm');
    const closeButton = document.getElementById('closeUnitEditModal');
    const cancelButton = document.getElementById('cancelUnitEditModal');
    const warning = document.getElementById('unitUsageWarning');

    if (!modal || !form) {
        return;
    }

    function openModal(button) {
        form.action = button.dataset.updateUrl || '';

        document.getElementById('editUnitCode').value = button.dataset.code || '';
        document.getElementById('editUnitName').value = button.dataset.name || '';
        document.getElementById('editUnitNameAr').value = button.dataset.nameAr || '';
        document.getElementById('editUnitSymbol').value = button.dataset.symbol || '';
        document.getElementById('editUnitPrecision').value = button.dataset.precision || '0';
        document.getElementById('editUnitSortOrder').value = button.dataset.sortOrder || '0';
        document.getElementById('editUnitAllowDecimal').checked = button.dataset.allowDecimal === '1';

        const productsCount = Number(button.dataset.productsCount || 0);
        warning.style.display = productsCount > 0 ? 'block' : 'none';

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        setTimeout(function () {
            document.getElementById('editUnitName').focus();
        }, 50);
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.js-edit-unit').forEach(function (button) {
        button.addEventListener('click', function () {
            openModal(button);
        });
    });

    closeButton?.addEventListener('click', closeModal);
    cancelButton?.addEventListener('click', closeModal);

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });
})();
</script>
@endpush
