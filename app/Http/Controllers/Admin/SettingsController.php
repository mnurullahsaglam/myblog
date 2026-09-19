<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Settings\SaveSettings;
use App\Contracts\NotifiesAdmin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Models\Setting;
use App\Support\Theme\AccentRamps;
use App\Support\Theme\Appearance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

final class SettingsController extends Controller
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

    public function __construct(private readonly NotifiesAdmin $notifier) {}

    public function edit(): Response
    {
        $settings = [];

        foreach (self::GROUPS as $group) {
            $settings[$group] = Setting::getGroup($group)->all();
        }

        $settings['appearance']['accent'] ??= AccentRamps::DEFAULT;
        $settings['appearance']['color_scheme'] ??= Appearance::DEFAULT_SCHEME;

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

    public function update(SettingsRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $files = [];

        foreach (array_keys(self::UPLOADS) as $key) {
            $file = $request->file($key);

            if ($file instanceof UploadedFile) {
                $files[$key] = $file;
            }
        }

        $saveSettings->handle($request->validated(), $files, self::UPLOADS);

        $this->notifier->success('Settings saved');

        return to_route('admin.settings');
    }
}
