<?php

use App\Modules\Chat\Http\Controllers\ConversationController;
use App\Modules\Chat\Http\Controllers\DeviceTokenController;
use App\Modules\Chat\Http\Controllers\MessageController;
use App\Modules\Chat\Http\Controllers\RealtimeController;
use App\Modules\Chat\Http\Controllers\WhatsappPreferenceController;
use App\Modules\WhatsApp\Http\Controllers\WebhookController;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

// Public Meta WhatsApp Business Cloud API webhook endpoints (verified via verify_token & X-Hub-Signature-256)
Route::prefix('api/v1/whatsapp')->group(function (): void {
    Route::get('/webhook', [WebhookController::class, 'verify'])
        ->name('citycourier.whatsapp.webhook.verify');
    Route::post('/webhook', [WebhookController::class, 'handle'])
        ->name('citycourier.whatsapp.webhook.handle');
});

Route::prefix('api/v1')
    ->middleware(['auth:sanctum', SubstituteBindings::class])
    ->group(function (): void {
        Route::prefix('chat')->group(function (): void {
            Route::get('/realtime-config', [RealtimeController::class, 'config'])
                ->name('citycourier.chat.realtime_config');
            Route::post('/broadcasting/auth', [RealtimeController::class, 'authenticate'])
                ->name('citycourier.chat.broadcasting.auth');

            Route::get('/unread-count', [ConversationController::class, 'unreadSummary'])
                ->name('citycourier.chat.unread');

            Route::get('/conversations', [ConversationController::class, 'index'])
                ->name('citycourier.chat.conversations.index');
            Route::post('/conversations/by-order/{orderIdentifier}', [ConversationController::class, 'openByOrder'])
                ->name('citycourier.chat.conversations.open_by_order');
            Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])
                ->whereNumber('conversation')
                ->name('citycourier.chat.conversations.show');

            Route::get('/conversations/{conversation}/messages', [MessageController::class, 'index'])
                ->whereNumber('conversation')
                ->name('citycourier.chat.messages.index');
            Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])
                ->whereNumber('conversation')
                ->name('citycourier.chat.messages.store');
            Route::post('/conversations/{conversation}/read', [MessageController::class, 'markRead'])
                ->whereNumber('conversation')
                ->name('citycourier.chat.messages.read');
            Route::post('/conversations/{conversation}/typing', [MessageController::class, 'typing'])
                ->whereNumber('conversation')
                ->name('citycourier.chat.messages.typing');

            Route::get('/media/{message}', [MessageController::class, 'media'])
                ->whereNumber('message')
                ->name('citycourier.chat.media');

            Route::get('/whatsapp/status', [WhatsappPreferenceController::class, 'status'])
                ->name('citycourier.chat.whatsapp.status');
            Route::post('/whatsapp/opt-in', [WhatsappPreferenceController::class, 'updateOptIn'])
                ->name('citycourier.chat.whatsapp.opt_in');
        });

        Route::post('/devices/fcm-token', [DeviceTokenController::class, 'store'])
            ->name('citycourier.devices.fcm.store');
        Route::delete('/devices/fcm-token', [DeviceTokenController::class, 'destroy'])
            ->name('citycourier.devices.fcm.destroy');
    });
