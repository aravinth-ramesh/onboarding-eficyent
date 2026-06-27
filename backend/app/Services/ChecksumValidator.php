<?php

namespace App\Services;

/**
 * Validates the check digit(s) of registration identifiers — catching numbers
 * that match the format regex but are mathematically invalid (typos,
 * transposed digits). Unknown algorithms pass through (never block).
 */
class ChecksumValidator
{
    public function isValid(?string $algorithm, string $value): bool
    {
        if (!$algorithm) {
            return true;
        }

        return match ($algorithm) {
            'gstin' => $this->gstin($value),
            'abn' => $this->abn($value),
            'cnpj' => $this->cnpj($value),
            default => true,
        };
    }

    /** Indian GSTIN — base-36 weighted check character (15th). */
    private function gstin(string $v): bool
    {
        $v = strtoupper(trim($v));
        if (strlen($v) !== 15) {
            return false;
        }
        $cp = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $mod = 36;
        $factor = 2;
        $sum = 0;
        for ($i = 13; $i >= 0; $i--) {
            $code = strpos($cp, $v[$i]);
            if ($code === false) {
                return false;
            }
            $digit = $factor * $code;
            $factor = ($factor === 2) ? 1 : 2;
            $digit = intdiv($digit, $mod) + ($digit % $mod);
            $sum += $digit;
        }
        $check = ($mod - ($sum % $mod)) % $mod;

        return $cp[$check] === $v[14];
    }

    /** Australian Business Number — mod 89 with 1 subtracted from the first digit. */
    private function abn(string $v): bool
    {
        $v = preg_replace('/\D/', '', $v);
        if (strlen($v) !== 11) {
            return false;
        }
        $weights = [10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19];
        $digits = str_split($v);
        $digits[0] = (string) ((int) $digits[0] - 1);
        $sum = 0;
        for ($i = 0; $i < 11; $i++) {
            $sum += ((int) $digits[$i]) * $weights[$i];
        }

        return $sum % 89 === 0;
    }

    /** Brazilian CNPJ — two mod-11 check digits. */
    private function cnpj(string $v): bool
    {
        $v = preg_replace('/\D/', '', $v);
        if (strlen($v) !== 14 || preg_match('/^(\d)\1{13}$/', $v)) {
            return false;
        }
        $calc = function (int $len) use ($v) {
            $weights = $len === 12
                ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
                : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
            $sum = 0;
            for ($i = 0; $i < $len; $i++) {
                $sum += ((int) $v[$i]) * $weights[$i];
            }
            $r = $sum % 11;

            return $r < 2 ? 0 : 11 - $r;
        };

        return (int) $v[12] === $calc(12) && (int) $v[13] === $calc(13);
    }
}
