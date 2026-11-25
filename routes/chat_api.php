<?php

use App\Modules\WhatsApp\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Public Meta WhatsApp Business Cloud API webhook endpoints (verified via verify_token & X-Hub-Signature-256).
// All internal chat and VoIP endpoints have been removed; direct communication uses WhatsApp.
Route::prefix('api/v1/whatsapp')->group(function (): void {
    Route::get('/webhook', [WebhookController::class, 'verify'])
        ->name('citycourier.whatsapp.webhook.verify');
    Route::post('/webhook', [WebhookController::class, 'handle'])
        ->name('citycourier.whatsapp.webhook.handle');
});
