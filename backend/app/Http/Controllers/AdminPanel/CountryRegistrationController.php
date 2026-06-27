<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Models\CountryRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CountryRegistrationController extends Controller
{
    public function index(Request $request): View
    {
        $country = $request->query('country');

        $registrations = CountryRegistration::query()
            ->when($country, fn ($q) => $q->where('country_code', $country))
            ->orderByRaw("country_code = '*' desc")
            ->orderBy('country_code')
            ->orderBy('order')
            ->paginate(30)
            ->withQueryString();

        $usedCodes = CountryRegistration::query()
            ->select('country_code')->distinct()->orderBy('country_code')->pluck('country_code');

        return view('admin.country-registrations.index', [
            'registrations' => $registrations,
            'countryNames' => $this->countryNames(),
            'usedCodes' => $usedCodes,
            'filter' => $country,
        ]);
    }

    public function create(): View
    {
        return view('admin.country-registrations.form', [
            'registration' => null,
            'countryOptions' => $this->countryOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $registration = CountryRegistration::create($this->validateData($request));

        return redirect()
            ->route('admin.country-registrations.index', ['country' => $registration->country_code])
            ->with('success', 'Registration field created successfully.');
    }

    public function edit(CountryRegistration $countryRegistration): View
    {
        return view('admin.country-registrations.form', [
            'registration' => $countryRegistration,
            'countryOptions' => $this->countryOptions(),
        ]);
    }

    public function update(Request $request, CountryRegistration $countryRegistration): RedirectResponse
    {
        $countryRegistration->update($this->validateData($request));

        return redirect()
            ->route('admin.country-registrations.index', ['country' => $countryRegistration->country_code])
            ->with('success', 'Registration field updated successfully.');
    }

    public function destroy(CountryRegistration $countryRegistration): RedirectResponse
    {
        $code = $countryRegistration->country_code;
        $countryRegistration->delete();

        return redirect()
            ->route('admin.country-registrations.index', ['country' => $code])
            ->with('success', 'Registration field deleted successfully.');
    }

    private function validateData(Request $request): array
    {
        $validated = $request->validate([
            'country_code' => ['required', 'string', 'max:2'],
            'field_key' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/'],
            'label' => ['required', 'string', 'max:255'],
            'applies_to' => ['required', 'in:both,fi,corporate'],
            'pattern' => ['nullable', 'string', 'max:255'],
            'pattern_message' => ['nullable', 'string', 'max:255'],
            'checksum' => ['nullable', 'in:gstin,abn,cnpj'],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'help' => ['nullable', 'string', 'max:1000'],
            'order' => ['nullable', 'integer', 'min:0'],
            'required' => ['boolean'],
            'is_active' => ['boolean'],
        ], [
            'field_key.regex' => 'The field key may only contain lowercase letters, numbers and underscores.',
        ]);

        // A user-supplied regex must be compilable, otherwise it silently
        // breaks validation for every client in that country.
        if (!empty($validated['pattern']) && @preg_match('#' . $validated['pattern'] . '#u', '') === false) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'pattern' => 'This is not a valid regular expression.',
            ]);
        }

        $validated['required'] = $request->boolean('required');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['order'] = $validated['order'] ?? 0;

        return $validated;
    }

    /**
     * @return array<string, string> code => name, including the '*' default.
     */
    private function countryOptions(): array
    {
        return ['*' => 'Default (all other countries)'] + $this->countryNames();
    }

    private function countryNames(): array
    {
        return config('country_registrations.countries', []);
    }
}
