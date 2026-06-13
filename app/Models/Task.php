<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Task extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'discipline_id',
        'name',
        'description',
        'scores',
        'deadline',
        'attachment_id',
        'task_number',
    ];
    protected static function booted()
    {
        static::creating(function ($task) {
            $maxTaskNumber = Task::where('discipline_id', $task->discipline_id)
                ->max('task_number');
            $task->task_number = ($maxTaskNumber ?? 0) + 1;
        });
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(File::class)->where('type', 'submission');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(File::class, 'attachment_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(File::class)->where('type', 'task')->orderBy('id');
    }

    public function reviewProfile(): HasOne
    {
        return $this->hasOne(TaskReviewProfile::class);
    }

    public function aiReviewRuns(): HasMany
    {
        return $this->hasMany(AiReviewRun::class);
    }

    public function reviewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_reviewers')
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    public function peerReviewSetting(): HasOne
    {
        return $this->hasOne(PeerReviewSetting::class);
    }

    public function peerReviewAssignments(): HasMany
    {
        return $this->hasMany(PeerReviewAssignment::class);
    }

    public function peerReviewResults(): HasMany
    {
        return $this->hasMany(PeerReviewResult::class);
    }
}
