<?php

namespace App\Modules\WhatsApp\Jobs;

use App\Modules\Chat\Models\WhatsappMessageLog;
use App\Modules\WhatsApp\Services\WhatsAppWebhookProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWhatsAppWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $webhookLogId
    ) {
    }

    public function handle(WhatsAppWebhookProcessor $processor): void
    {
        $log = WhatsappMessageLog::query()->find($this->webhookLogId);
        if ($log === null) {
            return;
        }

        $processor->processLog($log);
    }
}
