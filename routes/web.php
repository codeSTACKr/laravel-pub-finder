<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SearchController;



Route::get('/', function () {
    return view('pubfinder');
});
Route::match(['GET', 'POST'], '/search', [SearchController::class, 'perform'])->name('search.perform');