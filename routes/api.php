<?php

use App\Http\Controllers\CourseController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\UserAvatarController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::apiResource('/user', UserController::class);

Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);

Route::middleware(['auth:api'])->group(function () {
    Route::post('/logout', [UserController::class, 'logout']);
    Route::post('/user/avatar/upload', [UserAvatarController::class, 'upload']);
    Route::post('/user/avatar/destroy', [UserAvatarController::class, 'destroy']);
    Route::post('/refresh', [UserController::class, 'refresh']);
    Route::post('/user/edit', [UserController::class, 'edit']);
    Route::patch('/user/setRole/{id}', [UserController::class, 'setRole']);

    Route::apiResource('/file', FileController::class);
    Route::get('/file/download/{file}', [FileController::class, 'download']);

    Route::apiResource('/course', CourseController::class);
    Route::delete('/course/hardDestroy/{course}', [CourseController::class, 'hardDestroy']);
    Route::post('/course/generateCode/{course}', [CourseController::class, 'generateTeacherCodeInvite']);
    Route::post('/course/addUser', [CourseController::class, 'addUserToCourse']);
    Route::post('/course/restore/{course}', [CourseController::class, 'restore']);
});
