<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Models\FilterPreset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        return collect($request->only(FilterPreset::CONTEXTS[$context]))
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->all();
    }
}
