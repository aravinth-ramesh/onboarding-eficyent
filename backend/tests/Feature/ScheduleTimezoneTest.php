<?php

namespace Tests\Feature;

use App\Support\ScheduleTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A datetime-local input carries no offset, so "14:30" is whatever the admin's
 * clock says. It was stored verbatim into a UTC column, firing the email out by
 * exactly the admin's offset (report item 19).
 */
class ScheduleTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_local_wall_clock_time_is_stored_as_the_right_instant(): void
    {
        config(['app.display_timezone' => 'Asia/Kolkata']); // UTC+5:30

        $utc = ScheduleTime::toUtc('2026-09-20T14:30');

        $this->assertSame('2026-09-20 09:00:00', $utc->toDateTimeString());
        $this->assertSame('UTC', $utc->timezoneName);
    }

    public function test_it_round_trips_back_to_what_the_admin_typed(): void
    {
        config(['app.display_timezone' => 'Asia/Kolkata']);

        $shown = ScheduleTime::forDisplay(ScheduleTime::toUtc('2026-09-20T14:30'));

        $this->assertSame('20 Sep 2026 14:30', $shown->format('d M Y H:i'));
    }

    public function test_a_time_later_today_is_future_even_when_utc_has_passed_it(): void
    {
        // The exact failure: at 20:00 IST (14:30 UTC) an admin picks 21:00 IST.
        // Read as UTC that is already past, so the save was refused.
        config(['app.display_timezone' => 'Asia/Kolkata']);
        $this->travelTo(\Carbon\Carbon::parse('2026-09-20 14:30:00', 'UTC'));

        $this->assertTrue(ScheduleTime::isFuture('2026-09-20T21:00'));
        $this->assertFalse(ScheduleTime::isFuture('2026-09-20T19:00'), 'an hour already gone is still past');
    }

    public function test_a_past_time_is_still_refused(): void
    {
        config(['app.display_timezone' => 'UTC']);
        $this->travelTo(\Carbon\Carbon::parse('2026-09-20 12:00:00', 'UTC'));

        $this->assertFalse(ScheduleTime::isFuture('2026-09-20T11:00'));
    }

    public function test_the_default_configuration_changes_nothing(): void
    {
        // Shipping default is UTC, so the conversion must be a no-op until an
        // installation opts in.
        config(['app.display_timezone' => 'UTC']);

        $this->assertSame('2026-09-20 14:30:00', ScheduleTime::toUtc('2026-09-20T14:30')->toDateTimeString());
    }

    public function test_malformed_input_is_not_treated_as_a_valid_future_time(): void
    {
        $this->assertFalse(ScheduleTime::isFuture('not a date'));
    }
}
