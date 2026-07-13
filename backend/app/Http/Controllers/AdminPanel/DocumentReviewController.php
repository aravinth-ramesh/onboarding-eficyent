<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Models\AnswerFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Review queue for documents the automatic validation could not fully clear:
 * unreadable/unknown files (needs_review) and justified overrides
 * (type_mismatch / expired / stale). The extracted-text excerpt is shown so
 * the rules dictionaries can be tuned against real uploads.
 */
class DocumentReviewController extends Controller
{
    private const ATTENTION_STATUSES = ['needs_review', 'type_mismatch', 'expired', 'stale'];

    public function index(Request $request): View
    {
        $status = $request->input('status');
        $showReviewed = $request->boolean('show_reviewed');

        $files = AnswerFile::with(['answer.question', 'answer.user', 'answer.onboarding', 'reviewer'])
            ->whereIn('validation_status', $status ? [$status] : self::ATTENTION_STATUSES)
            ->when(! $showReviewed, fn ($q) => $q->whereNull('reviewed_at'))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.document-reviews.index', [
            'files' => $files,
            'status' => $status,
            'showReviewed' => $showReviewed,
            'stats' => $this->stats(),
        ]);
    }

    public function approve(Request $request, AnswerFile $file): RedirectResponse
    {
        $file->update([
            'reviewed_at' => now(),
            'reviewed_by' => Auth::guard('admin')->id(),
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
        $recent = AnswerFile::where('created_at', '>=', now()->subDays(30))
            ->where('validation_status', '!=', 'skipped');

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

        return [
            'total' => $total,
            'passed' => $byStatus->get('passed', 0),
            'needs_review' => $byStatus->get('needs_review', 0),
            'justified' => $byStatus->get('type_mismatch', 0) + $byStatus->get('expired', 0) + $byStatus->get('stale', 0),
            'auto_pass_rate' => $total > 0 ? round($byStatus->get('passed', 0) / $total * 100) : null,
            'top_review_questions' => $topReviewQuestions,
        ];
    }
}
