<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\Task;
use App\Models\User;
use App\Models\File;
use App\Policies\CoursePolicy;
use App\Policies\TaskPolicy;
use App\Policies\UserPolicy;
use App\Policies\FilePolicy;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
    protected $policies = [
        User::class => UserPolicy::class,
        File::class  => FilePolicy::class,
        Course::class => CoursePolicy::class,
        Task::class => TaskPolicy::class,
    ];
}
