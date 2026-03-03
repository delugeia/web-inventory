<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EndpointController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'env' => config('app.env'),
    ]);
});

Route::resource('endpoints', EndpointController::class);
