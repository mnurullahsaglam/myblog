<?php

namespace App\Models;

use App\Observers\TaskObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([TaskObserver::class])]
class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'repository_id', 'title', 'description', 'status', 'sort_order',
        'github_issue_number', 'github_issue_url', 'github_issue_state',
        'github_issue_labels', 'github_assignee', 'github_created_at',
        'github_updated_at', 'github_closed_at',
    ];

    protected $casts = [
        'github_issue_labels' => 'array',
        'github_created_at' => 'datetime',
        'github_updated_at' => 'datetime',
        'github_closed_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function getIsGithubIssueAttribute(): bool
    {
        return !is_null($this->github_issue_number);
    }

    public function getGithubLabelsStringAttribute(): string
    {
        if (!$this->github_issue_labels) {
            return '';
        }

        return collect($this->github_issue_labels)
            ->pluck('name')
            ->join(', ');
    }
}
