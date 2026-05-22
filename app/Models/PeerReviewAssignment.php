<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PeerReviewAssignment extends Model
{
    protected $fillable = [
        'assignment_key',
        'course_id',
        'discipline_id',
        'task_id',
        'reviewer_id',
        'target_user_id',
        'file_id',
        'blind',
        'allow_score',
        'max_score',
        'instructions',
    ];

    protected $casts = [
        'blind' => 'boolean',
        'allow_score' => 'boolean',
        'max_score' => 'integer',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function result(): HasOne
    {
        return $this->hasOne(PeerReviewResult::class, 'assignment_id');
    }
}
