<?php

namespace App\Helpers;

class PhoneNormalizer
{
    /**
     * Normalize phone number by stripping formatting and optional country code
     * 
     * @param string|null $phone
     * @return string
     */
    public static function normalize(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }

        // Remove all non-digit characters
        $normalized = preg_replace('/\D/', '', $phone);

        // Remove leading country code if present (common Indian codes: 91, +91)
        // Keep last 10 digits for Indian numbers
        if (strlen($normalized) > 10 && (str_starts_with($normalized, '91') || str_starts_with($normalized, '1'))) {
            $normalized = substr($normalized, -10);
        }

        return $normalized;
    }
}
