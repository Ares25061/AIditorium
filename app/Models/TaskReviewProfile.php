<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskReviewProfile extends Model
{
    protected $fillable = [
        'task_id',
        'enabled',
        'rubric_json',
        'custom_prompt',
        'supported_formats_json',
        'ai_model_key',
        'version',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'rubric_json' => 'array',
        'supported_formats_json' => 'array',
        'version' => 'integer',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
