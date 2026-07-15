<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\AdminQuestion;
use App\Models\AdminQuestionAnswer;
use App\Models\AdminQuestionAnswerFile;
use App\Mail\ClientRespondedMail;
use App\Models\User;
use App\Models\UserAnswer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function __construct(
        private AnswerService $answerService,
        private FileUploadService $fileUploadService,
    ) {}

    /**
     * Tell the review team a client has responded. Goes to the application's
     * assigned reviewer when there is one (they asked for it), otherwise to
     * every active admin so a response is never left unseen. Never lets a
     * mail failure undo the client's submission.
     */
    private function notifyAdminsOfResponse(AdminNotification $notification, string $summary): void
    {
        try {
            $notification->loadMissing(['user', 'admin', 'userAnswer.question', 'adminQuestion']);

            $assignee = $notification->user?->activeOnboarding()?->assignee;
            $recipients = $assignee && $assignee->is_active
                ? collect([$assignee->email])
                : Admin::where('is_active', true)->pluck('email');

            foreach ($recipients->filter() as $email) {
                Mail::to($email)->queue(new ClientRespondedMail($notification, $summary));
            }
        } catch (\Throwable $e) {
            Log::warning('client response notification failed', ['error' => $e->getMessage()]);
        }
    }

    /** Short, email-safe description of what the client sent back. */
    private function summarize(mixed $value, array $files = []): string
    {
        if (! empty($files)) {
            $names = collect($files)->map(fn ($f) => is_string($f) ? $f : ($f->getClientOriginalName() ?? 'file'));

            return $names->count() . ' file(s) uploaded: ' . $names->implode(', ');
        }

        $text = is_array($value) ? implode(', ', $value) : (string) $value;

        return mb_strlen($text) > 300 ? mb_substr($text, 0, 300) . '…' : ($text !== '' ? $text : '—');
    }

    public function createChangeRequest(Admin $admin, UserAnswer $answer, string $message): AdminNotification
    {
        return AdminNotification::create([
            'user_id' => $answer->user_id,
            'admin_id' => $admin->id,
            'type' => 'change_request',
            'user_answer_id' => $answer->id,
            'message' => $message,
            'status' => 'pending',
        ]);
    }

    public function createNewQuestion(Admin $admin, User $user, array $questionData, string $message): AdminNotification
    {
        return DB::transaction(function () use ($admin, $user, $questionData, $message) {
            $question = AdminQuestion::create([
                'user_id' => $user->id,
                'admin_id' => $admin->id,
                'label' => $questionData['label'],
                'description' => $questionData['description'] ?? null,
                'type' => $questionData['type'],
                'options' => $questionData['options'] ?? null,
                'is_required' => $questionData['is_required'] ?? true,
                'placeholder' => $questionData['placeholder'] ?? null,
                'help_text' => $questionData['help_text'] ?? null,
            ]);

            return AdminNotification::create([
                'user_id' => $user->id,
                'admin_id' => $admin->id,
                'type' => 'new_question',
                'admin_question_id' => $question->id,
                'message' => $message,
                'status' => 'pending',
            ]);
        });
    }

    public function getUnreadCount(User $user): int
    {
        return AdminNotification::forUser($user->id)->unread()->count();
    }

    public function getUserNotifications(User $user, ?string $type = null): LengthAwarePaginator
    {
        $query = AdminNotification::forUser($user->id)
            ->with(['admin', 'userAnswer.question', 'adminQuestion'])
            ->orderByDesc('created_at');

        if ($type) {
            $query->where('type', $type);
        }

        return $query->paginate(20);
    }

    public function markAsRead(AdminNotification $notification): void
    {
        if (!$notification->read_at) {
            $notification->update(['read_at' => now()]);
        }
    }

    public function resolveChangeRequest(AdminNotification $notification, mixed $newValue): UserAnswer
    {
        $answer = $notification->userAnswer;
        $user = $notification->user;
        $onboarding = $answer->onboarding;

        $updatedAnswer = $this->answerService->saveAnswer(
            $user,
            $onboarding,
            $answer->question_id,
            $newValue,
        );

        $notification->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        $this->notifyAdminsOfResponse($notification, $this->summarize($newValue));

        return $updatedAnswer;
    }

    public function resolveChangeRequestWithFile(AdminNotification $notification, array $files): UserAnswer
    {
        $answer = $notification->userAnswer;
        $user = $notification->user;
        $onboarding = $answer->onboarding;

        $updatedAnswer = $this->answerService->saveFileAnswer(
            $user,
            $onboarding,
            $answer->question_id,
            $files,
        );

        $notification->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        $this->notifyAdminsOfResponse($notification, $this->summarize(null, $files));

        return $updatedAnswer;
    }

    public function resolveNewQuestion(AdminNotification $notification, mixed $value): AdminQuestionAnswer
    {
        $answer = AdminQuestionAnswer::create([
            'admin_question_id' => $notification->admin_question_id,
            'user_id' => $notification->user_id,
            'value' => is_array($value) ? json_encode($value) : (string) $value,
        ]);

        $notification->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        $this->notifyAdminsOfResponse($notification, $this->summarize($value));

        return $answer;
    }

    public function resolveNewQuestionWithFile(AdminNotification $notification, array $files): AdminQuestionAnswer
    {
        return DB::transaction(function () use ($notification, $files) {
            $uploadedMeta = $this->fileUploadService->uploadMultiple($files, $notification->user_id);
            $paths = array_column($uploadedMeta, 's3_path');

            $answer = AdminQuestionAnswer::create([
                'admin_question_id' => $notification->admin_question_id,
                'user_id' => $notification->user_id,
                'value' => json_encode($paths),
            ]);

            foreach ($uploadedMeta as $meta) {
                AdminQuestionAnswerFile::create([
                    'admin_question_answer_id' => $answer->id,
                    'original_filename' => $meta['original_filename'],
                    's3_path' => $meta['s3_path'],
                    'mime_type' => $meta['mime_type'],
                    'file_size' => $meta['file_size'],
                    'disk' => $meta['disk'],
                ]);
            }

            $notification->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);

            $this->notifyAdminsOfResponse($notification, $this->summarize(null, $files));

            return $answer->load('files');
        });
    }
}
