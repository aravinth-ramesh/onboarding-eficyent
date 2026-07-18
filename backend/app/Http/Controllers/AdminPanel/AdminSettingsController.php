<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\FilterPreset;
use App\Models\HistoryPin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminSettingsController extends Controller
{
    /**
     * The customization actions and their labels, keyed by the route name the
     * activity-log middleware records — which carries the group's "admin."
     * prefix.
     */
    private const HISTORY_LABELS = [
        'admin.filter-presets.store' => 'Saved a preset',
        'admin.filter-presets.rename' => 'Renamed a preset',
        'admin.filter-presets.duplicate' => 'Duplicated a preset',
        'admin.filter-presets.destroy' => 'Deleted a preset',
        'admin.filter-presets.destroy-all' => 'Deleted all saved views',
        'admin.filter-presets.import' => 'Imported presets',
        'admin.filter-presets.pin' => 'Pinned or unpinned a saved view',
        'admin.filter-presets.bulk-pin' => 'Bulk pin / unpin',
        'admin.filter-presets.unpin-all' => 'Unpinned all saved views',
        'admin.filter-presets.reorder' => 'Reordered saved views',
        'admin.filter-presets.reset-order' => 'Reset order to A→Z',
        'admin.settings.pin-shortcut' => 'Changed the pin shortcut',
        'admin.settings.reset-preset-customizations' => 'Reset all customizations',
    ];

    /**
     * The admin's own history of preset customizations — a readable view over
     * the activity log the middleware already records for every such action.
     */
    public function presetHistory(Request $request): View
    {
        // Only a known customization action narrows the list; anything else is
        // ignored so a stray value can't produce an empty or odd view.
        $selected = $this->selectedAction($request);

        $search = $this->searchTerm($request);
        $pinnedIds = $this->pinnedLogIds();

        $history = $this->historyQuery($selected, $search)
            ->paginate(30)
            ->withQueryString()
            ->through(fn (AdminActivityLog $log) => [
                'id' => $log->id,
                'at' => $log->created_at,
                'label' => self::HISTORY_LABELS[$log->action] ?? $log->action,
                'detail' => $this->historyDetail($log),
                'page' => $this->contextFromPath($log->path),
                'ok' => $log->status < 400,
                'pinned' => $pinnedIds->contains($log->id),
            ]);

        // When a clear is in effect, surface it with how many entries it hides,
        // so the admin can restore them (the audit rows were never deleted).
        $clearedAt = Auth::guard('admin')->user()->preset_history_cleared_at;
        $hiddenCount = $clearedAt
            ? AdminActivityLog::where('admin_id', Auth::guard('admin')->id())
                ->whereIn('action', array_keys(self::HISTORY_LABELS))
                ->where('created_at', '<=', $clearedAt)
                ->count()
            : 0;

        return view('admin.settings.preset-history', [
            'history' => $history,
            'actions' => self::HISTORY_LABELS,
            'selectedAction' => $selected,
            'search' => $search,
            'clearedAt' => $clearedAt,
            'hiddenCount' => $hiddenCount,
            'hasPinnedHistory' => $pinnedIds->isNotEmpty(),
        ]);
    }

    /** Unpin every history entry the admin has pinned, in one go. */
    public function unpinAllHistory(): RedirectResponse
    {
        $count = HistoryPin::where('admin_id', Auth::guard('admin')->id())->delete();

        return back()->with(
            $count > 0 ? 'success' : 'error',
            $count > 0
                ? "Unpinned all {$count} history entr" . ($count === 1 ? 'y' : 'ies') . '.'
                : 'There were no pinned history entries.',
        );
    }

    /**
     * Undo a clear: bring back everything hidden by the cut-off. Non-destructive
     * all along — clearing only moved the cut-off, so this just removes it.
     */
    public function restorePresetHistory(): RedirectResponse
    {
        Auth::guard('admin')->user()->update(['preset_history_cleared_at' => null]);

        return redirect()->route('admin.settings.preset-history')
            ->with('success', 'Customization history restored.');
    }

    /**
     * Stream the customization history as CSV — same admin, same action filter
     * as the page, so what you see is what you export. Page labels are used so
     * the file is readable, not the raw route names.
     */
    public function exportPresetHistory(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $selected = $this->selectedAction($request);
        $search = $this->searchTerm($request);
        $pages = ['user-onboardings' => 'Onboardings', 'scheduled-emails' => 'Scheduled Emails'];
        $filename = 'preset-history-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($selected, $search, $pages) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['When (UTC)', 'Action', 'Details', 'Page', 'Status']);

            $this->historyQuery($selected, $search)
                ->lazy()
                ->each(function (AdminActivityLog $log) use ($out, $pages) {
                    $page = $this->contextFromPath($log->path);
                    fputcsv($out, [
                        $log->created_at->toDateTimeString(),
                        self::HISTORY_LABELS[$log->action] ?? $log->action,
                        // Strip the display quotes so the CSV cell is plain.
                        trim((string) $this->historyDetail($log), '“”'),
                        $page ? ($pages[$page] ?? $page) : '',
                        $log->status < 400 ? 'ok' : 'failed',
                    ]);
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Clear the admin's customization history — from their view only. This
     * records a cut-off timestamp; the append-only admin_activity_logs audit
     * trail (also shown on the Admin Activity page) is never deleted.
     */
    public function clearPresetHistory(): RedirectResponse
    {
        Auth::guard('admin')->user()->update(['preset_history_cleared_at' => now()]);

        return redirect()->route('admin.settings.preset-history')
            ->with('success', 'Customization history cleared from your view. The admin audit log is unaffected.');
    }

    /**
     * The current admin's history, filtered to a chosen action and free-text
     * search — pinned entries first, then newest first — and only entries after
     * the last "clear".
     */
    private function historyQuery(?string $selected, ?string $search = null)
    {
        $clearedAt = Auth::guard('admin')->user()->preset_history_cleared_at;
        $pinnedIds = $this->pinnedLogIds();

        return AdminActivityLog::where('admin_id', Auth::guard('admin')->id())
            ->whereIn('action', array_keys(self::HISTORY_LABELS))
            ->when($selected, fn ($q) => $q->where('action', $selected))
            ->when($clearedAt, fn ($q) => $q->where('created_at', '>', $clearedAt))
            ->when($search, function ($q) use ($search) {
                // Match the detail text (in the payload), the page (in the path)
                // and the action label (mapped in PHP, so resolve it to actions).
                $like = '%' . $search . '%';
                $actions = array_keys(array_filter(
                    self::HISTORY_LABELS,
                    fn ($label) => str_contains(strtolower($label), strtolower($search)),
                ));

                $q->where(function ($sub) use ($like, $actions) {
                    $sub->where('payload', 'like', $like)->orWhere('path', 'like', $like);
                    if ($actions) {
                        $sub->orWhereIn('action', $actions);
                    }
                });
            })
            // Ids come straight from the DB, so imploding them is injection-safe.
            ->when($pinnedIds->isNotEmpty(), fn ($q) => $q->orderByRaw(
                'case when id in (' . $pinnedIds->map(fn ($i) => (int) $i)->implode(',') . ') then 0 else 1 end'
            ))
            ->latest('created_at');
    }

    /** The free-text history search term, or null. */
    private function searchTerm(Request $request): ?string
    {
        return $request->filled('search') ? trim($request->input('search')) : null;
    }

    /** The log ids the current admin has pinned. */
    private function pinnedLogIds(): \Illuminate\Support\Collection
    {
        return HistoryPin::where('admin_id', Auth::guard('admin')->id())->pluck('admin_activity_log_id');
    }

    /**
     * Pin or unpin one history entry so it floats to the top of the admin's
     * history view. Only the admin's own customization entries are pinnable.
     */
    public function toggleHistoryPin(AdminActivityLog $log): RedirectResponse
    {
        $adminId = Auth::guard('admin')->id();
        abort_unless($log->admin_id === $adminId, 403);
        abort_unless(array_key_exists($log->action, self::HISTORY_LABELS), 404);

        $existing = HistoryPin::where('admin_id', $adminId)
            ->where('admin_activity_log_id', $log->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return back()->with('success', 'History entry unpinned.');
        }

        HistoryPin::create(['admin_id' => $adminId, 'admin_activity_log_id' => $log->id]);

        return back()->with('success', 'History entry pinned to top.');
    }

    /**
     * Pin (or unpin) several selected history entries at once. Only the admin's
     * own customization entries are eligible; any other id in the list matches
     * nothing and is silently ignored.
     */
    public function bulkPinHistory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'pinned' => 'required|boolean',
        ]);

        $adminId = Auth::guard('admin')->id();

        $eligible = AdminActivityLog::where('admin_id', $adminId)
            ->whereIn('action', array_keys(self::HISTORY_LABELS))
            ->whereIn('id', $validated['ids'])
            ->pluck('id');

        if ($request->boolean('pinned')) {
            $eligible->each(fn ($id) => HistoryPin::firstOrCreate([
                'admin_id' => $adminId, 'admin_activity_log_id' => $id,
            ]));
        } else {
            HistoryPin::where('admin_id', $adminId)->whereIn('admin_activity_log_id', $eligible)->delete();
        }

        $count = $eligible->count();

        return back()->with(
            $count > 0 ? 'success' : 'error',
            $count > 0
                ? "{$count} entr" . ($count === 1 ? 'y' : 'ies') . ($request->boolean('pinned') ? ' pinned.' : ' unpinned.')
                : 'No history entries were updated.',
        );
    }

    /** The action to filter on, or null — only a known customization action counts. */
    private function selectedAction(Request $request): ?string
    {
        return $request->filled('action') && array_key_exists($request->input('action'), self::HISTORY_LABELS)
            ? $request->input('action')
            : null;
    }

    /** A short human detail for a history row, pulled from the logged payload. */
    private function historyDetail(AdminActivityLog $log): ?string
    {
        $p = $log->payload ?? [];

        return match ($log->action) {
            'admin.filter-presets.store', 'admin.filter-presets.rename', 'admin.filter-presets.duplicate'
                => isset($p['name']) ? '“' . $p['name'] . '”' : null,
            'admin.filter-presets.bulk-pin'
                => (isset($p['ids']) ? count((array) $p['ids']) . ' view(s) ' : '')
                    . (($p['pinned'] ?? null) ? 'pinned' : 'unpinned'),
            'admin.filter-presets.reorder'
                => isset($p['order']) ? count((array) $p['order']) . ' views reordered' : null,
            'admin.settings.pin-shortcut'
                => filled($p['pin_shortcut'] ?? null)
                    ? Admin::displayShortcut(strtolower($p['pin_shortcut']))
                    : 'reset to default',
            default => null,
        };
    }

    /** The list page a customization happened on, parsed from the request path. */
    private function contextFromPath(?string $path): ?string
    {
        if ($path && preg_match('#filter-presets/([a-z-]+)#', $path, $m)
            && array_key_exists($m[1], FilterPreset::CONTEXTS)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Reset everything the admin has customised about their saved views —
     * across every page — back to defaults: alphabetical order, nothing
     * pinned, and the default pin shortcut. The saved views themselves are
     * kept; only their arrangement and the shortcut are reset.
     */
    public function resetPresetCustomizations(): RedirectResponse
    {
        $adminId = Auth::guard('admin')->id();

        foreach (array_keys(FilterPreset::CONTEXTS) as $context) {
            $position = 0;
            FilterPreset::where('admin_id', $adminId)
                ->where('context', $context)
                ->orderBy('name')
                ->get()
                ->each(function (FilterPreset $preset) use (&$position) {
                    $position++;
                    $preset->update(['position' => $position, 'pinned' => false]);
                });
        }

        Auth::guard('admin')->user()->update(['pin_shortcut' => null]);

        return back()->with('success',
            'Preset customisations reset — alphabetical order, nothing pinned, default shortcut. Your saved views were kept.');
    }

    /**
     * Save the admin's keyboard shortcut for pinning the applied saved view.
     * The combo is one or more modifiers plus a single key, e.g. "shift+p" or
     * "ctrl+alt+k" — a modifier is required so the shortcut can't fire from an
     * ordinary keypress. An empty value resets to the default.
     */
    public function updatePinShortcut(Request $request): RedirectResponse
    {
        $value = strtolower(trim((string) $request->input('pin_shortcut')));

        if ($value === '' || $value === 'default') {
            Auth::guard('admin')->user()->update(['pin_shortcut' => null]);

            return back()->with('success', 'Pin shortcut reset to the default (Shift+P).');
        }

        $request->merge(['pin_shortcut' => $value])->validate([
            // 1+ modifiers, then exactly one alphanumeric key.
            'pin_shortcut' => ['required', 'string', 'regex:/^(ctrl|alt|shift|meta)(\+(ctrl|alt|shift|meta))*\+[a-z0-9]$/'],
        ], [
            'pin_shortcut.regex' => 'Use a modifier (Ctrl, Alt, Shift or Cmd) plus one key.',
        ]);

        Auth::guard('admin')->user()->update(['pin_shortcut' => $value]);

        return back()->with('success', 'Pin shortcut updated to ' . Admin::displayShortcut($value) . '.');
    }
}
