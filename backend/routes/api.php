<?php

use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\ModerationController;
use App\Http\Controllers\Api\Admin\WithdrawalApprovalController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\UnlockController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::get('/articles', [ArticleController::class, 'index']);
Route::post('/payments/razorpay/webhook', [PaymentController::class, 'razorpayWebhook']);

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/profile-photo', [AuthController::class, 'updateProfilePhoto']);
        Route::put('/password', [AuthController::class, 'changePassword']);
    });

    Route::get('/wallet', [WalletController::class, 'show']);
    Route::get('/wallet/transactions', [WalletController::class, 'transactions']);
    Route::post('/wallet/purchase-orders', [WalletController::class, 'createPurchaseOrder']);

    Route::get('/articles/mine', [ArticleController::class, 'myArticles'])->middleware('role:author');
    Route::post('/articles', [ArticleController::class, 'store'])->middleware('role:author');
    Route::put('/articles/{article:id}', [ArticleController::class, 'update'])->middleware('role:author');
    Route::post('/articles/{article:id}/submit', [ArticleController::class, 'submit'])->middleware('role:author');
    Route::post('/articles/{article:slug}/unlock', [UnlockController::class, 'store']);
    Route::get('/unlocks', [UnlockController::class, 'index']);

    Route::post('/payments/razorpay/confirm', [PaymentController::class, 'confirmRazorpay']);

    Route::get('/withdrawals', [WithdrawalController::class, 'index'])->middleware('role:author');
    Route::post('/withdrawals', [WithdrawalController::class, 'store'])->middleware('role:author');

    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/articles/pending', [ModerationController::class, 'pending']);
        Route::post('/articles/{article:id}/approve', [ModerationController::class, 'approve']);
        Route::post('/articles/{article:id}/reject', [ModerationController::class, 'reject']);
        Route::get('/withdrawals/pending', [WithdrawalApprovalController::class, 'pending']);
        Route::post('/withdrawals/{withdrawal}/approve', [WithdrawalApprovalController::class, 'approve']);
        Route::post('/withdrawals/{withdrawal}/reject', [WithdrawalApprovalController::class, 'reject']);
    });
});

Route::get('/articles/{article:slug}', [ArticleController::class, 'show']);
