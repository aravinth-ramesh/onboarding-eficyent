<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\FilterPreset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminSettingsController extends Controller
{
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
