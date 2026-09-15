<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Convert between the wall-clock time an admin types and the UTC instant the
 * scheduler compares against.
 *
 * A `datetime-local` input carries no offset: "2026-09-20T14:30" is whatever
 * the admin's clock says. It was stored verbatim, and because the app runs in
 * UTC that became 14:30 UTC — so the email fired at the wrong hour, by exactly
 * the admin's offset (report item 19). The browser's own "is this in the
 * future?" check read the same string as local time, so the two disagreed and
 * a genuinely future time could be rejected as past.
 *
 * The database stays in UTC — ScheduledEmail::scopeDue depends on that — so the
 * conversion belongs at the edges: parse on the way in, format on the way out.
 */
class ScheduleTime
{
    /** The zone admins enter and read times in. */
    public static function zone(): string
    {
        return config('app.display_timezone') ?: config('app.timezone', 'UTC');
    }

    /** Read a naive "YYYY-MM-DDTHH:MM" as the admin's local time, in UTC. */
    public static function toUtc(string $localWallClock): CarbonImmutable
    {
        return CarbonImmutable::parse($localWallClock, self::zone())->utc();
    }

    /** Render a stored UTC instant back in the admin's zone. */
    public static function forDisplay(?\DateTimeInterface $utc): ?CarbonImmutable
    {
        return $utc === null ? null : CarbonImmutable::instance($utc)->setTimezone(self::zone());
    }

    /** Whether the entered wall-clock time is still ahead of now. */
    public static function isFuture(string $localWallClock): bool
    {
        try {
            return self::toUtc($localWallClock)->isFuture();
        } catch (\Throwable) {
            return false;
        }
    }
}
