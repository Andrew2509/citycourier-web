<?php

use App\Modules\Chat\ChatServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\ViewServiceProvider;

return [
    AppServiceProvider::class,
    ViewServiceProvider::class,
    ChatServiceProvider::class,
];
