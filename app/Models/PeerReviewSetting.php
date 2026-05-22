<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeerReviewSetting extends Model
{
    protected $fillable = [
        'task_id',
        'mode',
        'reviews_per_student',
        'allow_score',
        'instructions',
    ];

    protected $casts = [
        'reviews_per_student' => 'integer',
        'allow_score' => 'boolean',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
