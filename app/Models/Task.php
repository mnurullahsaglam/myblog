<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\TaskObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property int $id
 * @property int|null $project_id
 * @property int|null $repository_id
 * @property string $title
 * @property string|null $description
 * @property string $status
 * @property int|null $sort_order
 * @property string|null $github_issue_number
 * @property string|null $github_issue_url
 * @property string|null $github_issue_state
 * @property array<int, array<string, mixed>>|null $github_issue_labels
 * @property string|null $github_assignee
 * @property Carbon|null $github_created_at
 * @property Carbon|null $github_updated_at
 * @property Carbon|null $github_closed_at
 * @property-read bool $is_github_issue
 * @property-read string $github_labels_string
 * @property-read Project|null $project
 * @property-read Repository|null $repository
 */
#[ObservedBy([TaskObserver::class])]
final class Task extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    #[Override]
    protected $fillable = [
        'project_id', 'repository_id', 'title', 'description', 'status', 'sort_order',
        'github_issue_number', 'github_issue_url', 'github_issue_state',
        'github_issue_labels', 'github_assignee', 'github_created_at',
        'github_updated_at', 'github_closed_at',
    ];

    #[Override]
    protected $casts = [
        'github_issue_labels' => 'array',
        'github_created_at' => 'datetime',
        'github_updated_at' => 'datetime',
        'github_closed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Repository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    protected function getIsGithubIssueAttribute(): bool
    {
        return ! is_null($this->github_issue_number);
    }

    protected function getGithubLabelsStringAttribute(): string
    {
        if (! $this->github_issue_labels) {
            return '';
        }

        return collect($this->github_issue_labels)
            ->pluck('name')
            ->map(fn (mixed $name): string => is_scalar($name) ? (string) $name : '')
            ->join(', ');
    }
}
