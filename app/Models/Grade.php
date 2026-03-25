<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grade extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'task_id',
        'discipline_id',
        'file_id',
        'type',
        'grade',
        'graded_by',
        'graded_at',
    ];

    protected $casts = [
        'type' => 'string',
        'grade' => 'integer',
        'graded_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    // app/Models/User.php

// Add these methods to your User model:

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class, 'user_id');
    }

    public function gradedGrades(): HasMany
    {
        return $this->hasMany(Grade::class, 'graded_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

}
