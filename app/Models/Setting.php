<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Override;

/**
 * @property string $group
 * @property string $name
 * @property string|null $value
 * @property string $type
 */
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    #[Override]
    public $timestamps = false;

    public static function get(string $group, string $name, mixed $default = null): mixed
    {
        $cacheKey = "setting_{$group}_{$name}";

        return Cache::remember($cacheKey, 3600, function () use ($group, $name, $default) {
            $setting = static::where('group', $group)->where('name', $name)->first();

            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Set a setting value
     */
    public static function set(string $group, string $name, mixed $value, string $type = 'text'): self
    {
        $setting = static::updateOrCreate(
            ['group' => $group, 'name' => $name],
            ['value' => $value, 'type' => $type]
        );

        Cache::forget("setting_{$group}_{$name}");

        return $setting;
    }

    /**
     * Get all settings for a group
     */
    /**
     * @return Collection<array-key, mixed>
     */
    public static function getGroup(string $group): Collection
    {
        return static::where('group', $group)->pluck('value', 'name');
    }

    /**
     * Clear cache when model is saved or deleted
     */
    protected static function booted(): void
    {
        static::saved(function (Setting $setting): void {
            Cache::forget("setting_{$setting->group}_{$setting->name}");
        });

        static::deleted(function (Setting $setting): void {
            Cache::forget("setting_{$setting->group}_{$setting->name}");
        });
    }
}
