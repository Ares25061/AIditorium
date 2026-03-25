<?php

namespace App\Models;

use App\Enums\CoursePermissions;
use App\Enums\DisciplinePermissions;
use App\Enums\FilePermissions;
use App\Enums\RolePermissions;
use App\Enums\TaskPermissions;
use App\Enums\UserPermissions;
use App\Enums\CommentPermissions;
use App\Enums\GradePermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'email_verified_at',
        'avatar',
    ];
    protected $appends = [
        'roleName',
        'avatar_url',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'avatar' => 'integer',
        ];
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
    public function courses()
    {
        return $this->BelongsToMany(Course::class)
            ->withPivot('role')
            ->withTimestamps();
    }
    public function disciplines()
    {
        return $this->hasMany(Discipline::class, 'created_by');
    }
    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function avatarFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'avatar');
    }

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


    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->avatar && $this->avatarFile) {
            return asset('storage/' . $this->avatarFile->path);
        }
        return null;
    }


    public function hasPermission(UserPermissions|RolePermissions|FilePermissions|CoursePermissions|TaskPermissions|DisciplinePermissions|CommentPermissions| GradePermissions $permission): bool
    {
        return $this->role->permissions->contains('name', $permission->value);
    }
    public function getRoleNameAttribute():string
    {
        return $this->role->name;
    }
}
