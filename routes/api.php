<?php

use App\Http\Controllers\HymnController;
use App\Http\Controllers\LiturgicalController;
use App\Http\Controllers\WhatsAppNotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// 1. Liturgical & Coptic Calendar API
Route::get('/liturgical/today', [LiturgicalController::class, 'today']);
Route::get('/liturgical/synaxarium', [LiturgicalController::class, 'synaxarium']);

// 2. WhatsApp Notification API (Throttled for abuse prevention)
Route::middleware('throttle:30,1')->group(function () {
    Route::post('/whatsapp/attendance', [WhatsAppNotificationController::class, 'notifyAttendance']);
    Route::post('/whatsapp/exam-result', [WhatsAppNotificationController::class, 'notifyExamResult']);
    Route::post('/whatsapp/roster', [WhatsAppNotificationController::class, 'notifyRoster']);
});

// 3. Hymns Library API
Route::get('/hymns', [HymnController::class, 'index']);
Route::get('/hymns/{id}', [HymnController::class, 'show']);
