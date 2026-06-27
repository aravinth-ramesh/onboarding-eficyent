<?php

namespace Database\Seeders;

use App\Models\CountryRegistration;
use Illuminate\Database\Seeder;

class CountryRegistrationSeeder extends Seeder
{
    /**
     * Seed the editable country registration catalog from the config file.
     * Idempotent: re-running keeps a single row per (country, field_key).
     */
    public function run(): void
    {
        // Generic default fields, stored under the reserved '*' country code.
        $this->seedCountry('*', config('country_registrations.default_fields', []));

        foreach (config('country_registrations.overrides', []) as $code => $fields) {
            $this->seedCountry($code, $fields);
        }
    }

    private function seedCountry(string $code, array $fields): void
    {
        foreach ($fields as $index => $field) {
            CountryRegistration::updateOrCreate(
                ['country_code' => $code, 'field_key' => $field['key']],
                [
                    'label' => $field['label'],
                    'required' => (bool) ($field['required'] ?? false),
                    'applies_to' => $this->appliesTo($field['types'] ?? ['fi', 'corporate']),
                    'pattern' => $field['pattern'] ?? null,
                    'pattern_message' => $field['pattern_message'] ?? null,
                    'placeholder' => $field['placeholder'] ?? null,
                    'help' => $field['help'] ?? null,
                    'order' => $index,
                    'is_active' => true,
                ]
            );
        }
    }

    private function appliesTo(array $types): string
    {
        $fi = in_array('fi', $types, true);
        $corp = in_array('corporate', $types, true);

        if ($fi && $corp) {
            return 'both';
        }

        return $fi ? 'fi' : 'corporate';
    }
}
