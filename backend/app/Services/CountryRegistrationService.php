<?php

namespace App\Services;

use App\Models\UserType;

class CountryRegistrationService
{
    /**
     * Sorted list of countries for the selector.
     *
     * @return array<int, array{code: string, name: string}>
     */
    public function countries(): array
    {
        $list = config('country_registrations.countries', []);
        $out = [];
        foreach ($list as $code => $name) {
            $out[] = ['code' => $code, 'name' => $name];
        }
        usort($out, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $out;
    }

    /**
     * Classify an organization type into a registration category.
     */
    public function categoryForType(?UserType $type): string
    {
        if (!$type) {
            return 'corporate';
        }
        $haystack = strtolower(($type->slug ?? '') . ' ' . ($type->name ?? ''));

        return preg_match('/financ|bank|nbfc|insur|fund|broker|capital/', $haystack) === 1
            ? 'fi'
            : 'corporate';
    }

    /**
     * Registration fields for a single country + category. Falls back to the
     * generic default set when the country has no specific override.
     */
    public function fieldsFor(string $countryCode, string $category): array
    {
        $overrides = config('country_registrations.overrides', []);
        $source = $overrides[$countryCode] ?? config('country_registrations.default_fields', []);

        return $this->filterByCategory($source, $category);
    }

    /**
     * The full catalog payload for a category: the generic default plus only
     * the countries that have a specific override (keeps the response small).
     *
     * @return array{default_fields: array, overrides: array}
     */
    public function catalogForCategory(string $category): array
    {
        $overrides = [];
        foreach (config('country_registrations.overrides', []) as $code => $fields) {
            $filtered = $this->filterByCategory($fields, $category);
            if (!empty($filtered)) {
                $overrides[$code] = $filtered;
            }
        }

        return [
            'default_fields' => $this->filterByCategory(config('country_registrations.default_fields', []), $category),
            'overrides' => $overrides,
        ];
    }

    private function filterByCategory(array $fields, string $category): array
    {
        return array_values(array_filter(
            $fields,
            fn ($f) => in_array($category, $f['types'] ?? ['fi', 'corporate'], true)
        ));
    }
}
