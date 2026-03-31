<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
