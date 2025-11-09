<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');   // or redirect('/login');
    // Route::get('/', fn () => redirect('/login'));
});
