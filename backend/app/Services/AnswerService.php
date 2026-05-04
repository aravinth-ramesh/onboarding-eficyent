<?php

namespace App\Services;

use App\Models\AnswerAuditLog;
use App\Models\AnswerFile;
use App\Models\Question;
use App\Models\User;
use App\Models\UserAnswer;
use App\Models\UserOnboarding;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class AnswerService
{
    public function __construct(
        private FileUploadService $fileUploadService,
    ) {}

    /**
     * Save or update an answer, logging edits.
     */
    public function saveAnswer(
        User $user,
        UserOnboarding $onboarding,
        int $questionId,
        mixed $value,
        ?User $editedBy = null,
    ): UserAnswer {
        $editedBy = $editedBy ?? $user;

        // Normalize value for multi-select
        $normalizedValue = is_array($value) ? json_encode($value) : (string) $value;

        $existing = UserAnswer::where('user_id', $user->id)
            ->where('question_id', $questionId)
            ->where('user_onboarding_id', $onboarding->id)
            ->first();

        if ($existing) {
            $oldValue = $existing->value;

            // Only log if value actually changed
            if ($oldValue !== $normalizedValue) {
                AnswerAuditLog::create([
                    'user_answer_id' => $existing->id,
                    'question_id' => $questionId,
                    'user_id' => $user->id,
                    'edited_by' => $editedBy->id,
                    'old_value' => $oldValue,
                    'new_value' => $normalizedValue,
                    'edited_at' => now(),
                ]);

                $existing->update(['value' => $normalizedValue]);
            }

            return $existing;
        }

        return UserAnswer::create([
            'user_id' => $user->id,
            'question_id' => $questionId,
            'user_onboarding_id' => $onboarding->id,
            'value' => $normalizedValue,
        ]);
    }

    /**
     * Save a file-type answer: upload files to S3, store metadata in answer_files.
     * Previous files are audit-logged but NOT deleted from S3.
     *
     * @param  UploadedFile[]  $files
     */
    public function saveFileAnswer(
        User $user,
        UserOnboarding $onboarding,
        int $questionId,
        array $files,
        ?User $editedBy = null,
    ): UserAnswer {
        $editedBy = $editedBy ?? $user;

        return DB::transaction(function () use ($user, $onboarding, $questionId, $files, $editedBy) {
            // Upload all files first
            $uploadedMeta = $this->fileUploadService->uploadMultiple($files, $user->id);

            // Build new value summary (JSON array of file paths)
            $newPaths = array_column($uploadedMeta, 's3_path');
            $newValue = json_encode($newPaths);

            $existing = UserAnswer::where('user_id', $user->id)
                ->where('question_id', $questionId)
                ->where('user_onboarding_id', $onboarding->id)
                ->first();

            if ($existing) {
                // Audit log the old file data before replacing
                $oldFileData = $existing->files->map(fn (AnswerFile $f) => [
                    'original_filename' => $f->original_filename,
                    's3_path' => $f->s3_path,
                    'mime_type' => $f->mime_type,
                    'file_size' => $f->file_size,
                ])->toArray();

                AnswerAuditLog::create([
                    'user_answer_id' => $existing->id,
                    'question_id' => $questionId,
                    'user_id' => $user->id,
                    'edited_by' => $editedBy->id,
                    'old_value' => json_encode($oldFileData),
                    'new_value' => $newValue,
                    'edited_at' => now(),
                ]);

                // Remove old file records (NOT deleting from S3)
                $existing->files()->delete();

                // Update answer value
                $existing->update(['value' => $newValue]);

                $answer = $existing;
            } else {
                $answer = UserAnswer::create([
                    'user_id' => $user->id,
                    'question_id' => $questionId,
                    'user_onboarding_id' => $onboarding->id,
                    'value' => $newValue,
                ]);
            }

            // Create new file records
            foreach ($uploadedMeta as $meta) {
                AnswerFile::create([
                    'user_answer_id' => $answer->id,
                    'original_filename' => $meta['original_filename'],
                    's3_path' => $meta['s3_path'],
                    'mime_type' => $meta['mime_type'],
                    'file_size' => $meta['file_size'],
                    'disk' => $meta['disk'],
                ]);
            }

            return $answer->load('files');
        });
    }

    /**
     * Upload files for individual cells of a table-type answer and merge the
     * resulting metadata back into the answer's JSON value.
     *
     * Each entry must contain row_index, column_key, and an UploadedFile.
     */
    public function saveTableCellFiles(
        User $user,
        UserOnboarding $onboarding,
        int $questionId,
        array $entries,
        ?User $editedBy = null,
    ): UserAnswer {
        $editedBy = $editedBy ?? $user;

        return DB::transaction(function () use ($user, $onboarding, $questionId, $entries, $editedBy) {
            $existing = UserAnswer::where('user_id', $user->id)
                ->where('question_id', $questionId)
                ->where('user_onboarding_id', $onboarding->id)
                ->first();

            $rows = [];
            $oldValue = null;
            if ($existing) {
                $oldValue = $existing->value;
                $decoded = json_decode((string) $existing->value, true);
                if (is_array($decoded)) {
                    $rows = $decoded;
                }
            }

            foreach ($entries as $entry) {
                $rowIndex = (int) $entry['row_index'];
                $columnKey = (string) $entry['column_key'];
                /** @var UploadedFile $file */
                $file = $entry['file'];

                $meta = $this->fileUploadService->upload($file, $user->id);

                while (count($rows) <= $rowIndex) {
                    $rows[] = [];
                }
                if (! is_array($rows[$rowIndex])) {
                    $rows[$rowIndex] = [];
                }

                $rows[$rowIndex][$columnKey] = [
                    'path' => $meta['s3_path'],
                    'filename' => $meta['original_filename'],
                    'mime' => $meta['mime_type'],
                    'size' => $meta['file_size'],
                    'disk' => $meta['disk'],
                ];
            }

            $newValue = json_encode($rows);

            if ($existing) {
                if ($oldValue !== $newValue) {
                    AnswerAuditLog::create([
                        'user_answer_id' => $existing->id,
                        'question_id' => $questionId,
                        'user_id' => $user->id,
                        'edited_by' => $editedBy->id,
                        'old_value' => $oldValue,
                        'new_value' => $newValue,
                        'edited_at' => now(),
                    ]);
                    $existing->update(['value' => $newValue]);
                }
                return $existing;
            }

            return UserAnswer::create([
                'user_id' => $user->id,
                'question_id' => $questionId,
                'user_onboarding_id' => $onboarding->id,
                'value' => $newValue,
            ]);
        });
    }

    /**
     * Save multiple answers at once (non-file types only).
     */
    public function saveBulkAnswers(
        User $user,
        UserOnboarding $onboarding,
        array $answers,
        ?User $editedBy = null,
    ): array {
        $saved = [];

        foreach ($answers as $answer) {
            $saved[] = $this->saveAnswer(
                $user,
                $onboarding,
                $answer['question_id'],
                $answer['value'],
                $editedBy,
            );
        }

        return $saved;
    }
}
