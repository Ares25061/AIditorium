<?php

namespace App\Models;

use App\Enums\ReviewRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiReviewRun extends Model
{
    protected $fillable = [
        'course_id',
        'discipline_id',
        'task_id',
        'file_id',
        'student_id',
        'requested_by',
        'status',
        'provider',
        'model',
        'criteria_snapshot_json',
        'extracted_artifacts_json',
        'result_json',
        'summary',
        'recommended_score',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'status' => ReviewRunStatus::class,
        'criteria_snapshot_json' => 'array',
        'extracted_artifacts_json' => 'array',
        'result_json' => 'array',
        'recommended_score' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
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

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
