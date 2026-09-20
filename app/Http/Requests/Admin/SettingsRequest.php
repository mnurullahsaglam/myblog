<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Area;
use App\Support\Theme\AccentRamps;
use App\Support\Theme\Appearance;
use Illuminate\Validation\Rule;
use Override;

final class SettingsRequest extends AdminRequest
{
    #[Override]
    protected function area(): Area
    {
        return Area::General;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'appearance.accent' => ['nullable', Rule::in(AccentRamps::names())],
            'appearance.color_scheme' => ['nullable', Rule::in(Appearance::SCHEMES)],

            'site_info.site_name' => ['nullable', 'string', 'max:255'],
            'site_info.site_description' => ['nullable', 'string', 'max:500'],
            'site_info.site_url' => ['nullable', 'url', 'max:255'],
            'site_info.admin_email' => ['nullable', 'email', 'max:255'],

            'meta.meta_title' => ['nullable', 'string', 'max:255'],
            'meta.meta_description' => ['nullable', 'string', 'max:160'],
            'meta.meta_keywords' => ['nullable', 'array'],
            'meta.meta_keywords.*' => ['string', 'max:50'],
            'meta.og_title' => ['nullable', 'string', 'max:255'],
            'meta.og_description' => ['nullable', 'string', 'max:300'],
            'meta.og_image' => ['nullable', 'image', 'max:5120'],

            'branding.logo' => ['nullable', 'image', 'max:5120'],
            'branding.footer_logo' => ['nullable', 'image', 'max:5120'],
            'branding.favicon' => ['nullable', 'image', 'max:2048'],
            'branding.primary_color' => ['nullable', 'string', 'max:32'],
            'branding.secondary_color' => ['nullable', 'string', 'max:32'],

            'social.github_url' => ['nullable', 'url', 'max:255'],
            'social.twitter_url' => ['nullable', 'url', 'max:255'],
            'social.linkedin_url' => ['nullable', 'url', 'max:255'],
            'social.instagram_url' => ['nullable', 'url', 'max:255'],
            'social.facebook_url' => ['nullable', 'url', 'max:255'],
            'social.youtube_url' => ['nullable', 'url', 'max:255'],

            'contact.phone' => ['nullable', 'string', 'max:64'],
            'contact.address' => ['nullable', 'string', 'max:500'],
            'contact.city' => ['nullable', 'string', 'max:255'],
            'contact.postal_code' => ['nullable', 'string', 'max:32'],
            'contact.country' => ['nullable', 'string', 'max:255'],
        ];
    }
}
