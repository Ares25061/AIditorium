<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class File extends Model
{
   protected $table = 'files';

   protected $fillable = [
       'path',
       'user_id',
       'course_id',
       'task_id',
       'type'
   ];

   protected $casts = [
       'type' => "string",
   ];

   public function user(): BelongsTo
   {
       return $this->belongsTo(User::class);
   }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
    // Пользователь, который загрузил файл (владелец)
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Пользователь, у которого этот файл как аватар
    public function userAvatar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id', 'avatar');
    }
}
