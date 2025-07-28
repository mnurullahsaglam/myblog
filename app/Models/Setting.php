<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    public $timestamps = false;

    public static function get(string $group, string $name, $default = null)
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
    public static function set(string $group, string $name, $value, string $type = 'text')
    {
        $setting = static::updateOrCreate(
            ['group' => $group, 'name' => $name],
            ['value' => $value, 'type' => $type]
        );

        // Clear cache
        Cache::forget("setting_{$group}_{$name}");

        return $setting;
    }

    /**
     * Get all settings for a group
     */
    public static function getGroup(string $group)
    {
        return static::where('group', $group)->pluck('value', 'name');
    }

    /**
     * Clear cache when model is saved or deleted
     */
    protected static function booted()
    {
        static::saved(function ($setting) {
            Cache::forget("setting_{$setting->group}_{$setting->name}");
        });

        static::deleted(function ($setting) {
            Cache::forget("setting_{$setting->group}_{$setting->name}");
        });
    }
}
