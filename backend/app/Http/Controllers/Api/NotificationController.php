<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}

    public function index(): JsonResponse
    {
        /** @disregard */
        $user = auth()->user();

        $notifications = $this->notificationService->getUserNotifications($user);

        $data = $notifications->through(fn (AdminNotification $n) => $this->formatNotification($n));

        return response()->json($data);
    }

    public function unreadCount(): JsonResponse
    {
        /** @disregard */
        $user = auth()->user();

        return response()->json([
            'count' => $this->notificationService->getUnreadCount($user),
        ]);
    }

    public function show(AdminNotification $notification): JsonResponse
    {
        /** @disregard */
        $user = auth()->user();

        if ((int) $notification->user_id !== (int) $user->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $notification->load([
            'admin',
            'userAnswer.question',
            'userAnswer.files',
            'adminQuestion.answer.files',
        ]);

        // Mark as read
        $this->notificationService->markAsRead($notification);

        return response()->json([
            'data' => $this->formatNotificationDetail($notification),
        ]);
    }

    public function markAsRead(AdminNotification $notification): JsonResponse
    {
        /** @disregard */
        $user = auth()->user();

        if ((int) $notification->user_id !== (int) $user->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $this->notificationService->markAsRead($notification);

        return response()->json(['message' => 'Marked as read.']);
    }

    public function resolve(Request $request, AdminNotification $notification): JsonResponse
    {
        /** @disregard */
        $user = auth()->user();

        if ((int) $notification->user_id !== (int) $user->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($notification->status === 'resolved') {
            return response()->json(['message' => 'Already resolved.'], 422);
        }

        $request->validate([
            'value' => 'required',
        ]);

        if ($notification->type === 'change_request') {
            $this->notificationService->resolveChangeRequest($notification, $request->input('value'));
        } else {
            $this->notificationService->resolveNewQuestion($notification, $request->input('value'));
        }

        return response()->json(['message' => 'Response submitted successfully.']);
    }

    public function resolveWithFile(Request $request, AdminNotification $notification): JsonResponse
    {
        /** @disregard */
        $user = auth()->user();

        if ((int) $notification->user_id !== (int) $user->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($notification->status === 'resolved') {
            return response()->json(['message' => 'Already resolved.'], 422);
        }

        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'file|max:' . config('onboarding_uploads.max_file_size_kb', 5120),
        ]);

        $files = $request->file('files');

        if ($notification->type === 'change_request') {
            $this->notificationService->resolveChangeRequestWithFile($notification, $files);
        } else {
            $this->notificationService->resolveNewQuestionWithFile($notification, $files);
        }

        return response()->json(['message' => 'File(s) submitted successfully.']);
    }

    private function formatNotification(AdminNotification $notification): array
    {
        $data = [
            'id' => $notification->id,
            'type' => $notification->type,
            'message' => $notification->message,
            'status' => $notification->status,
            'read_at' => $notification->read_at,
            'resolved_at' => $notification->resolved_at,
            'created_at' => $notification->created_at,
        ];

        if ($notification->type === 'change_request' && $notification->userAnswer && $notification->userAnswer->question) {
            $data['question_label'] = $notification->userAnswer->question->label;
        } elseif ($notification->type === 'new_question' && $notification->adminQuestion) {
            $data['question_label'] = $notification->adminQuestion->label;
        }

        return $data;
    }

    private function formatNotificationDetail(AdminNotification $notification): array
    {
        $data = [
            'id' => $notification->id,
            'type' => $notification->type,
            'message' => $notification->message,
            'status' => $notification->status,
            'read_at' => $notification->read_at,
            'resolved_at' => $notification->resolved_at,
            'created_at' => $notification->created_at,
        ];

        if ($notification->type === 'change_request' && $notification->userAnswer) {
            $answer = $notification->userAnswer;
            $question = $answer->question;

            $data['question'] = [
                'id' => $question->id,
                'label' => $question->label,
                'description' => $question->description,
                'type' => $question->type,
                'options' => $question->options,
                'placeholder' => $question->placeholder,
                'help_text' => $question->help_text,
            ];
            $data['old_answer'] = $answer->value;

            if ($question->type === 'file' && $answer->files->count()) {
                $data['files'] = $answer->files->map(fn ($f) => [
                    'id' => $f->id,
                    'original_filename' => $f->original_filename,
                    'mime_type' => $f->mime_type,
                    'file_size' => $f->file_size,
                    'url' => $f->url,
                ])->values()->toArray();
            }
        } elseif ($notification->type === 'new_question' && $notification->adminQuestion) {
            $adminQ = $notification->adminQuestion;

            $data['question'] = [
                'id' => $adminQ->id,
                'label' => $adminQ->label,
                'description' => $adminQ->description,
                'type' => $adminQ->type,
                'options' => $adminQ->options,
                'is_required' => $adminQ->is_required,
                'placeholder' => $adminQ->placeholder,
                'help_text' => $adminQ->help_text,
            ];

            // Include existing answer if resolved
            if ($adminQ->answer) {
                $data['existing_answer'] = $adminQ->answer->value;
                if ($adminQ->type === 'file' && $adminQ->answer->files->count()) {
                    $data['files'] = $adminQ->answer->files->map(fn ($f) => [
                        'id' => $f->id,
                        'original_filename' => $f->original_filename,
                        'mime_type' => $f->mime_type,
                        'file_size' => $f->file_size,
                        'url' => $f->url,
                    ])->values()->toArray();
                }
            }
        }

        return $data;
    }
}
