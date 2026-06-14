<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminDocumentController;
use App\Http\Controllers\Admin\AdminQueueController;
use App\Http\Controllers\Admin\AdminUsageLogController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminUserLimitController;
use App\Http\Controllers\Admin\UserSuspensionController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ConversationMessageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuspendedAccountController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/account/suspended', SuspendedAccountController::class)
    ->middleware('auth')
    ->name('account.suspended');

Route::middleware(['auth', 'verified', 'not_suspended'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/documents/upload', [DocumentController::class, 'create'])->name('documents.create');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/{document}/status', [DocumentController::class, 'status'])->name('documents.status');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    Route::get('/chat', [ConversationController::class, 'index'])->name('chat.index');
    Route::post('/chat', [ConversationController::class, 'store'])->name('chat.store');
    Route::get('/chat/create', [ConversationController::class, 'create'])->name('chat.create');
    Route::get('/chat/{conversation}', [ConversationController::class, 'show'])->name('chat.show');
    Route::delete('/chat/{conversation}', [ConversationController::class, 'destroy'])->name('chat.destroy');
    Route::post('/chat/{conversation}/messages', [ConversationMessageController::class, 'store'])->name('chat.messages.store');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'verified', 'not_suspended', 'admin'])
    ->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::patch('/users/{user}/promote', [AdminUserController::class, 'promote'])->name('users.promote');
        Route::patch('/users/{user}/demote', [AdminUserController::class, 'demote'])->name('users.demote');
        Route::patch('/users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend');
        Route::patch('/users/{user}/activate', [AdminUserController::class, 'activate'])->name('users.activate');
        Route::patch('/users/{user}/limits', [AdminUserLimitController::class, 'update'])->name('users.limits.update');

        Route::patch('/users/{user}/suspension', [UserSuspensionController::class, 'update'])->name('users.suspension.update');
        Route::delete('/users/{user}/suspension', [UserSuspensionController::class, 'destroy'])->name('users.suspension.destroy');

        Route::get('/documents', [AdminDocumentController::class, 'index'])->name('documents.index');
        Route::get('/documents/{document}', [AdminDocumentController::class, 'show'])->name('documents.show');
        Route::post('/documents/{document}/retry', [AdminDocumentController::class, 'retry'])->name('documents.retry');
        Route::post('/documents/{document}/regenerate-embeddings', [AdminDocumentController::class, 'regenerateEmbeddings'])->name('documents.regenerate-embeddings');
        Route::post('/documents/{document}/reprocess', [AdminDocumentController::class, 'reprocess'])->name('documents.reprocess');
        Route::delete('/documents/{document}', [AdminDocumentController::class, 'destroy'])->name('documents.destroy');

        Route::get('/queues', [AdminQueueController::class, 'index'])->name('queues.index');
        Route::get('/failed-jobs', [AdminQueueController::class, 'failedJobs'])->name('failed-jobs.index');
        Route::post('/failed-jobs/retry-all', [AdminQueueController::class, 'retryAllFailedJobs'])->name('failed-jobs.retry-all');
        Route::post('/failed-jobs/{failedJob}/retry', [AdminQueueController::class, 'retryFailedJob'])->name('failed-jobs.retry');
        Route::delete('/failed-jobs/{failedJob}', [AdminQueueController::class, 'forgetFailedJob'])->name('failed-jobs.destroy');

        Route::get('/usage-logs', [AdminUsageLogController::class, 'index'])->name('usage-logs.index');
        Route::get('/usage-logs/{aiUsageLog}', [AdminUsageLogController::class, 'show'])->name('usage-logs.show');
    });

require __DIR__.'/auth.php';
