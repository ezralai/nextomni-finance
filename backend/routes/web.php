<?php

use App\Http\Controllers\Api\V1\DocsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/docs', [DocsController::class, 'ui'])->name('docs');
