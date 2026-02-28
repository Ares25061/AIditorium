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
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        $locale = request()->get('lang', request()->header('Accept-Language', config('app.locale')));
        if (in_array($locale, ['ru', 'en'])) {
            App::setLocale($locale);
        }
        Scramble::configure()
            ->withOperationTransformers(function (Operation $operation, $routeInfo) {
                $operation->parameters[] = Parameter::make('Accept-Language', 'header')
                    ->setSchema(Schema::fromType((new StringType)->enum(['ru', 'en'])->default('ru')))
                    ->description('Язык ответа (ru или en)');
                $controllerFullClass = $routeInfo->route->getControllerClass();
                $methodName = $routeInfo->route->getActionMethod();
                if (!$controllerFullClass || !is_string($controllerFullClass)) {
                    return;
                }
                $controllerName = class_basename($controllerFullClass);
                $resourceKey = Str::lower(Str::replace('Controller', '', $controllerName));
                $summaryKey = "docs.{$resourceKey}.{$methodName}.summary";
                $descriptionKey = "docs.{$resourceKey}.{$methodName}.description";
                $tagKey = "docs.tags.{$resourceKey}";
                if (Lang::has($summaryKey)) {
                    $operation->summary(trans($summaryKey));
                }
                if (Lang::has($descriptionKey)) {
                    $operation->description(trans($descriptionKey));
                }
                if (Lang::has($tagKey)) {
                    $operation->setTags([trans($tagKey)]);
                }
            })
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->info->title = trans('docs.title');
                $openApi->info->description = trans('docs.description');
                $openApi->secure(SecurityScheme::http('bearer'));
            });
    }

    protected $policies = [
        User::class => UserPolicy::class,
        File::class  => FilePolicy::class,
        Course::class => CoursePolicy::class,
        Task::class => TaskPolicy::class,
    ];
}
