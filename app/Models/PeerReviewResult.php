<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeerReviewResult extends Model
{
    protected $fillable = [
        'assignment_id',
        'task_id',
        'reviewer_id',
        'target_user_id',
        'file_id',
        'grade',
        'comment',
    ];

    protected $casts = [
        'grade' => 'float',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(PeerReviewAssignment::class, 'assignment_id');
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
}
