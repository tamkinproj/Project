<?php

namespace App\Modules\Card\Services;

use RuntimeException;

/**
 * Card secrets (chip UIDs, card numbers, activation codes) are stored only as keyed
 * HMAC-SHA256 digests, so a database leak alone does not reveal or allow cloning them.
 * Each kind of value is domain-separated so digests cannot be confused with each other.
 */
class CardSecrets
{
    private const ACTIVATION_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'; // Crockford base32: no I, L, O, U

    public function hashChipUid(string $uid): string
    {
        return $this->hmac('chip-uid', self::normalizeChipUid($uid));
    }

    public function hashNumber(string $number): string
    {
        return $this->hmac('card-number', preg_replace('/\D/', '', $number));
    }

    public function hashActivationCode(string $code): string
    {
        return $this->hmac('activation-code', self::normalizeActivationCode($code));
    }

    public static function normalizeChipUid(string $uid): string
    {
        return strtoupper((string) preg_replace('/[^0-9A-Fa-f]/', '', $uid));
    }

    public static function normalizeActivationCode(string $code): string
    {
        return strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', $code));
    }

    // 12-digit member number with a Luhn check digit. Not 16 digits, so it cannot be
    // mistaken for a payment card number.
    public function generateNumber(): string
    {
        $digits = '';
        for ($i = 0; $i < 11; $i++) {
            $digits .= random_int($i === 0 ? 1 : 0, 9);
        }

        return $digits.self::luhnCheckDigit($digits);
    }

    // 10 characters ≈ 50 bits, shown once and formatted as XXXXX-XXXXX.
    public function generateActivationCode(): string
    {
        $code = '';
        for ($i = 0; $i < 10; $i++) {
            $code .= self::ACTIVATION_ALPHABET[random_int(0, 31)];
        }

        return substr($code, 0, 5).'-'.substr($code, 5);
    }

    public static function luhnCheckDigit(string $digits): int
    {
        $sum = 0;
        foreach (array_reverse(str_split($digits)) as $i => $d) {
            $d = (int) $d;
            if ($i % 2 === 0) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
        }

        return (10 - ($sum % 10)) % 10;
    }

    private function hmac(string $domain, string $value): string
    {
        return hash_hmac('sha256', $domain.':'.$value, $this->key());
    }

    private function key(): string
    {
        $configured = (string) config('ecosystem.card.hmac_key');

        if (strlen($configured) < 32) {
            throw new RuntimeException('CARD_HMAC_KEY is missing or too short.');
        }

        $decoded = base64_decode($configured, true);

        return $decoded !== false && strlen($decoded) >= 32 ? $decoded : $configured;
    }
}
