<?php

use App\Http\Controllers\AutomationController;
use App\Http\Controllers\EndpointController;
use App\Http\Controllers\ProfileController;
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

    Route::get('/automation', [AutomationController::class, 'index'])->middleware('verified')->name('automation.index');
    Route::post('/automation/resolve-multiple', [AutomationController::class, 'resolveMultipleStore'])->middleware('verified')->name('automation.resolve-multiple.store');
    Route::get('/automation/runs/{run}', [AutomationController::class, 'runStatus'])->middleware('verified')->name('automation.runs.show');

    Route::get('/endpoints/import', [EndpointController::class, 'importForm'])->middleware('verified')->name('endpoints.import');
    Route::post('/endpoints/import', [EndpointController::class, 'importStore'])->middleware('verified')->name('endpoints.import.store');
    Route::post('/endpoints/{endpoint}/recheck', [EndpointController::class, 'resolveStore'])->middleware('verified')->name('endpoints.resolve.store');
    Route::get('/endpoints/{endpoint}/cached', [EndpointController::class, 'cached'])->middleware('verified')->name('endpoints.cached');
    Route::get('/endpoints/{endpoint}/cached/content', [EndpointController::class, 'cachedContent'])->middleware('verified')->name('endpoints.cached.content');
    Route::get('/endpoints/{endpoint}/cached/source', [EndpointController::class, 'cachedSource'])->middleware('verified')->name('endpoints.cached.source');
    Route::resource('endpoints', EndpointController::class)->middleware('verified');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
