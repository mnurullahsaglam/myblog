<?php

declare(strict_types=1);

use App\Actions\Settings\SaveSettings;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

const UPLOAD_FIELDS = ['branding.logo' => 'settings/branding'];

it('stores a scalar value as a string', function (): void {
    resolve(SaveSettings::class)->handle(['site_info' => ['title' => 'My Blog']], [], []);

    expect(Setting::getGroup('site_info')->get('title'))->toBe('My Blog');
});

it('stores an array value as JSON', function (): void {
    resolve(SaveSettings::class)->handle(['meta' => ['meta_keywords' => ['rust', 'laravel']]], [], []);

    expect(Setting::where('group', 'meta')->where('name', 'meta_keywords')->sole()->type)->toBe('json');
});

it('round-trips an array value', function (): void {
    resolve(SaveSettings::class)->handle(['meta' => ['meta_keywords' => ['rust', 'laravel']]], [], []);

    $stored = Setting::getGroup('meta')->get('meta_keywords');

    expect(json_decode((string) $stored, true))->toBe(['rust', 'laravel']);
});

it('stores an uploaded file and records its type', function (): void {
    Storage::fake('public');

    resolve(SaveSettings::class)->handle(
        ['branding' => ['logo' => null]],
        ['branding.logo' => UploadedFile::fake()->image('logo.png')],
        UPLOAD_FIELDS,
    );

    $setting = Setting::where('group', 'branding')->where('name', 'logo')->sole();

    expect($setting->type)->toBe('file')
        ->and($setting->value)->toStartWith('settings/branding/');
});

it('keeps the stored path when an upload field arrives without a file', function (): void {
    Setting::set('branding', 'logo', 'settings/branding/existing.png', 'file');

    resolve(SaveSettings::class)->handle(['branding' => ['logo' => null]], [], UPLOAD_FIELDS);

    expect(Setting::getGroup('branding')->get('logo'))->toBe('settings/branding/existing.png');
});

it('skips a group that is not an array', function (): void {
    resolve(SaveSettings::class)->handle(['appearance' => 'not-an-array'], [], []);

    expect(Setting::where('group', 'appearance')->count())->toBe(0);
});

it('overwrites a previously stored value', function (): void {
    Setting::set('site_info', 'title', 'Old');

    resolve(SaveSettings::class)->handle(['site_info' => ['title' => 'New']], [], []);

    expect(Setting::getGroup('site_info')->get('title'))->toBe('New')
        ->and(Setting::where('group', 'site_info')->where('name', 'title')->count())->toBe(1);
});

it('stores a null for a value that is neither scalar nor array', function (): void {
    resolve(SaveSettings::class)->handle(['site_info' => ['title' => null]], [], []);

    expect(Setting::getGroup('site_info')->get('title'))->toBeNull();
});

it('writes every group it is given', function (): void {
    resolve(SaveSettings::class)->handle([
        'site_info' => ['title' => 'A'],
        'contact' => ['email' => 'a@example.com'],
    ], [], []);

    expect(Setting::getGroup('site_info')->get('title'))->toBe('A')
        ->and(Setting::getGroup('contact')->get('email'))->toBe('a@example.com');
});
