<?php

namespace App\Support;

final class PhoneNormalizer
{
    public static function digits(string $phone): string
    {
        return preg_replace('/\D/', '', trim($phone)) ?? '';
    }

    public static function digitsMatch(string $left, string $right): bool
    {
        $leftDigits = self::digits($left);
        $rightDigits = self::digits($right);

        if ($leftDigits === '' || $rightDigits === '') {
            return false;
        }

        if ($leftDigits === $rightDigits) {
            return true;
        }

        $minLength = min(strlen($leftDigits), strlen($rightDigits));

        if ($minLength >= 10) {
            return substr($leftDigits, -10) === substr($rightDigits, -10);
        }

        return false;
    }
}
