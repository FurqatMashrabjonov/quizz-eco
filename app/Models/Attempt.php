<?php

namespace App\Models;

use Database\Factories\AttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property array<int, array{q: int, o: array<int, int>}> $layout
 * @property Carbon $started_at
 * @property Carbon $expires_at
 * @property Carbon|null $finished_at
 * @property int|null $score
 * @property int|null $total
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'layout', 'started_at', 'expires_at', 'finished_at', 'score', 'total'])]
class Attempt extends Model
{
    /** @use HasFactory<AttemptFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'layout' => 'array',
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Answer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * @return list<int>
     */
    public function questionIds(): array
    {
        return array_column($this->layout, 'q');
    }
}
