<?php

use App\Http\Controllers\BeneficiaryController;
use App\Http\Controllers\StatementController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/wallet');
Route::middleware('guest')->group(function () {
    Route::get('/login', [WalletController::class, 'loginForm'])->name('login');
    Route::post('/login', [WalletController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/signup', [WalletController::class, 'signupForm'])->name('signup');
    Route::post('/signup', [WalletController::class, 'signup'])->middleware('throttle:5,1');
});
Route::middleware('auth')->group(function () {
    Route::get('/wallet', [WalletController::class, 'dashboard'])->name('wallet');
    Route::post('/beneficiaries/lookup', [BeneficiaryController::class, 'lookup'])->middleware('throttle:10,1')->name('beneficiaries.lookup');
    Route::post('/beneficiaries', [BeneficiaryController::class, 'store'])->middleware('throttle:10,1')->name('beneficiaries.store');
    Route::delete('/beneficiaries/{beneficiary}', [BeneficiaryController::class, 'destroy'])->name('beneficiaries.destroy');
    Route::post('/transfer', [WalletController::class, 'transfer'])->middleware('throttle:10,1')->name('transfer');
    Route::get('/statement', [StatementController::class, 'statement'])->name('statement');
    Route::get('/receipt/{transfer}', [StatementController::class, 'receipt'])->name('receipt');
    Route::post('/logout', [WalletController::class, 'logout'])->name('logout');
});
