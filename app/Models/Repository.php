<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

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