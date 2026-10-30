<?php

namespace Tests\Unit;

use App\Services\BuyerIdentity;
use Tests\TestCase;

/**
 * Regresi data buyer iPaymu: placeholder seperti "User 1234" dan email
 * `@citycourier.local` / `@example.com` membuat iPaymu menolak transaksi
 * production dengan pesan "Suspicious buyer".
 */
class BuyerIdentityTest extends TestCase
{
    public function test_placeholder_names_are_detected(): void
    {
        foreach (['', 'User 1234', 'user 99', 'Tester', 'Customer', 'Buyer', 'Pelanggan', 'Admin', 'Test 01'] as $name) {
            $this->assertTrue(BuyerIdentity::isPlaceholderName($name), "Harus placeholder: {$name}");
        }

        foreach (['Budi Santoso', 'Siti Aminah', 'Andrew', 'Pelanggan Setia'] as $name) {
            $this->assertFalse(BuyerIdentity::isPlaceholderName($name), "Bukan placeholder: {$name}");
        }
    }

    public function test_name_skips_placeholders_and_falls_back(): void
    {
        $this->assertSame(
            'Budi Santoso',
            BuyerIdentity::name('User 1234', 'Budi Santoso', 'Andrew')
        );
        $this->assertSame(
            'Pelanggan City Courier',
            BuyerIdentity::name('User 1234', '', null)
        );
    }

    public function test_phone_is_normalized_to_local_format(): void
    {
        $this->assertSame('081234567890', BuyerIdentity::phone('+62 812-3456-7890'));
        $this->assertSame('081234567890', BuyerIdentity::phone('6281234567890'));
        $this->assertSame('081234567890', BuyerIdentity::phone('81234567890'));
        $this->assertSame('081234567890', BuyerIdentity::phone('', '0812 3456 7890'));
        $this->assertSame('', BuyerIdentity::phone(null));
    }

    public function test_reserved_email_domains_are_rejected(): void
    {
        foreach ([
            '081234567890@citycourier.local',
            'customer@example.com',
            'tester@test.com',
            'user@invalid',
            'user@localhost',
            'user@foo.local',
            'not-an-email',
            '',
        ] as $email) {
            $this->assertFalse(BuyerIdentity::isUsableEmail($email), "Harus ditolak: {$email}");
        }

        foreach (['budi@gmail.com', 'budi.santoso@yahoo.co.id', 'admin@neonflux.my.id'] as $email) {
            $this->assertTrue(BuyerIdentity::isUsableEmail($email), "Harus valid: {$email}");
        }
    }

    public function test_email_falls_back_to_merchant_domain_when_invalid(): void
    {
        config(['ipaymu.buyer_email_domain' => 'neonflux.my.id', 'ipaymu.buyer_email' => null]);

        $this->assertSame(
            'budi@gmail.com',
            BuyerIdentity::email('budi@gmail.com', '081234567890@citycourier.local', '081234567890')
        );

        // Alamat hasil generate (local-part berisi nomor telepon) tidak dipakai.
        $this->assertSame(
            'pesanan@neonflux.my.id',
            BuyerIdentity::email('081234567890@citycourier.local', null, '081234567890')
        );
    }

    public function test_generated_looking_emails_are_not_usable(): void
    {
        $this->assertTrue(BuyerIdentity::isGeneratedLookingEmail('pelanggan.081234567890@neonflux.my.id'));
        $this->assertFalse(BuyerIdentity::isUsableEmail('pelanggan.081234567890@neonflux.my.id'));

        // Nama pengguna dengan beberapa digit tetap boleh.
        $this->assertFalse(BuyerIdentity::isGeneratedLookingEmail('budi1234@gmail.com'));
        $this->assertTrue(BuyerIdentity::isUsableEmail('budi1234@gmail.com'));
    }

    public function test_configured_merchant_email_is_used_before_generated_address(): void
    {
        config([
            'ipaymu.buyer_email_domain' => 'neonflux.my.id',
            'ipaymu.buyer_email'        => 'cs@neonflux.my.id',
        ]);

        $this->assertSame(
            'cs@neonflux.my.id',
            BuyerIdentity::email('081234567890@citycourier.local', null, '081234567890')
        );

        // Email pengguna yang benar-benar valid tetap diutamakan.
        $this->assertSame(
            'budi@gmail.com',
            BuyerIdentity::email('budi@gmail.com', null, '081234567890')
        );
    }

    public function test_email_domain_derives_from_app_url_when_config_empty(): void
    {
        config(['ipaymu.buyer_email_domain' => null, 'app.url' => 'https://www.neonflux.my.id']);

        // Prefix `www.` dibuang: alamat email memakai domain apex.
        $this->assertSame('neonflux.my.id', BuyerIdentity::emailDomain());
    }
}
