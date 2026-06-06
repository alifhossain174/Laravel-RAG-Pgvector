<?php

use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ConversationMessageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/documents/upload', [DocumentController::class, 'create'])->name('documents.create');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
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

require __DIR__.'/auth.php';
