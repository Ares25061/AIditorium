<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discipline extends Model
{
    protected $fillable = [
        'course_id',
        'name',
        'hours',
        'discipline_number',
        'discipline_slug',
        'created_by',
        'description',
    ];
    protected static function booted()
    {
        static::creating(function ($discipline) {
            $maxDisciplineNumber = Discipline::where('discipline_id', $discipline->course_id)
                ->max('discipline_number');
            $discipline->discipline_number = ($maxDisciplineNumber ?? 0) + 1;
        });
    }
}
