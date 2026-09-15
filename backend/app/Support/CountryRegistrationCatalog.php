<?php

namespace App\Support;

use App\Models\CountryRegistration;

/**
 * Populate the editable country registration catalog from config.
 *
 * The catalog is what the admin panel lists and edits, but it was written only
 * by `db:seed` — which a deploy running `php artisan migrate` never executes.
 * So on a migrated-but-unseeded installation the Country Registration screen
 * was simply empty, while the onboarding form carried on working because the
 * service falls back to the config file (report item 1). The admin could not
 * see, let alone manage, the fields clients were actually being asked for.
 *
 * Idempotent: one row per (country, field_key), and re-running refreshes the
 * definition without duplicating or resurrecting anything an admin deactivated.
 */
class CountryRegistrationCatalog
{
    /** @return int rows written */
    public static function apply(): int
    {
        $written = self::seedCountry('*', config('country_registrations.default_fields', []));

        foreach (config('country_registrations.overrides', []) as $code => $fields) {
            $written += self::seedCountry($code, $fields);
        }

        return $written;
    }

    /** @return int rows actually created or altered for this country */
    private static function seedCountry(string $code, array $fields): int
    {
        $changed = 0;

        foreach ($fields as $index => $field) {
            $row = CountryRegistration::updateOrCreate(
                ['country_code' => $code, 'field_key' => $field['key']],
                [
                    'label' => $field['label'],
                    'required' => (bool) ($field['required'] ?? false),
                    'applies_to' => self::appliesTo($field['types'] ?? ['fi', 'corporate']),
                    'pattern' => $field['pattern'] ?? null,
                    'pattern_message' => $field['pattern_message'] ?? null,
                    'checksum' => $field['checksum'] ?? null,
                    'placeholder' => $field['placeholder'] ?? null,
                    'help' => $field['help'] ?? null,
                    'order' => $index,
                    'is_active' => true,
                ]
            );

            // Count real work, not rows touched: the sync command's value is
            // that a second run reports nothing, which is how an operator can
            // tell whether a deploy actually needed it.
            if ($row->wasRecentlyCreated || $row->wasChanged()) {
                $changed++;
            }
        }

        return $changed;
    }

    private static function appliesTo(array $types): string
    {
        $fi = in_array('fi', $types, true);
        $corp = in_array('corporate', $types, true);

        if ($fi && $corp) {
            return 'both';
        }

        return $fi ? 'fi' : 'corporate';
    }
}
