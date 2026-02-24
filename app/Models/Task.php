<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'name',
        'description',
        'scores',
        'deadline',
        'attachment_id',
    ];
}
