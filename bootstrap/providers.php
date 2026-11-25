<?php

use App\Modules\WhatsApp\WhatsAppCloudServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\ViewServiceProvider;

return [
    AppServiceProvider::class,
    ViewServiceProvider::class,
    WhatsAppCloudServiceProvider::class,
];
