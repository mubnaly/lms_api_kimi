<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
});

// Health check endpoint (web)
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'service' => config('app.name'),
        'version' => '1.0.0',
    ]);
})->name('web.health');

// Redirect to API documentation
Route::get('/docs', function () {
    return redirect('/');
})->name('web.docs');
