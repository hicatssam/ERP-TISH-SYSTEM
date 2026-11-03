<?php

namespace App\Models;

use App\Support\PublicImageUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("setting:{$key}", function () use ($key, $default) {
            $setting = static::query()
                ->where('key', $key)
                ->first();

            return $setting
                ? $setting->typedValue()
                : $default;
        });
    }

    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value === null ? null : (string) $value]
        );

        Cache::forget("setting:{$key}");
    }

    public static function flushCache(): void
    {
        static::query()
            ->pluck('key')
            ->each(function (string $key): void {
                // Collection::each stops on false; a cache miss must not stop
                // invalidation of the remaining settings.
                Cache::forget("setting:{$key}");
            });
    }

    public static function assetUrl(string $key, ?string $fallback = null): ?string
    {
        $value = static::get($key);

        return PublicImageUrl::url(is_string($value) ? $value : null)
            ?? PublicImageUrl::url($fallback);
    }

    /**
     * Use a distinct URL whenever the selected icon bytes change, including
     * replacements at the same stored path. Keep explicit favicons preferred.
     */
    public static function browserIconUrl(): ?string
    {
        foreach (['brand_favicon', 'brand_logo', 'brand_logo_small'] as $key) {
            $path = static::get($key);
            $url = PublicImageUrl::url(is_string($path) ? $path : null);
            if (! $url) {
                continue;
            }
            $local = PublicImageUrl::localPath(is_string($path) ? $path : null);
            if (! $local) {
                return $url;
            }
            $version = substr(hash('sha256', (string) $path.'|'.hash_file('sha256', $local)), 0, 16);
            return $url.(str_contains($url, '?') ? '&' : '?').'brand_v='.$version;
        }
        return null;
    }

    public function typedValue(): mixed
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'decimal' => (float) $this->value,
            default   => $this->value,
        };
    }
}
