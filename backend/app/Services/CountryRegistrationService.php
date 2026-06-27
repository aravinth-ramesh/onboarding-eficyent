<?php

namespace App\Services;

use App\Models\CountryRegistration;
use App\Models\UserType;
use Illuminate\Support\Collection;

class CountryRegistrationService
{
    /**
     * Sorted list of countries for the selector (static ISO data from config).
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
     * Registration fields for a single country + category. Reads the editable
     * catalog (DB), falling back to the country's '*' default, then to the
     * config catalog when the table has not been seeded yet.
     */
    public function fieldsFor(string $countryCode, string $category): array
    {
        if (!CountryRegistration::query()->exists()) {
            return $this->configFieldsFor($countryCode, $category);
        }

        $rows = $this->activeRowsFor($countryCode);
        if ($rows->isEmpty()) {
            $rows = $this->activeRowsFor('*');
        }

        return $rows->filter(fn (CountryRegistration $r) => $r->appliesToCategory($category))
            ->map(fn (CountryRegistration $r) => $r->toField())
            ->values()
            ->all();
    }

    /**
     * Full catalog payload for a category: the generic default plus only the
     * countries that have specific fields.
     *
     * @return array{default_fields: array, overrides: array}
     */
    public function catalogForCategory(string $category): array
    {
        if (!CountryRegistration::query()->exists()) {
            return $this->configCatalogForCategory($category);
        }

        $rows = CountryRegistration::query()
            ->where('is_active', true)
            ->orderBy('country_code')->orderBy('order')->orderBy('id')
            ->get()
            ->filter(fn (CountryRegistration $r) => $r->appliesToCategory($category));

        $default = $rows->where('country_code', '*')
            ->map(fn (CountryRegistration $r) => $r->toField())->values()->all();

        $overrides = [];
        foreach ($rows->where('country_code', '!=', '*')->groupBy('country_code') as $code => $group) {
            $overrides[$code] = $group->map(fn (CountryRegistration $r) => $r->toField())->values()->all();
        }

        return ['default_fields' => $default, 'overrides' => $overrides];
    }

    private function activeRowsFor(string $code): Collection
    {
        return CountryRegistration::query()
            ->where('is_active', true)
            ->where('country_code', $code)
            ->orderBy('order')->orderBy('id')
            ->get();
    }

    // ── Config fallback (used only before the catalog table is seeded) ──

    private function configFieldsFor(string $countryCode, string $category): array
    {
        $overrides = config('country_registrations.overrides', []);
        $source = $overrides[$countryCode] ?? config('country_registrations.default_fields', []);

        return $this->filterConfigByCategory($source, $category);
    }

    private function configCatalogForCategory(string $category): array
    {
        $overrides = [];
        foreach (config('country_registrations.overrides', []) as $code => $fields) {
            $filtered = $this->filterConfigByCategory($fields, $category);
            if (!empty($filtered)) {
                $overrides[$code] = $filtered;
            }
        }

        return [
            'default_fields' => $this->filterConfigByCategory(config('country_registrations.default_fields', []), $category),
            'overrides' => $overrides,
        ];
    }

    private function filterConfigByCategory(array $fields, string $category): array
    {
        return array_values(array_filter(
            $fields,
            fn ($f) => in_array($category, $f['types'] ?? ['fi', 'corporate'], true)
        ));
    }
}
