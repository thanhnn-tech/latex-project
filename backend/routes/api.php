<?php

use App\Http\Controllers\Api\CompileController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectFileController;
use App\Http\Controllers\Api\ProjectFileVersionController;
use App\Http\Controllers\Api\SyncTexController;
use Illuminate\Support\Facades\Route;

Route::get('health', [HealthController::class, 'check']);

Route::apiResource('projects', ProjectController::class)->parameters(['projects' => 'project']);
Route::get('projects/{project}/download', [ProjectController::class, 'download']);
Route::post('projects/import-zip', [ProjectController::class, 'importZip']);

Route::get('projects/{project}/files', [ProjectFileController::class, 'index']);
Route::post('projects/{project}/files', [ProjectFileController::class, 'store']);
Route::post('projects/{project}/files/upload', [ProjectFileController::class, 'upload']);
Route::post('projects/{project}/folders', [ProjectFileController::class, 'storeFolder']);
Route::get('projects/{project}/files/{file}', [ProjectFileController::class, 'show']);
Route::get('projects/{project}/files/{file}/raw', [ProjectFileController::class, 'raw']);
Route::put('projects/{project}/files/{file}', [ProjectFileController::class, 'update']);
Route::put('projects/{project}/files/{file}/move', [ProjectFileController::class, 'move']);
Route::post('projects/{project}/files/{file}/duplicate', [ProjectFileController::class, 'duplicate']);
Route::delete('projects/{project}/files/{file}', [ProjectFileController::class, 'destroy']);

Route::get('projects/{project}/files/{file}/versions', [ProjectFileVersionController::class, 'index']);
Route::get('projects/{project}/files/{file}/versions/{version}', [ProjectFileVersionController::class, 'show']);
Route::post('projects/{project}/files/{file}/versions/{version}/restore', [ProjectFileVersionController::class, 'restore']);

Route::post('projects/{project}/compile', [CompileController::class, 'compile']);
Route::get('projects/{project}/pdf', [CompileController::class, 'pdf']);
Route::get('projects/{project}/logs', [CompileController::class, 'logs']);
Route::get('projects/{project}/problems', [CompileController::class, 'problems']);

Route::get('projects/{project}/synctex/forward', [SyncTexController::class, 'forward']);
Route::get('projects/{project}/synctex/reverse', [SyncTexController::class, 'reverse']);
