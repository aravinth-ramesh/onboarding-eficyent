<?php

namespace App\Services\DocumentIntelligence\Rules;

use Carbon\CarbonImmutable;

/**
 * Finds dates in document text, but only where they mean something: a date
 * is trusted only when it appears within a configured window after a known
 * label ("date of expiry", "statement date", ...). Documents are full of
 * incidental dates; unanchored ones are ignored on purpose.
 */
class DateExtractor
{
    private const MONTHS = 'jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec';

    /**
     * Earliest labeled date for the given label kind ('expiry' or 'issue').
     */
    public function labeled(string $text, string $kind): ?CarbonImmutable
    {
        $labels = config("document_validation.rules.date_labels.{$kind}", []);
        $window = (int) config('document_validation.rules.label_window', 80);

        foreach ($labels as $label) {
            $offset = 0;
            while (($pos = stripos($text, $label, $offset)) !== false) {
                $slice = substr($text, $pos + strlen($label), $window);
                $date = $this->firstDate($slice);
                if ($date !== null) {
                    return $date;
                }
                $offset = $pos + strlen($label);
            }
        }

        return null;
    }

    public function firstDate(string $text): ?CarbonImmutable
    {
        $months = self::MONTHS;

        // Ordered: unambiguous formats first.
        $patterns = [
            // 2026-07-13
            'iso' => '/\b(\d{4})-(\d{2})-(\d{2})\b/',
            // 13 July 2026 | 13th July 2026 | 13 Jul 2026 | 13-Jul-26
            'dmy_name' => "/\b(\d{1,2})(?:st|nd|rd|th)?[\s\-.\/]*({$months})[a-z]*\.?,?[\s\-.\/]*(\d{2,4})\b/i",
            // July 13, 2026 | Jul 13 2026
            'mdy_name' => "/\b({$months})[a-z]*\.?[\s\-.\/]*(\d{1,2})(?:st|nd|rd|th)?,?[\s\-.\/]*(\d{4})\b/i",
            // 13/07/2026, 07-13-2026, 13.07.26
            'numeric' => '/\b(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})\b/',
        ];

        $earliest = null;
        $earliestPos = PHP_INT_MAX;

        foreach ($patterns as $name => $pattern) {
            if (! preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            if ($m[0][1] >= $earliestPos) {
                continue;
            }

            $date = $this->build($name, $m);
            if ($date !== null) {
                $earliest = $date;
                $earliestPos = $m[0][1];
            }
        }

        return $earliest;
    }

    private function build(string $pattern, array $m): ?CarbonImmutable
    {
        try {
            switch ($pattern) {
                case 'iso':
                    return $this->make((int) $m[1][0], (int) $m[2][0], (int) $m[3][0]);

                case 'dmy_name':
                    return $this->make((int) $m[3][0], $this->monthNumber($m[2][0]), (int) $m[1][0]);

                case 'mdy_name':
                    return $this->make((int) $m[3][0], $this->monthNumber($m[1][0]), (int) $m[2][0]);

                case 'numeric':
                    $a = (int) $m[1][0];
                    $b = (int) $m[2][0];
                    $year = (int) $m[3][0];
                    // Disambiguate day/month: a value over 12 must be the day;
                    // otherwise default to day-first (most of the world).
                    if ($a > 12 && $b <= 12) {
                        [$day, $month] = [$a, $b];
                    } elseif ($b > 12 && $a <= 12) {
                        [$day, $month] = [$b, $a];
                    } else {
                        [$day, $month] = [$a, $b];
                    }

                    return $this->make($year, $month, $day);
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function make(int $year, int $month, int $day): ?CarbonImmutable
    {
        if ($year < 100) {
            // Two-digit years: KYC documents live around the present.
            $year += $year <= 69 ? 2000 : 1900;
        }

        if ($month < 1 || $month > 12 || $day < 1 || $day > 31 || $year < 1900 || $year > 2100) {
            return null;
        }

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return CarbonImmutable::create($year, $month, $day)->startOfDay();
    }

    private function monthNumber(string $name): int
    {
        $index = array_search(strtolower(substr($name, 0, 3)), explode('|', self::MONTHS), true);

        return $index === false ? 0 : $index + 1;
    }
}
