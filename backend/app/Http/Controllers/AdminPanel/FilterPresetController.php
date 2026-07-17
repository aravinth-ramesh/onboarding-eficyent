<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Models\FilterPreset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class FilterPresetController extends Controller
{
    /**
     * Save the filters currently in the query string under a name. Re-using a
     * name overwrites that preset, so admins can refine one without piling up
     * near-duplicates.
     */
    public function store(Request $request, string $context): RedirectResponse
    {
        abort_unless(array_key_exists($context, FilterPreset::CONTEXTS), 404);

        $validated = $request->validate([
            'name' => 'required|string|max:60',
        ]);

        $name = trim($validated['name']);
        $filters = $this->filtersFrom($request, $context);

        if (empty($filters)) {
            return back()->with('error', 'Set at least one filter before saving a preset.');
        }

        FilterPreset::updateOrCreate(
            [
                'admin_id' => Auth::guard('admin')->id(),
                'context' => $context,
                'name' => $name,
            ],
            ['filters' => $filters],
        );

        return redirect()->route("admin.{$context}.index", $filters)
            ->with('success', "Preset \"{$name}\" saved.");
    }

    /**
     * Download this admin's presets for one page as JSON.
     *
     * JSON rather than the CSV the other admin exports use: a preset is
     * configuration, not a row — its filters are a structured blob that would
     * land in a spreadsheet cell as unreadable soup and could not be read back
     * in. `version` is here so a later import can tell what it is holding.
     */
    public function export(string $context): JsonResponse
    {
        abort_unless(array_key_exists($context, FilterPreset::CONTEXTS), 404);

        $presets = FilterPreset::ownedBy(Auth::guard('admin')->id(), $context)->get();

        // Ids and admin_id are deliberately left out — they mean nothing
        // outside this database, and a preset is fully described by its name
        // and filters.
        $payload = [
            'version' => 1,
            'context' => $context,
            'exported_at' => now()->toIso8601String(),
            'presets' => $presets->map(fn (FilterPreset $p) => [
                'name' => $p->name,
                'filters' => $p->filters,
            ])->all(),
        ];

        $filename = "filter-presets-{$context}-" . now()->format('Y-m-d') . '.json';

        return response()->json($payload, 200, [
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Copy an existing preset's filters under a new name — a starting point
     * for a variation without having to rebuild the filters by hand.
     *
     * Unlike store(), a name collision is refused rather than overwritten:
     * "duplicate" means make another one, so silently replacing the preset
     * the admin named would be the opposite of what they asked for.
     */
    public function duplicate(Request $request, string $context, FilterPreset $preset): RedirectResponse
    {
        $this->guard($context, $preset);

        $validated = $request->validate(['name' => $this->nameRules($context)]);

        $copy = FilterPreset::create([
            'admin_id' => Auth::guard('admin')->id(),
            'context' => $context,
            'name' => trim($validated['name']),
            'filters' => $preset->filters,
        ]);

        return back()->with('success', "Preset \"{$preset->name}\" duplicated as \"{$copy->name}\".");
    }

    /**
     * Rename a preset in place, keeping its filters. Like duplicate(), a
     * collision with another preset is refused — but the preset's own name is
     * ignored, so re-submitting it unchanged is not an error.
     */
    public function rename(Request $request, string $context, FilterPreset $preset): RedirectResponse
    {
        $this->guard($context, $preset);

        $validated = $request->validate(['name' => $this->nameRules($context, $preset->id)]);

        $was = $preset->name;
        $preset->update(['name' => trim($validated['name'])]);

        return back()->with('success', $was === $preset->name
            ? "Preset \"{$was}\" is unchanged."
            : "Preset \"{$was}\" renamed to \"{$preset->name}\".");
    }

    public function destroy(string $context, FilterPreset $preset): RedirectResponse
    {
        $this->guard($context, $preset);

        $preset->delete();

        return back()->with('success', "Preset \"{$preset->name}\" deleted.");
    }

    /**
     * A preset may only be touched by its owner, through the page it belongs
     * to — the {context} in the URL is not free to disagree with the record.
     */
    private function guard(string $context, FilterPreset $preset): void
    {
        abort_unless(array_key_exists($context, FilterPreset::CONTEXTS), 404);
        abort_unless($preset->context === $context, 404);
        abort_unless($preset->admin_id === Auth::guard('admin')->id(), 403);
    }

    /**
     * Names are unique per admin per page. `$ignoreId` lets a rename keep its
     * own name without colliding with itself.
     */
    private function nameRules(string $context, ?int $ignoreId = null): array
    {
        $adminId = Auth::guard('admin')->id();

        $unique = Rule::unique('filter_presets')
            ->where(fn ($q) => $q->where('admin_id', $adminId)->where('context', $context));

        if ($ignoreId !== null) {
            $unique->ignore($ignoreId);
        }

        return ['required', 'string', 'max:60', $unique];
    }

    /**
     * The active, non-empty filters for this page — blanks are dropped so an
     * empty box can't be saved as if it narrowed anything.
     */
    private function filtersFrom(Request $request, string $context): array
    {
        $filters = collect($request->only(FilterPreset::CONTEXTS[$context]))
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->all();

        return FilterPreset::normalize($context, $filters);
    }
}
