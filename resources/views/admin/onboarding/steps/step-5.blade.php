@php
    $plan = $run->module_plan ?? [];
    $selected = collect(old('selected_codes', $plan['selected_codes'] ?? []));
@endphp

<form method="POST" action="{{ route('onboarding.step.save', [$run, 5]) }}">
    @csrf
    @method('PATCH')

    <div class="onb-card">
        <div class="onb-card-head">
            <strong>5. خطة الوحدات</strong>
        </div>

        <div class="onb-card-body">
            <div class="onb-warning" style="margin-bottom:1rem">
                اختر وحدات نشاطك فقط. وحدات إدارة النظام الضرورية مقفلة، وتُفعّل متطلبات اختياراتك تلقائيًا عند الحفظ. تُوقف جميع الوحدات غير المختارة عند التطبيق دون حذف بياناتها.
            </div>

            <div class="onb-module-list">
                @foreach($allModules as $module)
                    @php
                        $isCore = in_array($module->code, \App\Support\ActivityModules::requiredCodes($run->business_profile_code ?? 'bakery_sweets'), true);
                        $implemented = $module->isImplemented();
                        $recommended = in_array($module->code, $recommendedCodes, true);
                    @endphp

                    <label class="onb-module" style="{{ !$implemented ? 'opacity:.55' : '' }}">
                        <div class="onb-module-head">
                            <input
                                type="checkbox"
                                name="selected_codes[]"
                                value="{{ $module->code }}"
                                @checked($isCore || $selected->contains($module->code))
                                @disabled($isCore || !$implemented)
                            >

                            <div>
                                <strong>{{ $module->name }}</strong>
                                <small class="onb-help">{{ $module->description }}</small>
                                <small class="onb-help">المتطلبات: {{ $module->dependencies->pluck('name')->implode('، ') ?: 'لا يوجد' }}</small>

                                @if($isCore)
                                    <span class="onb-chip">أساسية</span>
                                @elseif($recommended)
                                    <span class="onb-chip">موصى بها</span>
                                @elseif(!$implemented)
                                    <span class="onb-chip">غير منفذة بعد</span>
                                @endif
                            </div>
                        </div>
                    </label>
                @endforeach
            </div>

            <p class="onb-help">المخزون والوصفات والإنتاج وحدات تشغيل قابلة للاختيار. اختيار الإنتاج يتطلب الوصفات والمخزون؛ واختيار الوصفات يتطلب المخزون.</p>

            <div class="onb-actions">
                <a class="btn btn-ghost" href="{{ route('onboarding.index', ['step' => 4]) }}">السابق</a>
                <button type="submit" class="btn btn-gold">حفظ ومراجعة</button>
            </div>
        </div>
    </div>
</form>
