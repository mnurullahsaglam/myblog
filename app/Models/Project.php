<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property int $id
 * @property int|null $client_id
 * @property string $name
 * @property Carbon|null $due_date
 * @property-read Client|null $client
 * @property-read Collection<int, Task> $tasks
 * @property-read Collection<int, Repository> $repositories
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    #[Override]
    protected $fillable = [
        'client_id',
        'name',
        'due_date',
    ];

    #[Override]
    protected $casts = [
        'due_date' => 'date',
    ];

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return HasMany<Repository, $this>
     */
    public function repositories(): HasMany
    {
        return $this->hasMany(Repository::class);
    }
}
