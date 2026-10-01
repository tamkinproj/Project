<?php

namespace Tests\Unit;

use App\Modules\Card\Services\CardSecrets;
use PHPUnit\Framework\TestCase;

class CardSecretsTest extends TestCase
{
    public function test_luhn_check_digit_matches_the_reference_algorithm(): void
    {
        $this->assertSame(3, CardSecrets::luhnCheckDigit('7992739871'));
        $this->assertSame(0, CardSecrets::luhnCheckDigit('0'));
    }

    public function test_chip_uids_normalise_regardless_of_reader_formatting(): void
    {
        $this->assertSame('04A22B1A9C5D80', CardSecrets::normalizeChipUid('04:a2:2b:1a:9c:5d:80'));
        $this->assertSame('04A22B1A9C5D80', CardSecrets::normalizeChipUid('04 A2 2B 1A 9C 5D 80'));
    }

    public function test_activation_codes_normalise_case_and_separators(): void
    {
        $this->assertSame('ABCDE12345', CardSecrets::normalizeActivationCode(' abcde-12345 '));
    }
}
