<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $fillable = [
        'creator_id',
        'name',
        'invite_code',
        'invite_code_teacher',
        'background_logo_id',
        'description',
        'status',
        'is_closed',
        'slug',
    ];

    protected $casts = [
        'status' => "string",
    ];
    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }
    public function disciplines()
    {
        return $this->hasMany(Discipline::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    public function backgroundLogo(): BelongsTo
    {
        return $this->belongsTo(File::class, 'background_logo_id');
    }

    public function aiReviewRuns(): HasMany
    {
        return $this->hasMany(AiReviewRun::class);
    }
}
