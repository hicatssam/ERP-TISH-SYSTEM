@php
    $selectedPermissionNames = old('permissions', $selectedPermissions ?? []);
    $selectedPermissionNames = is_array($selectedPermissionNames) ? $selectedPermissionNames : [];
    $permissionTotal = $permissions->sum(fn ($sections) => $sections->sum(fn ($items) => $items->count()));
@endphp

<style>
    .role-matrix { display:grid;gap:.75rem;direction:rtl }
    .role-matrix-toolbar { display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;background:var(--surface);padding:12px;border:1px solid var(--border);border-radius:12px }
    .role-matrix-search { flex:1;min-width:210px;max-width:520px }
    .role-matrix-group {border:1px solid var(--border);border-radius:12px;overflow:hidden;background:var(--card,#fff)}
    .role-matrix-group > summary { padding:14px 16px;cursor:pointer;background:var(--surface);display:flex;align-items:center;justify-content:space-between;gap:10px;font-size:.9rem }
    .role-matrix-group > summary::marker {color:var(--gold-deep)}
    .role-matrix-section {padding:13px 15px;border-top:1px solid var(--border)}
    .role-matrix-section h3 {font-size:.81rem;margin:0 0 10px;color:var(--gold-deep);font-weight:800}
    .role-matrix-options {display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:8px}
    .role-matrix-option {display:flex;align-items:flex-start;gap:9px;padding:9px 10px;border:1px solid var(--border);border-radius:8px;cursor:pointer;color:var(--text-mid);font-size:.82rem;line-height:1.6}
    .role-matrix-option:has(input:checked) {border-color:var(--gold-deep);background:var(--surface)}
    .role-matrix-option input {margin-top:4px;flex:none}
    .role-matrix-code {display:block;direction:ltr;text-align:right;font-family:monospace;word-break:break-all;font-size:.7rem;color:var(--text-muted)}
    .role-matrix-note {display:block;font-size:.72rem;color:var(--text-muted)}
    .role-matrix-hidden {display:none !important}
</style>

<div class="role-matrix" data-role-matrix>
    <div class="role-matrix-toolbar">
        <input type="search" class="form-input role-matrix-search" data-role-search
               aria-label="البحث في الصلاحيات" placeholder="ابحث باسم الإجراء أو كود الصلاحية...">
        <small style="color:var(--text-muted)">
            <span data-role-checked>0</span> محددة من أصل {{ $permissionTotal }} صلاحية
        </small>
        <small style="color:var(--text-muted)">الأقسام مستقلة، والبحث لا يغيّر الاختيارات المحفوظة.</small>
    </div>

    @forelse($permissions as $group => $sections)
        @php
            $allGroupPerms = $sections->flatMap(fn ($section) => $section);
            $selectedInGroup = $allGroupPerms->filter(fn ($item) => in_array($item->name, $selectedPermissionNames, true))->count();
        @endphp
        <details class="role-matrix-group" data-role-group>
            <summary>
                <strong>{{ \App\Support\PermissionUi::groupLabel($group) }}</strong>
                <span style="font-size:.77rem;color:var(--text-muted)">
                    <span data-role-group-checked>{{ $selectedInGroup }}</span>/{{ $allGroupPerms->count() }}
                    · {{ $sections->count() }} مجموعة إجراءات
                </span>
            </summary>

            @foreach($sections as $sectionName => $sectionPermissions)
                <div class="role-matrix-section" data-role-section>
                    <h3>{{ $sectionName }} ({{ $sectionPermissions->count() }})</h3>
                    <div class="role-matrix-options">
                        @foreach($sectionPermissions as $perm)
                            <label class="role-matrix-option" data-role-permission>
                                <input type="checkbox" name="permissions[]" value="{{ $perm->name }}"
                                       @checked(in_array($perm->name, $selectedPermissionNames, true))>
                                <span>
                                    <strong style="font-weight:650">{{ \App\Support\PermissionUi::permissionLabel($perm->name) }}</strong>
                                    @if(\App\Support\PermissionUi::isLegacyPermission($perm->name))
                                        <small class="role-matrix-note">صلاحية قديمة — لا تُلغها دون مراجعة استخداماتها.</small>
                                    @elseif(\App\Support\PermissionUi::isBroadPermission($perm->name))
                                        <small class="role-matrix-note">صلاحية واسعة أو حساسة — راجع نطاق الوصول قبل منحها.</small>
                                    @endif
                                    <small class="role-matrix-code">{{ $perm->name }}</small>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </details>
    @empty
        <div class="empty-state-sm">لا توجد صلاحيات مسجلة في النظام.</div>
    @endforelse

    <p data-role-no-results hidden style="text-align:center;color:var(--text-muted)">
        لا توجد صلاحية تطابق البحث.
    </p>
</div>

<script>
document.querySelectorAll('[data-role-matrix]').forEach(function (matrix) {
    const input = matrix.querySelector('[data-role-search]');
    const groups = Array.from(matrix.querySelectorAll('[data-role-group]'));
    const checkedCount = matrix.querySelector('[data-role-checked]');
    const empty = matrix.querySelector('[data-role-no-results]');

    function updateCounts() {
        const selected = matrix.querySelectorAll('[data-role-permission] input:checked').length;
        checkedCount.textContent = String(selected);
        groups.forEach(group => {
            group.querySelector('[data-role-group-checked]').textContent =
                String(group.querySelectorAll('[data-role-permission] input:checked').length);
        });
    }

    input.addEventListener('input', function () {
        const q = input.value.trim().toLocaleLowerCase();
        let visibleGroups = 0;
        groups.forEach(group => {
            let visible = 0;
            group.querySelectorAll('[data-role-section]').forEach(section => {
                let sectionVisible = 0;
                section.querySelectorAll('[data-role-permission]').forEach(item => {
                    const matches = !q || item.textContent.toLocaleLowerCase().includes(q)
                        || section.querySelector('h3').textContent.toLocaleLowerCase().includes(q)
                        || group.querySelector('summary strong').textContent.toLocaleLowerCase().includes(q);
                    item.classList.toggle('role-matrix-hidden', !matches);
                    if (matches) sectionVisible++;
                });
                section.classList.toggle('role-matrix-hidden', sectionVisible === 0);
                visible += sectionVisible;
            });
            group.classList.toggle('role-matrix-hidden', visible === 0);
            if (q && visible > 0) group.open = true;
            if (visible > 0) visibleGroups++;
        });
        empty.hidden = visibleGroups > 0;
    });
    matrix.addEventListener('change', updateCounts);
    updateCounts();
});
</script>
