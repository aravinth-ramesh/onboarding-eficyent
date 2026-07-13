<?php

namespace App\Services\DocumentIntelligence\Rules;

use Rakibdevs\MrzParser\MrzParser;
use Rakibdevs\MrzParser\MrzResult;

/**
 * Detects and parses the machine-readable zone (MRZ) of passports, ID cards,
 * and visas. MRZ lines carry the expiry date with ICAO 9303 check digits, so
 * a valid parse is deterministic — no heuristics involved.
 */
class MrzReader
{
    // ICAO 9303 line lengths: TD3 (passport) 44, TD2/visa 36, TD1 (id card) 30.
    private const LINE_LENGTHS = [44, 36, 30];

    public function read(string $text): ?MrzResult
    {
        $candidates = [];
        foreach (preg_split('/\R/', $text) as $line) {
            $line = strtoupper(str_replace(' ', '', trim($line)));
            if ($line !== '' && preg_match('/^[A-Z0-9<]+$/', $line)
                && in_array(strlen($line), self::LINE_LENGTHS, true)
                && str_contains($line, '<')) {
                $candidates[strlen($line)][] = $line;
            }
        }

        // MRZ blocks are consecutive same-length lines (2 for TD3/TD2, 3 for TD1).
        foreach ($candidates as $lines) {
            $result = MrzParser::tryRead(implode("\n", $lines));
            if ($result !== null && $result->dateOfExpiry !== null) {
                return $result;
            }
        }

        return null;
    }
}
