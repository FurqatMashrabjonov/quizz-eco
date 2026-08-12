<?php

use App\Models\QuizSetting;

test('a new installation defaults to 60 questions per attempt and 120 minutes', function () {
    expect(QuizSetting::current())
        ->questions_per_attempt->toBe(60)
        ->duration_minutes->toBe(120);
});

test('an admin can override the default questions per attempt and duration', function () {
    QuizSetting::current()->update(['questions_per_attempt' => 20, 'duration_minutes' => 45]);

    expect(QuizSetting::current())
        ->questions_per_attempt->toBe(20)
        ->duration_minutes->toBe(45);
});
