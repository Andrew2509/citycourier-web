<?php

namespace App\Modules\Chat;

use App\Modules\Chat\Events\InternalChatMessagePersisted;
use App\Modules\Chat\Events\InternalChatMessagesRead;
use App\Modules\Chat\Listeners\BroadcastChatMessageListener;
use App\Modules\Chat\Listeners\BroadcastMessagesReadListener;
use App\Modules\Chat\Listeners\QueueFcmPushListener;
use App\Modules\Chat\Listeners\QueueWhatsAppBridgeListener;
use App\Modules\Chat\Models\CallSession;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Policies\CallSessionPolicy;
use App\Modules\Chat\Policies\ConversationPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../../config/chat.php', 'chat');
        $this->mergeConfigFrom(__DIR__ . '/../../../config/whatsapp.php', 'whatsapp');
        $this->mergeConfigFrom(__DIR__ . '/../../../config/fcm.php', 'fcm');
        $this->mergeConfigFrom(__DIR__ . '/../../../config/voip.php', 'voip');
    }

    public function boot(): void
    {
        Gate::policy(Conversation::class, ConversationPolicy::class);
        Gate::policy(CallSession::class, CallSessionPolicy::class);

        $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../../../routes/chat_api.php');
        self::registerBroadcastChannels();

        Event::listen(InternalChatMessagePersisted::class, BroadcastChatMessageListener::class);
        Event::listen(InternalChatMessagePersisted::class, QueueWhatsAppBridgeListener::class);
        Event::listen(InternalChatMessagePersisted::class, QueueFcmPushListener::class);
        Event::listen(InternalChatMessagesRead::class, BroadcastMessagesReadListener::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../../config/chat.php' => config_path('chat.php'),
                __DIR__ . '/../../../config/whatsapp.php' => config_path('whatsapp.php'),
                __DIR__ . '/../../../config/fcm.php' => config_path('fcm.php'),
            ], 'city-courier-chat-config');

            $this->publishes([
                __DIR__ . '/../../../database/migrations' => database_path('migrations'),
            ], 'city-courier-chat-migrations');
        }
    }

    public static function registerBroadcastChannels(): void
    {
        require __DIR__ . '/../../../routes/chat_channels.php';
    }
}
