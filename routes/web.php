<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\DatasetProcessingController;
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

        // Timeline summary report (year/month/day)
        Route::get('/summary/timeline', [TransactionController::class, 'summaryTimeline'])->name('summary.timeline');

        // Gazna CSV-based report
        Route::get('/gazna/svod3', [TransactionController::class, 'gaznaSvod3'])->name('gazna.svod3');

        // Jamgarma first SVOD report
        Route::get('/jamgarma/first-svod', [TransactionController::class, 'jamgarmaFirstSvod'])->name('jamgarma.first_svod');

        // Large dataset cache builder (file-cache progress, no queues)
        Route::get('/processing/cache-builder', [DatasetProcessingController::class, 'index'])->name('processing.index');
        Route::post('/processing/cache-builder/start', [DatasetProcessingController::class, 'start'])->name('processing.start');
        Route::get('/processing/cache-builder/progress', [DatasetProcessingController::class, 'progress'])->name('processing.progress');
        Route::post('/processing/cache-builder/reset', [DatasetProcessingController::class, 'reset'])->name('processing.reset');

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
            Route::post('/warm-cache', [TransactionController::class, 'warmCache'])->name('warm-cache');
        });
    }); // end approved group
}); // end auth group

