<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Models\AnswerFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Review queue for documents the automatic validation could not fully clear:
 * unreadable/unknown files (needs_review) and justified overrides
 * (type_mismatch / expired / stale). The extracted-text excerpt is shown so
 * the rules dictionaries can be tuned against real uploads.
 */
class DocumentReviewController extends Controller
{
    /**
     * Statuses that put a document in front of a human.
     *
     * `skipped` belongs here: automated validation short-circuits to it
     * whenever the question carries no expected_document policy, and no seeder
     * or migration sets that policy — so on a real install every upload lands
     * as skipped and the queue reported "Nothing awaiting review" while
     * documents sat unreviewed (report item 15). A document the automation
     * never assessed is exactly one a reviewer must look at.
     */
    private const ATTENTION_STATUSES = ['needs_review', 'type_mismatch', 'expired', 'stale', 'skipped'];

    /** Documents the automation actually assessed, for the auto-pass rate. */
    private const ASSESSED_STATUSES = ['needs_review', 'type_mismatch', 'expired', 'stale', 'passed'];

    public function index(Request $request): View
    {
        $status = $request->input('status');
        $showReviewed = $request->boolean('show_reviewed');
        // "All" widens the queue to every uploaded document, not just the ones
        // automation flagged — so a reviewer can eyeball files that passed too.
        $showAll = $request->boolean('all');

        // Fetch the matching documents, then group them by application so each
        // application appears once with its documents underneath (EOP-93).
        /** @disregard */
        $admin = Auth::guard('admin')->user();

        $files = AnswerFile::with(['answer.question', 'answer.onboarding.user', 'reviewer'])
            // Same visibility rule the rest of the per-application surfaces use,
            // and the one serve() already enforces on the individual download.
            // It changes nothing today — every role that can reach this route
            // sees all applications by design, and the one role restricted to
            // assigned work lacks the ability to get here — but it means the
            // queue cannot start leaking if that ability is ever granted more
            // widely or a new restricted role is added.
            ->whereHas('answer.onboarding', fn ($q) => $q->visibleTo($admin))
            ->when(
                $status,
                fn ($q) => $q->where('validation_status', $status),
                // "All" must mean all: excluding `skipped` hid every document
                // whose question has no AI policy, which reads as "only
                // mandatory documents are shown" (EOP-105).
                fn ($q) => $showAll
                    ? $q
                    : $q->whereIn('validation_status', self::ATTENTION_STATUSES),
            )
            ->when(! $showReviewed, fn ($q) => $q->whereNull('reviewed_at'))
            ->latest('id')
            ->get()
            ->filter(fn ($f) => $f->answer && $f->answer->onboarding);

        $filesByOnboarding = $files->groupBy(fn ($f) => $f->answer->user_onboarding_id);

        // The applications, most-recent document first, paginated in memory
        // (the review queue is small — grouping needs the full set anyway).
        $orderedApplications = $filesByOnboarding
            ->map(fn ($group) => $group->first()->answer->onboarding)
            ->values();

        $perPage = 15;
        $page = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        $applications = new \Illuminate\Pagination\LengthAwarePaginator(
            $orderedApplications->forPage($page, $perPage)->values(),
            $orderedApplications->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('admin.document-reviews.index', [
            'applications' => $applications,
            'filesByOnboarding' => $filesByOnboarding,
            'status' => $status,
            'showReviewed' => $showReviewed,
            'showAll' => $showAll,
            'stats' => $this->stats(),
        ]);
    }

    /**
     * Stream an uploaded document to the admin. Serves inline by default so it
     * previews in a new tab rather than force-downloading; `?download=1` forces
     * the attachment (Download option). Uniform across local/public/s3 disks so
     * the disposition is ours to set, not the storage backend's (EOP-81).
     */
    public function serve(Request $request, AnswerFile $file): StreamedResponse
    {
        $onboarding = $file->answer?->onboarding;
        abort_unless($onboarding !== null, 404);
        abort_unless($onboarding->isVisibleTo(Auth::guard('admin')->user()), 403);

        $disk = Storage::disk($file->disk);
        abort_unless($disk->exists($file->s3_path), 404);

        return $disk->response(
            $file->s3_path,
            $file->original_filename,
            ['Content-Type' => $file->mime_type ?: 'application/octet-stream'],
            $request->boolean('download') ? 'attachment' : 'inline',
        );
    }

    public function approve(Request $request, AnswerFile $file): RedirectResponse
    {
        $file->update([
            'reviewed_at' => now(),
            'reviewed_by' => Auth::guard('admin')->id(),
            'review_decision' => 'verified',
        ]);

        return redirect()
            ->to($request->input('redirect_to', route('admin.document-reviews.index')))
            ->with('success', 'Document approved.');
    }

    /**
     * Tuning signals: where does automation stop short? High needs_review on
     * a question usually means its anchor-phrase dictionary needs new entries
     * (see config/document_validation.php).
     */
    private function stats(): array
    {
        // Counted separately rather than excluded outright: dropping skipped
        // made every tile read zero on an install where nothing carries an
        // expected_document policy (report item 15).
        $recent = AnswerFile::where('created_at', '>=', now()->subDays(30));

        $assessed = (clone $recent)->whereIn('validation_status', self::ASSESSED_STATUSES);
        $notAssessed = (clone $recent)->where('validation_status', 'skipped')->count();

        $byStatus = (clone $recent)->selectRaw('validation_status, count(*) as total')
            ->groupBy('validation_status')
            ->pluck('total', 'validation_status');

        $total = $byStatus->sum();

        $topReviewQuestions = AnswerFile::where('answer_files.created_at', '>=', now()->subDays(30))
            ->where('validation_status', 'needs_review')
            ->join('user_answers', 'user_answers.id', '=', 'answer_files.user_answer_id')
            ->join('questions', 'questions.id', '=', 'user_answers.question_id')
            ->selectRaw('questions.label, count(*) as total')
            ->groupBy('questions.label')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // The rate answers "of the documents automation judged, how many
        // passed" — counting never-assessed uploads in the denominator would
        // drag it down for work the automation never attempted.
        $assessedTotal = $assessed->count();

        return [
            'total' => $total,
            'passed' => $byStatus->get('passed', 0),
            'needs_review' => $byStatus->get('needs_review', 0),
            'not_assessed' => $notAssessed,
            'justified' => $byStatus->get('type_mismatch', 0) + $byStatus->get('expired', 0) + $byStatus->get('stale', 0),
            'auto_pass_rate' => $assessedTotal > 0 ? round($byStatus->get('passed', 0) / $assessedTotal * 100) : null,
            'top_review_questions' => $topReviewQuestions,
        ];
    }
}
