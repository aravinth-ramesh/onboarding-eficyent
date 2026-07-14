<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Services\EmailTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EmailTemplateController extends Controller
{
    public function index(): View
    {
        $overrides = EmailTemplate::with('updatedBy')->get()->keyBy('key');

        $templates = collect(EmailTemplateService::REGISTRY)->map(function ($definition, $key) use ($overrides) {
            return (object) [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'customized' => $overrides->has($key),
                'override' => $overrides->get($key),
            ];
        });

        return view('admin.email-templates.index', compact('templates'));
    }

    public function edit(string $key): View
    {
        $definition = EmailTemplateService::REGISTRY[$key] ?? abort(404);
        $override = EmailTemplate::where('key', $key)->first();

        // Preview with sample values so admins see the substitution live.
        $sample = collect($definition['placeholders'])
            ->map(fn ($desc, $name) => match ($name) {
                'client_name' => 'Jane Doe',
                'reference' => 'ONB-2026-0042',
                'organization_type' => 'Corporate',
                'question_label' => 'Proof of Business Address',
                default => strtoupper($name),
            })
            ->all();
        $preview = app(EmailTemplateService::class)->render($key, $sample);

        return view('admin.email-templates.edit', compact('key', 'definition', 'override', 'preview'));
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        EmailTemplateService::REGISTRY[$key] ?? abort(404);

        $validated = $request->validate([
            'subject' => 'required|string|max:500',
            'body' => 'required|string|max:10000',
        ]);

        EmailTemplate::updateOrCreate(
            ['key' => $key],
            $validated + ['updated_by' => Auth::guard('admin')->id()],
        );

        return redirect()->route('admin.email-templates.edit', $key)
            ->with('success', 'Template saved — new emails will use this wording.');
    }

    public function reset(string $key): RedirectResponse
    {
        EmailTemplateService::REGISTRY[$key] ?? abort(404);

        EmailTemplate::where('key', $key)->delete();

        return redirect()->route('admin.email-templates.edit', $key)
            ->with('success', 'Template reset to the default wording.');
    }
}
