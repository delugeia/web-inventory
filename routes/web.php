<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\EndpointController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('auth.login');
})->middleware('guest')->name('home');

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
    })->middleware('verified')->name('dashboard');

    Route::get('/endpoints/import', [EndpointController::class, 'importForm'])->middleware('verified')->name('endpoints.import');
    Route::post('/endpoints/import', [EndpointController::class, 'importStore'])->middleware('verified')->name('endpoints.import.store');
    Route::get('/endpoints/{endpoint}/resolve', [EndpointController::class, 'resolve'])->middleware('verified')->name('endpoints.resolve');
    Route::post('/endpoints/{endpoint}/resolve', [EndpointController::class, 'resolveStore'])->middleware('verified')->name('endpoints.resolve.store');
    Route::resource('endpoints', EndpointController::class)->middleware('verified');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
