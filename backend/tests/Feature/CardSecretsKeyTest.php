<?php

namespace Tests\Feature;

use App\Modules\Card\Services\CardSecrets;
use RuntimeException;
use Tests\TestCase;

class CardSecretsKeyTest extends TestCase
{
    public function test_generated_numbers_pass_the_luhn_check_and_codes_use_an_unambiguous_alphabet(): void
    {
        $secrets = app(CardSecrets::class);

        foreach (range(1, 50) as $_) {
            $number = $secrets->generateNumber();
            $this->assertMatchesRegularExpression('/^[1-9]\d{11}$/', $number);
            $this->assertSame((int) substr($number, -1), CardSecrets::luhnCheckDigit(substr($number, 0, 11)));
            $this->assertDoesNotMatchRegularExpression('/[ILOU]/', $secrets->generateActivationCode());
        }
    }

    public function test_hashes_are_keyed_and_domain_separated(): void
    {
        $secrets = app(CardSecrets::class);

        $this->assertSame($secrets->hashChipUid('04a22b'), $secrets->hashChipUid('04:A2:2B'));
        $this->assertNotSame($secrets->hashChipUid('1234'), $secrets->hashNumber('1234'));
        $this->assertNotSame(hash('sha256', '04A22B'), $secrets->hashChipUid('04A22B'));

        $before = $secrets->hashChipUid('04A22B');
        config(['ecosystem.card.hmac_key' => base64_encode(str_repeat('k', 32))]);
        $this->assertNotSame($before, $secrets->hashChipUid('04A22B'));
    }

    public function test_a_missing_key_fails_loudly(): void
    {
        config(['ecosystem.card.hmac_key' => '']);

        $this->expectException(RuntimeException::class);
        app(CardSecrets::class)->hashChipUid('04A22B');
    }
}
