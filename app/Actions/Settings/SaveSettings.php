<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;

/**
 * Persist a screen's worth of grouped settings.
 *
 * Files arrive already separated from the plain values so the action never
 * touches a request; an upload field with no file keeps whatever is stored.
 */
final class SaveSettings
{
    /**
     * @param  array<string, string>  $uploadFields  dotted "group.name" mapped to its storage directory
     * @param  array<string, mixed>  $groups
     * @param  array<string, UploadedFile>  $files  keyed by dotted "group.name"
     */
    public function handle(array $groups, array $files, array $uploadFields): void
    {
        foreach ($groups as $group => $values) {
            if (! is_array($values)) {
                continue;
            }

            foreach ($values as $name => $value) {
                $this->persist((string) $group, (string) $name, $value, $files, $uploadFields);
            }
        }
    }

    /**
     * @param  array<string, UploadedFile>  $files
     * @param  array<string, string>  $uploadFields
     */
    private function persist(string $group, string $name, mixed $value, array $files, array $uploadFields): void
    {
        $key = $group.'.'.$name;

        if (array_key_exists($key, $uploadFields)) {
            $file = $files[$key] ?? null;

            if (! $file instanceof UploadedFile) {
                return;
            }

            $stored = $file->store($uploadFields[$key], 'public');

            Setting::set($group, $name, is_string($stored) ? $stored : null, 'file');

            return;
        }

        if (is_array($value)) {
            Setting::set($group, $name, json_encode($value), 'json');

            return;
        }

        Setting::set($group, $name, is_scalar($value) ? (string) $value : null);
    }
}
