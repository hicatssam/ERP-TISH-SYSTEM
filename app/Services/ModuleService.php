<?php

namespace App\Services;

use App\Enums\ModuleType;
use App\Models\Module;
use App\Models\ModuleBundle;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ModuleService
{
    private const CACHE_KEY = 'modules:registry:v1';

    public function get(string $moduleCode): ?Module
    {
        return $this->all()->get($moduleCode);
    }

    public function all(): Collection
    {
        if (! Schema::hasTable('modules')) {
            return collect();
        }

        return Cache::rememberForever(
            self::CACHE_KEY,
            fn () => Module::query()
                ->with(['dependencies', 'dependents'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->keyBy('code')
        );
    }

    public function enabled(): Collection
    {
        return $this->all()->filter(fn (Module $module) => $this->isEnabled($module->code))->values();
    }

    public function core(): Collection
    {
        return $this->all()
            ->filter(fn (Module $module) => $module->type === ModuleType::CORE)
            ->values();
    }

    public function industry(): Collection
    {
        return $this->all()
            ->filter(fn (Module $module) => $module->type === ModuleType::INDUSTRY)
            ->values();
    }

    public function optional(): Collection
    {
        return $this->all()
            ->filter(fn (Module $module) => $module->type === ModuleType::OPTIONAL)
            ->values();
    }

    public function isEnabled(string $moduleCode): bool
    {
        /*
         * Fail-open only before Sprint tables are installed.
         * This preserves the existing Dahab installation while migration/seeding
         * is not yet complete. Chat keeps its old toggle during that window.
         */
        if (! Schema::hasTable('modules')) {
            if ($moduleCode === 'chat') {
                return (bool) SystemSetting::get('chat_enabled', true);
            }

            return true;
        }

        if (\App\Support\ActivityModules::scoped()) {
            $allowed = \App\Support\ActivityModules::allowedCodes();
            $visited = [];
            $check = function (string $code) use (&$check, &$visited, $allowed): bool {
                if (isset($visited[$code])) {
                    return true;
                }
                $visited[$code] = true;
                $module = $this->get($code);
                if (! in_array($code, $allowed, true) || ! $module?->is_active || ! $module->isImplemented()) {
                    return false;
                }
                foreach ($module->dependencies as $dependency) {
                    if (! $check($dependency->code)) {
                        return false;
                    }
                }
                return true;
            };
            return $check($moduleCode);
        }

        return (bool) $this->get($moduleCode)?->is_active;
    }

    public function isCore(string $moduleCode): bool
    {
        return (bool) $this->get($moduleCode)?->is_core;
    }

    public function require(string $moduleCode): void
    {
        abort_unless(
            $this->isEnabled($moduleCode),
            403,
            'هذه الوحدة غير مفعلة في النظام.'
        );
    }

    public function setEnabled(
        string|Module $module,
        bool $enabled,
        ?User $actor = null
    ): Module {
        $module = $module instanceof Module
            ? $module
            : Module::query()->where('code', $module)->firstOrFail();

        if ($enabled && \App\Support\ActivityModules::scoped()
            && ! in_array($module->code, \App\Support\ActivityModules::allowedCodes(), true)) {
            throw ValidationException::withMessages(['module' => 'هذه الوحدة لا تتبع النشاط الحالي.']);
        }

        if ($module->is_active === $enabled) {
            return $module;
        }

        if (! $enabled && $module->is_system) {
            throw ValidationException::withMessages([
                'module' => 'لا يمكن تعطيل وحدة نظام حرجة: ' . $module->name,
            ]);
        }

        if ($enabled && ! $module->isImplemented()) {
            throw ValidationException::withMessages([
                'module' => 'الوحدة «' . $module->name . '» مسجلة للمستقبل ولم يتم تنفيذ وظائفها بعد.',
            ]);
        }

        if ($enabled) {
            $disabledDependencies = $module->dependencies()
                ->where('is_active', false)
                ->pluck('name')
                ->all();

            if ($disabledDependencies !== []) {
                throw ValidationException::withMessages([
                    'module' => 'فعّل المتطلبات أولًا: ' . implode('، ', $disabledDependencies),
                ]);
            }
        } else {
            $activeDependents = $module->dependents()
                ->where('is_active', true)
                ->pluck('name')
                ->all();

            if ($activeDependents !== []) {
                throw ValidationException::withMessages([
                    'module' => 'لا يمكن تعطيل الوحدة لأنها مطلوبة بواسطة: ' . implode('، ', $activeDependents),
                ]);
            }
        }

        $before = $module->is_active;
        $module->update(['is_active' => $enabled]);

        if ($module->code === 'chat') {
            $this->mirrorLegacyChatSetting($enabled);
        }

        $this->invalidate();

        if ($actor) {
            ActivityLogger::log(
                $actor->id,
                $enabled ? 'module.enabled' : 'module.disabled',
                'modules',
                Module::class,
                $module->id,
                ['is_active' => $before],
                ['is_active' => $enabled],
                ['module_code' => $module->code]
            );
        }

        return $module->fresh();
    }

    public function enableWithDependencies(Module $module, ?User $actor = null): array
    {
        $result = [
            'enabled' => [],
            'skipped' => [],
        ];

        $visited = [];
        $this->enableRecursively($module, $actor, $visited, $result);

        return $result;
    }

    public function applyBundle(ModuleBundle $bundle, ?User $actor = null): array
    {
        $result = ['enabled' => [], 'skipped' => []];

        foreach ($bundle->modules()->orderByPivot('sort_order')->get() as $module) {
            if (! $module->isImplemented()) {
                $result['skipped'][] = $module->code;
                continue;
            }

            $sub = $this->enableWithDependencies($module, $actor);
            $result['enabled'] = array_values(array_unique(array_merge($result['enabled'], $sub['enabled'])));
            $result['skipped'] = array_values(array_unique(array_merge($result['skipped'], $sub['skipped'])));
        }

        return $result;
    }

    public function availableForProfile(string $profile): Collection
    {
        $allowed = \App\Support\ActivityModules::allowedCodes($profile);
        return $this->all()->filter(fn (Module $module) =>
            in_array($module->code, $allowed, true) && $module->isImplemented()
        )->values();
    }

    public function selection(string $profile, array $selected): array
    {
        $available = $this->availableForProfile($profile)->keyBy('code');
        $targets = [];
        $visit = function (string $code) use (&$visit, &$targets, $available): void {
            if (isset($targets[$code])) {
                return;
            }
            $module = $available->get($code);
            if (! $module) {
                throw ValidationException::withMessages([
                    'selected_codes' => 'الوحدة أو أحد متطلباتها لا يتبع النشاط المحدد: '.$code,
                ]);
            }
            $targets[$code] = true;
            foreach ($module->dependencies as $dependency) {
                $visit($dependency->code);
            }
        };
        foreach (array_unique(array_merge(
            \App\Support\ActivityModules::requiredCodes($profile), $selected
        )) as $code) {
            $visit($code);
        }
        return array_keys($targets);
    }

    public function applySelection(string $profile, array $selected, User $actor): array
    {
        $targets = $this->selection($profile, $selected);
        $result = ['enabled' => [], 'disabled' => [], 'skipped' => [], 'blocked' => []];
        foreach ($targets as $code) {
            $sub = $this->enableWithDependencies($this->get($code), $actor);
            $result['enabled'] = array_values(array_unique(array_merge($result['enabled'], $sub['enabled'])));
        }
        // Disable dependents before dependencies, independent of display order.
        $pending = Module::query()->where('is_active', true)
            ->whereNotIn('code', $targets)->get()->keyBy('code');
        while ($pending->isNotEmpty()) {
            $progress = false;
            foreach ($pending as $code => $module) {
                if ($module->dependents()->where('is_active', true)->exists()) {
                    continue;
                }
                $this->setEnabled($module, false, $actor);
                $result['disabled'][] = $code;
                $pending->forget($code);
                $progress = true;
            }
            if (! $progress) {
                throw ValidationException::withMessages([
                    'selected_codes' => 'تعذر إيقاف الوحدات غير المختارة بسبب اعتمادياتها. لم يتم تطبيق الإعداد.',
                ]);
            }
        }
        $this->invalidate();
        return $result;
    }

    public function invalidate(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function enableRecursively(
        Module $module,
        ?User $actor,
        array &$visited,
        array &$result
    ): void {
        if (isset($visited[$module->code])) {
            return;
        }

        $visited[$module->code] = true;

        if (\App\Support\ActivityModules::scoped()
            && ! in_array($module->code, \App\Support\ActivityModules::allowedCodes(), true)) {
            throw ValidationException::withMessages(['module' => 'هذه الوحدة لا تتبع النشاط الحالي.']);
        }

        if (! $module->isImplemented()) {
            $result['skipped'][] = $module->code;
            return;
        }

        foreach ($module->dependencies as $dependency) {
            $this->enableRecursively($dependency, $actor, $visited, $result);
        }

        $fresh = Module::query()->findOrFail($module->id);

        if (! $fresh->is_active) {
            $before = false;
            $fresh->update(['is_active' => true]);

            if ($fresh->code === 'chat') {
                $this->mirrorLegacyChatSetting(true);
            }

            if ($actor) {
                ActivityLogger::log(
                    $actor->id,
                    'module.enabled',
                    'modules',
                    Module::class,
                    $fresh->id,
                    ['is_active' => $before],
                    ['is_active' => true],
                    ['module_code' => $fresh->code, 'source' => 'dependency_or_bundle']
                );
            }

            $result['enabled'][] = $fresh->code;
        }

        $this->invalidate();
    }

    private function mirrorLegacyChatSetting(bool $enabled): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'chat_enabled'],
            [
                'value' => $enabled ? '1' : '0',
                'type' => 'boolean',
                'group' => 'modules',
                'label' => 'تفعيل المحادثات الداخلية',
                'description' => 'قيمة توافق قديمة؛ المصدر الرسمي الآن هو جدول modules.',
            ]
        );

        SystemSetting::flushCache();
    }
}
