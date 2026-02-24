<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = [
        'creator_id',
        'name',
        'invite_code',
        'invite_code_teacher',
        'background_logo',
        'description',
        'status',
        'is_closed',
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
}
