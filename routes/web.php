<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\EImzoAuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// ─── Public: redirect root to login ───
Route::get('/', fn() => auth()->check() ? redirect()->route('home') : redirect()->route('login'));

// ─── E-IMZO Auth ───
Route::middleware('guest')->group(function () {
    Route::get('/login', [EImzoAuthController::class, 'showLogin'])->name('login');
    Route::post('/eimzo/authenticate', [EImzoAuthController::class, 'authenticate'])->name('eimzo.authenticate');
    // Password login (for admin & staff)
    Route::post('/login/password', [EImzoAuthController::class, 'loginWithPassword'])->name('login.password');
});

// Challenge endpoint (no auth needed for PKCS7 creation)
Route::get('/frontend/challenge', [EImzoAuthController::class, 'getChallenge'])->name('eimzo.challenge');

// ─── Authenticated (staff/admin) ───
Route::middleware('auth')->group(function () {
    Route::post('/logout', [EImzoAuthController::class, 'logout'])->name('logout');

    // Pending approval waiting page (no approved check here)
    Route::get('/approval/pending', function () {
        if (Auth::user()->canAccessSystem()) return redirect()->route('home');
        return view('auth.pending');
    })->name('approval.pending');

    // All routes below require approved status
    Route::middleware('approved')->group(function () {

        // Home / Transactions list
        Route::get('/home', [TransactionController::class, 'index'])->name('home');

        // Dashboard
        Route::get('/dashboard', [TransactionController::class, 'dashboard'])->name('dashboard');

        // Summary report (Свод)
        Route::get('/summary', [TransactionController::class, 'summary'])->name('summary');

        // Summary report 2 (Свод 2)
        Route::get('/summary2', [TransactionController::class, 'summary2'])->name('summary2');

        // ─── Profile ───
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile');

        // ─── IT Admin panel ───
        Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
            Route::get('/', [AdminController::class, 'index'])->name('dashboard');
            Route::get('/users', [AdminController::class, 'users'])->name('users');
            Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
            Route::get('/users/{user}/edit', [AdminController::class, 'editUser'])->name('users.edit');
            Route::patch('/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
            Route::delete('/users/{user}', [AdminController::class, 'destroyUser'])->name('users.destroy');
            // Approve / Reject
            Route::post('/users/{user}/approve', [AdminController::class, 'approveUser'])->name('users.approve');
            Route::post('/users/{user}/reject', [AdminController::class, 'rejectUser'])->name('users.reject');
            // Cache management
            Route::post('/clear-cache', [TransactionController::class, 'clearCache'])->name('clear-cache');
        });
    }); // end approved group
}); // end auth group

