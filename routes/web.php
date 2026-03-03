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

Route::get('/endpoints/import', [EndpointController::class, 'importForm'])->name('endpoints.import');
Route::post('/endpoints/import', [EndpointController::class, 'importStore'])->name('endpoints.import.store');
Route::resource('endpoints', EndpointController::class);
