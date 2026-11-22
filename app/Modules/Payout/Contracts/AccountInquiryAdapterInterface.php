<?php

namespace App\Modules\Payout\Contracts;

interface AccountInquiryAdapterInterface
{
    public function providerCode(): string;

    public function isEnabled(): bool;

    public function supportsChannel(string $destinationType, string $channelCode): bool;

    /**
     * Inquire registered account holder name via official provider API.
     *
     * @return array{
     *   supported: bool,
     *   found: bool,
     *   verification_status: string,
     *   account_holder_name: string|null,
     *   can_input_manually: bool,
     *   provider: string,
     *   message: string|null
     * }
     */
    public function inquire(string $destinationType, string $channelCode, string $accountNumber): array;
}
