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

    /** Never create more than this from one file, however large. */
    private const IMPORT_LIMIT = 200;

    /**
     * Load presets from a JSON file produced by export(). Non-destructive by
     * default: a name that already exists is left alone unless "overwrite" is
     * asked for, so an import can never silently replace an admin's own views.
     * Every incoming filter set is re-sanitised — the file is untrusted input.
     */
    public function import(Request $request, string $context): RedirectResponse
    {
        abort_unless(array_key_exists($context, FilterPreset::CONTEXTS), 404);

        $request->validate([
            'file' => 'required|file|max:256', // KB
            'overwrite' => 'sometimes|boolean',
        ]);

        $data = json_decode((string) file_get_contents($request->file('file')->getRealPath()), true);

        if (! is_array($data) || ($data['version'] ?? null) !== 1) {
            return back()->with('error', 'That does not look like a filter preset export (version 1).');
        }

        if (($data['context'] ?? null) !== $context) {
            $from = is_string($data['context'] ?? null) ? $data['context'] : 'unknown';

            return back()->with('error', "That file holds \"{$from}\" presets — import it from that page instead.");
        }

        $entries = $data['presets'] ?? null;
        if (! is_array($entries)) {
            return back()->with('error', 'That file has no presets to import.');
        }

        $adminId = Auth::guard('admin')->id();
        $overwrite = $request->boolean('overwrite');
        $truncated = count($entries) > self::IMPORT_LIMIT;
        $imported = $skipped = $invalid = 0;

        foreach (array_slice($entries, 0, self::IMPORT_LIMIT) as $entry) {
            $name = is_array($entry) && is_string($entry['name'] ?? null) ? trim($entry['name']) : '';
            $filters = is_array($entry) && is_array($entry['filters'] ?? null)
                ? $this->sanitizeFilters($context, $entry['filters'])
                : [];

            // A preset with no name, an over-long name, or nothing left after
            // sanitising is not something we can meaningfully store.
            if ($name === '' || mb_strlen($name) > 60 || empty($filters)) {
                $invalid++;
                continue;
            }

            $exists = FilterPreset::where('admin_id', $adminId)
                ->where('context', $context)
                ->where('name', $name)
                ->exists();

            if ($exists && ! $overwrite) {
                $skipped++;
                continue;
            }

            FilterPreset::updateOrCreate(
                ['admin_id' => $adminId, 'context' => $context, 'name' => $name],
                ['filters' => $filters],
            );
            $imported++;
        }

        $parts = ["Imported {$imported} preset(s)."];
        if ($skipped) {
            $parts[] = "{$skipped} skipped (name already exists — tick Overwrite to replace).";
        }
        if ($invalid) {
            $parts[] = "{$invalid} skipped (invalid).";
        }
        if ($truncated) {
            $parts[] = 'Only the first ' . self::IMPORT_LIMIT . ' were read.';
        }

        return back()->with($imported > 0 ? 'success' : 'error', implode(' ', $parts));
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
     * Pin a preset to the top of the list, or unpin it. Pinned presets float
     * above the manual ordering; several may be pinned at once.
     */
    public function togglePin(string $context, FilterPreset $preset): RedirectResponse
    {
        $this->guard($context, $preset);

        $preset->update(['pinned' => ! $preset->pinned]);

        return back()->with('success', $preset->pinned
            ? "Preset \"{$preset->name}\" pinned to top."
            : "Preset \"{$preset->name}\" unpinned.");
    }

    /**
     * Persist a manual ordering. `order` is the full list of preset ids in the
     * arrangement the admin dragged/nudged them into; position follows the
     * index. Only the caller's own presets for this page are touched — a
     * foreign or unknown id in the list is ignored, never reordered.
     */
    public function reorder(Request $request, string $context): RedirectResponse|JsonResponse
    {
        abort_unless(array_key_exists($context, FilterPreset::CONTEXTS), 404);

        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer',
        ]);

        $mine = FilterPreset::where('admin_id', Auth::guard('admin')->id())
            ->where('context', $context)
            ->get()
            ->keyBy('id');

        $position = 0;
        foreach ($validated['order'] as $id) {
            if ($preset = $mine->get($id)) {
                $position++;
                if ($preset->position !== $position) {
                    $preset->update(['position' => $position]);
                }
            }
        }

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Saved view order updated.');
    }

    /**
     * Reset the manual ordering back to the default: alphabetical by name.
     * This is the order presets displayed in before manual reordering existed,
     * so "default" means A→Z. Only the caller's own presets for this page.
     */
    public function resetOrder(string $context): RedirectResponse
    {
        abort_unless(array_key_exists($context, FilterPreset::CONTEXTS), 404);

        $presets = FilterPreset::where('admin_id', Auth::guard('admin')->id())
            ->where('context', $context)
            ->orderBy('name')
            ->get();

        $position = 0;
        foreach ($presets as $preset) {
            $position++;
            if ($preset->position !== $position) {
                $preset->update(['position' => $position]);
            }
        }

        return back()->with(
            $presets->isNotEmpty() ? 'success' : 'error',
            $presets->isNotEmpty()
                ? 'Saved view order reset to alphabetical.'
                : 'There were no saved views to reset.',
        );
    }

    /**
     * Delete all of this admin's presets for one page at once. Only ever the
     * caller's own, and only for this page — one admin clearing their views
     * never touches another's, or the same admin's views on a different page.
     */
    public function destroyAll(string $context): RedirectResponse
    {
        abort_unless(array_key_exists($context, FilterPreset::CONTEXTS), 404);

        $deleted = FilterPreset::where('admin_id', Auth::guard('admin')->id())
            ->where('context', $context)
            ->delete();

        return back()->with(
            $deleted > 0 ? 'success' : 'error',
            $deleted > 0
                ? "Deleted all {$deleted} saved view(s) for this page."
                : 'There were no saved views to delete.',
        );
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
        return $this->sanitizeFilters($context, $request->only(FilterPreset::CONTEXTS[$context]));
    }

    /**
     * Reduce a raw filters array to what this page recognises: only its known
     * keys, values coerced to trimmed strings, blanks and non-scalars dropped.
     * The gate for imported data — a hand-edited file can carry anything, and
     * nothing outside this allow-list ever reaches the query builder.
     */
    private function sanitizeFilters(string $context, array $filters): array
    {
        $clean = [];

        foreach (FilterPreset::CONTEXTS[$context] as $key) {
            if (! array_key_exists($key, $filters) || ! is_scalar($filters[$key])) {
                continue;
            }

            $value = trim((string) $filters[$key]);
            if ($value !== '') {
                $clean[$key] = $value;
            }
        }

        return FilterPreset::normalize($context, $clean);
    }
}
