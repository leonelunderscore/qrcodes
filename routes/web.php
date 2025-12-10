<?php

use App\Http\Controllers\ProcessController;
use Illuminate\Support\Facades\Route;


Route::controller(ProcessController::class)->group(function () {
    Route::get('link/{reference}', 'download')->name('link');
});

Route::fallback(fn() => redirect('/files'));
