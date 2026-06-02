<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $project_id
 * @property string $name
 * @property string $full_name
 * @property string $owner
 * @property string|null $description
 * @property string $visibility
 * @property string $github_url
 * @property string $github_id
 * @property string $default_branch
 * @property string|null $language
 * @property int $stars_count
 * @property int $forks_count
 * @property int $issues_count
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $github_created_at
 * @property \Illuminate\Support\Carbon|null $github_updated_at
 * @property \Illuminate\Support\Carbon|null $last_synced_at
 * @property-read bool $is_public
 * @property-read bool $is_private
 * @property-read Project|null $project
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Task> $tasks
 */
class Repository extends Model
{
    protected $fillable = [
        'project_id',
        'name',
        'full_name',
        'owner',
        'description',
        'visibility',
        'github_url',
        'github_id',
        'default_branch',
        'language',
        'stars_count',
        'forks_count',
        'issues_count',
        'is_active',
        'github_created_at',
        'github_updated_at',
        'last_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'github_created_at' => 'datetime',
        'github_updated_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function getIsPublicAttribute(): bool
    {
        return $this->visibility === 'public';
    }

    public function getIsPrivateAttribute(): bool
    {
        return $this->visibility === 'private';
    }
}
