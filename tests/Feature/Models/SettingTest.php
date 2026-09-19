<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

it('returns the default when the setting is missing', function (): void {
    expect(Setting::get('appearance', 'accent', 'khaki'))->toBe('khaki');
});

it('returns null when missing and no default is given', function (): void {
    expect(Setting::get('appearance', 'accent'))->toBeNull();
});

it('reads a stored value', function (): void {
    Setting::create(['group' => 'appearance', 'name' => 'accent', 'value' => 'amber', 'type' => 'text']);

    expect(Setting::get('appearance', 'accent', 'khaki'))->toBe('amber');
});

it('caches the read', function (): void {
    Setting::create(['group' => 'appearance', 'name' => 'accent', 'value' => 'amber', 'type' => 'text']);

    Setting::get('appearance', 'accent');

    expect(Cache::has('setting_appearance_accent'))->toBeTrue();
});

it('busts the cache when the value is set', function (): void {
    Setting::set('appearance', 'accent', 'amber');
    expect(Setting::get('appearance', 'accent'))->toBe('amber');

    Setting::set('appearance', 'accent', 'emerald');

    expect(Setting::get('appearance', 'accent'))->toBe('emerald');
});

it('busts the cache when the model is saved directly', function (): void {
    $setting = Setting::create(['group' => 'appearance', 'name' => 'accent', 'value' => 'amber', 'type' => 'text']);
    Setting::get('appearance', 'accent');

    $setting->update(['value' => 'rose']);

    expect(Setting::get('appearance', 'accent'))->toBe('rose');
});

it('busts the cache when the model is deleted', function (): void {
    $setting = Setting::create(['group' => 'appearance', 'name' => 'accent', 'value' => 'amber', 'type' => 'text']);
    Setting::get('appearance', 'accent');

    $setting->delete();

    expect(Setting::get('appearance', 'accent', 'khaki'))->toBe('khaki');
});

it('upserts on set rather than duplicating', function (): void {
    Setting::set('appearance', 'accent', 'amber');
    Setting::set('appearance', 'accent', 'khaki');

    expect(Setting::where('group', 'appearance')->where('name', 'accent')->count())->toBe(1);
});

it('keeps groups isolated from each other', function (): void {
    Setting::set('appearance', 'accent', 'khaki');
    Setting::set('branding', 'accent', 'logo.png');

    expect(Setting::get('appearance', 'accent'))->toBe('khaki')
        ->and(Setting::get('branding', 'accent'))->toBe('logo.png');
});

it('returns a group as a name-keyed collection', function (): void {
    Setting::set('appearance', 'accent', 'khaki');
    Setting::set('appearance', 'color_scheme', 'dark');

    expect(Setting::getGroup('appearance')->toArray())
        ->toBe(['accent' => 'khaki', 'color_scheme' => 'dark']);
});
