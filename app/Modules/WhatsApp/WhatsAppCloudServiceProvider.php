<?php

namespace App\Modules\WhatsApp;

use Illuminate\Support\ServiceProvider;

/**
 * Provides Meta WhatsApp Business Cloud API configuration and webhook routes.
 * Preserves automated WhatsApp Business notifications while internal chat/VoIP
 * modules have been retired in favor of direct WhatsApp communication.
 */
class WhatsAppCloudServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../../config/whatsapp.php', 'whatsapp');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../../../routes/chat_api.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../../config/whatsapp.php' => config_path('whatsapp.php'),
            ], 'city-courier-whatsapp-config');
        }
    }
}
