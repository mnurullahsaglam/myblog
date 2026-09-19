<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Models\Setting;
use App\Support\AdminNotifier;
use App\Support\Theme\AccentRamps;
use App\Support\Theme\Appearance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const array GROUPS = ['appearance', 'site_info', 'meta', 'branding', 'social', 'contact'];

    /**
     * Fields stored as an uploaded file rather than a plain value.
     *
     * @var array<string, string>
     */
    private const array UPLOADS = [
        'meta.og_image' => 'settings/meta',
        'branding.logo' => 'settings/branding',
        'branding.footer_logo' => 'settings/branding',
        'branding.favicon' => 'settings/branding',
    ];

    public function __construct(private readonly AdminNotifier $notifier) {}

    public function edit(): Response
    {
        $settings = [];

        foreach (self::GROUPS as $group) {
            $settings[$group] = Setting::getGroup($group)->all();
        }

        $settings['appearance']['accent'] ??= AccentRamps::DEFAULT;
        $settings['appearance']['color_scheme'] ??= Appearance::DEFAULT_SCHEME;

        // Stored as JSON, but the tag input wants an array.
        $keywords = $settings['meta']['meta_keywords'] ?? null;
        $settings['meta']['meta_keywords'] = is_string($keywords)
            ? (json_decode($keywords, true) ?: [])
            : ($keywords ?? []);

        return Inertia::render('Settings', [
            'settings' => $settings,
            'accents' => array_map(
                fn (string $name): array => ['name' => $name, 'swatch' => AccentRamps::swatch($name)],
                AccentRamps::names(),
            ),
            'schemes' => [
                ['value' => 'light', 'label' => 'Light'],
                ['value' => 'dark', 'label' => 'Dark'],
                ['value' => 'system', 'label' => 'System'],
            ],
        ]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        foreach ($request->validated() as $group => $values) {
            if (! is_array($values)) {
                continue;
            }

            foreach ($values as $name => $value) {
                $this->persist((string) $group, (string) $name, $value, $request);
            }
        }

        $this->notifier->success('Settings saved');

        return to_route('admin.settings');
    }

    private function persist(string $group, string $name, mixed $value, SettingsRequest $request): void
    {
        $key = $group.'.'.$name;

        if (array_key_exists($key, self::UPLOADS)) {
            $file = $request->file($key);

            // No new file means keep whatever is already stored.
            if (! $file instanceof UploadedFile) {
                return;
            }

            Setting::set($group, $name, $file->store(self::UPLOADS[$key], 'public'), 'file');

            return;
        }

        if (is_array($value)) {
            Setting::set($group, $name, json_encode($value), 'json');

            return;
        }

        Setting::set($group, $name, is_scalar($value) ? (string) $value : null);
    }
}
