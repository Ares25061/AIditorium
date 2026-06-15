<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiDocsCoverageTest extends TestCase
{
    public function test_all_project_api_routes_have_docs_translations(): void
    {
        $missing = [];

        foreach (['ru', 'en'] as $locale) {
            $docs = require base_path("lang/{$locale}/docs.php");

            foreach (Route::getRoutes() as $route) {
                $uri = $route->uri();

                if (!str_starts_with($uri, 'api/')) {
                    continue;
                }

                $controllerClass = $route->getControllerClass();
                if (!$controllerClass || !str_starts_with($controllerClass, 'App\\Http\\Controllers\\')) {
                    continue;
                }

                $methodName = $route->getActionMethod();
                $resourceKey = Str::lower(str_replace('Controller', '', class_basename($controllerClass)));

                foreach ([
                    "tags.{$resourceKey}" => $docs['tags'][$resourceKey] ?? null,
                    "{$resourceKey}.{$methodName}.summary" => $docs[$resourceKey][$methodName]['summary'] ?? null,
                    "{$resourceKey}.{$methodName}.description" => $docs[$resourceKey][$methodName]['description'] ?? null,
                ] as $key => $value) {
                    if (!is_string($value) || trim($value) === '') {
                        $missing[] = "{$locale}: {$uri} ".class_basename($controllerClass)."@{$methodName} missing docs.{$key}";
                    }
                }
            }
        }

        $this->assertSame([], $missing);
    }
}
