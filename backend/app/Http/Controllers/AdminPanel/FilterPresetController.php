<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Models\FilterPreset;
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
     * Copy an existing preset's filters under a new name — a starting point
     * for a variation without having to rebuild the filters by hand.
     *
     * Unlike store(), a name collision is refused rather than overwritten:
     * "duplicate" means make another one, so silently replacing the preset
     * the admin named would be the opposite of what they asked for.
     */
    public function duplicate(Request $request, string $context, FilterPreset $preset): RedirectResponse
    {
        abort_unless(array_key_exists($context, FilterPreset::CONTEXTS), 404);
        abort_unless($preset->admin_id === Auth::guard('admin')->id(), 403);

        $adminId = Auth::guard('admin')->id();

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:60',
                Rule::unique('filter_presets')->where(
                    fn ($q) => $q->where('admin_id', $adminId)->where('context', $context),
                ),
            ],
        ]);

        $copy = FilterPreset::create([
            'admin_id' => $adminId,
            'context' => $context,
            'name' => trim($validated['name']),
            'filters' => $preset->filters,
        ]);

        return back()->with('success', "Preset \"{$preset->name}\" duplicated as \"{$copy->name}\".");
    }

    public function destroy(string $context, FilterPreset $preset): RedirectResponse
    {
        abort_unless($preset->admin_id === Auth::guard('admin')->id(), 403);

        $preset->delete();

        return back()->with('success', "Preset \"{$preset->name}\" deleted.");
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
