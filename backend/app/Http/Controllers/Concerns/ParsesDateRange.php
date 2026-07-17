<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Carbon;

/**
 * Shared handling for the from/to date filters on admin list pages.
 */
trait ParsesDateRange
{
    /**
     * Parse a YYYY-MM-DD filter value, ignoring anything malformed — a filter
     * the admin fat-fingered should narrow nothing, not blow up the page.
     */
    protected function parseDate(?string $value): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
