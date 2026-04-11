<?php

use App\Http\Controllers\Web\ReaderAuthController;
use App\Http\Controllers\Web\ReaderPortalController;
use App\Models\Article;
use Illuminate\Support\Facades\Route;

Route::get('/', [ReaderAuthController::class, 'entry'])->name('reader.entry');
Route::get('/login', function () {
    return redirect()->route('reader.auth');
})->name('login');
Route::get('/admin', function () {
    return response()->file(public_path('portal.html'));
})->name('admin.portal');

Route::get('/dashboard', function () {
    return redirect()->route('admin.portal');
});

Route::middleware('guest')->group(function () {
    Route::get('/reader/auth', [ReaderAuthController::class, 'show'])->name('reader.auth');
    Route::post('/reader/login', [ReaderAuthController::class, 'login'])->name('reader.login');
    Route::post('/reader/register', [ReaderAuthController::class, 'register'])->name('reader.register');
    Route::post('/reader/verify-otp', [ReaderAuthController::class, 'verifyOtp'])->name('reader.verify-otp');
    Route::post('/reader/resend-otp', [ReaderAuthController::class, 'resendOtp'])->name('reader.resend-otp');
});

Route::middleware(['auth', 'role:reader'])->prefix('reader')->name('reader.')->group(function () {
    Route::get('/', [ReaderPortalController::class, 'home'])->name('home');
    Route::get('/search', [ReaderPortalController::class, 'search'])->name('search');
    Route::get('/library', [ReaderPortalController::class, 'library'])->name('library');
    Route::get('/wallet', [ReaderPortalController::class, 'wallet'])->name('wallet');
    Route::post('/wallet/orders', [ReaderPortalController::class, 'storePurchaseOrder'])->name('wallet.orders');
    Route::get('/profile', [ReaderPortalController::class, 'profile'])->name('profile');
    Route::post('/profile/photo', [ReaderPortalController::class, 'updatePhoto'])->name('profile.photo');
    Route::put('/profile/password', [ReaderPortalController::class, 'updatePassword'])->name('profile.password');
    Route::get('/articles/{article:slug}', [ReaderPortalController::class, 'showArticle'])->name('articles.show');
    Route::post('/articles/{article:slug}/unlock', [ReaderPortalController::class, 'unlockArticle'])->name('articles.unlock');
    Route::post('/logout', [ReaderAuthController::class, 'logout'])->name('logout');
});

Route::get('/stories/{article:slug}', function (Article $article) {
    abort_unless($article->isPublished(), 404);

    $article->load('author');

    return response()
        ->view('article-share', ['article' => $article])
        ->header('Content-Type', 'text/html; charset=UTF-8');
})->name('stories.show');

Route::get('/up', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
});
