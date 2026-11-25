<?php

namespace Tests\Unit;

use App\Modules\Chat\Support\PhoneNumberNormalizer;
use Tests\TestCase;

class PhoneNumberNormalizerTest extends TestCase
{
    public function test_normalizes_indonesian_and_e164_numbers(): void
    {
        $this->assertSame('6281234567890', PhoneNumberNormalizer::toE164('081234567890'));
        $this->assertSame('6281234567890', PhoneNumberNormalizer::toE164('+62 812-3456-7890'));
        $this->assertSame('6281234567890', PhoneNumberNormalizer::toE164('006281234567890'));
        $this->assertSame('6281234567890', PhoneNumberNormalizer::toE164('81234567890'));
        $this->assertSame('6591234567', PhoneNumberNormalizer::toE164('+65 9123 4567'));
    }

    public function test_rejects_empty_or_invalid_short_numbers(): void
    {
        $this->assertNull(PhoneNumberNormalizer::toE164(null));
        $this->assertNull(PhoneNumberNormalizer::toE164(''));
        $this->assertNull(PhoneNumberNormalizer::toE164('12345'));
        $this->assertNull(PhoneNumberNormalizer::toE164('abc'));
    }
}
