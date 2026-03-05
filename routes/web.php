<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\EndpointController;
use Illuminate\Support\Facades\Route;

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

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/endpoints/import', [EndpointController::class, 'importForm'])->name('endpoints.import');
    Route::post('/endpoints/import', [EndpointController::class, 'importStore'])->name('endpoints.import.store');
    Route::resource('endpoints', EndpointController::class);

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
