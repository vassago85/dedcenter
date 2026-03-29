<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/score/{any?}', function () {
    return view('scoring');
})->where('any', '.*')->name('scoring');
