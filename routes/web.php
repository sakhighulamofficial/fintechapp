<?php
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;
Route::redirect('/', '/wallet');
Route::middleware('guest')->group(function () {
    Route::get('/login', [WalletController::class,'loginForm'])->name('login');
    Route::post('/login', [WalletController::class,'login'])->middleware('throttle:10,1');
});
Route::middleware('auth')->group(function () {
    Route::get('/wallet', [WalletController::class,'dashboard'])->name('wallet');
    Route::post('/transfer', [WalletController::class,'transfer'])->middleware('throttle:10,1')->name('transfer');
    Route::post('/logout', [WalletController::class,'logout'])->name('logout');
});
