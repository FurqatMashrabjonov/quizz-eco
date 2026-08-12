<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Singleton settings row (id 1).
 *
 * @property int $id
 * @property int $duration_minutes
 * @property int $questions_per_attempt
 * @property int $max_attempts
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['duration_minutes', 'questions_per_attempt', 'max_attempts'])]
class QuizSetting extends Model
{
    public static function current(): self
    {
        $settings = self::query()->firstOrCreate(['id' => 1]);

        // firstOrCreate() doesn't hydrate DB-level column defaults onto a
        // newly created model, so a fresh row must be re-fetched to see them.
        return $settings->wasRecentlyCreated ? $settings->fresh() : $settings;
    }
}
