<?php

return [
    'media_disk' => env('CHAT_MEDIA_DISK', 'local'),
    'max_image_kb' => (int) env('CHAT_MAX_IMAGE_KB', 5120),
    'allowed_image_mimes' => ['jpg', 'jpeg', 'png', 'webp'],
    'messages_per_page' => (int) env('CHAT_MESSAGES_PER_PAGE', 30),
    'max_messages_per_page' => 100,
    'typing_throttle_seconds' => (int) env('CHAT_TYPING_THROTTLE_SECONDS', 3),
    'use_queue_worker' => filter_var(env('CHAT_USE_QUEUE_WORKER', false), FILTER_VALIDATE_BOOLEAN),
    'whatsapp_bridge_enabled' => filter_var(env('CHAT_WHATSAPP_BRIDGE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'host_models' => [
        'user' => env('CHAT_USER_MODEL', 'App\\Models\\User'),
        'order' => env('CHAT_ORDER_MODEL', 'App\\Models\\Order'),
        'shipment' => env('CHAT_SHIPMENT_MODEL', 'App\\Models\\Shipment'),
        'courier' => env('CHAT_COURIER_MODEL', 'App\\Models\\Courier'),
    ],
];
