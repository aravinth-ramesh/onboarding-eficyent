<?php

namespace App\Services;

use App\Exceptions\StaleAnswerException;
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
        ?string $expectedVersion = null,
    ): UserAnswer {
        $editedBy = $editedBy ?? $user;

        // Normalize value for multi-select
        $normalizedValue = is_array($value) ? json_encode($value) : (string) $value;

        // Keyed by onboarding + question (not user): collaborators edit the
        // application's shared answer, with edited_by capturing the actor.
        $existing = UserAnswer::where('question_id', $questionId)
            ->where('user_onboarding_id', $onboarding->id)
            ->first();

        if ($existing) {
            // Optimistic concurrency: the client sends back the version it
            // loaded. If someone else saved in the meantime, refuse rather
            // than silently overwriting their work (EOP-97).
            if ($expectedVersion !== null && self::versionOf($existing) !== $expectedVersion) {
                throw new StaleAnswerException($existing, $questionId);
            }

            $oldValue = $existing->value;

            if ($oldValue !== $normalizedValue) {
                // Only changes made after the application entered review are
                // audit-logged — draft edits aren't the team's concern.
                if ($onboarding->hasEnteredReview()) {
                    AnswerAuditLog::create([
                        'user_answer_id' => $existing->id,
                        'question_id' => $questionId,
                        'user_id' => $existing->user_id,
                        'edited_by' => $editedBy->id,
                        'old_value' => $oldValue,
                        'new_value' => $normalizedValue,
                        'edited_at' => now(),
                    ]);
                }

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
     * The version token a client echoes back to prove it edited the answer it
     * was shown.
     *
     * A hash of the stored value rather than updated_at: timestamps are only
     * second-precision on some drivers, so two edits within the same second
     * would compare equal and the conflict would slip through. Hashing the
     * value detects exactly what matters — the answer changed underneath us —
     * and needs no schema change. Two people saving the identical value is
     * not a conflict worth reporting.
     */
    public static function versionOf(?UserAnswer $answer): ?string
    {
        if (! $answer) {
            return null;
        }

        return substr(hash('sha256', (string) $answer->value), 0, 16);
    }

    /**
     * Save a file-type answer: upload files to S3, store metadata in answer_files.
     * Previous files are audit-logged but NOT deleted from S3.
     *
     * @param  UploadedFile[]  $files
     * @param  array  $fileValidations  per-file-index AI validation columns
     */
    public function saveFileAnswer(
        User $user,
        UserOnboarding $onboarding,
        int $questionId,
        array $files,
        ?User $editedBy = null,
        array $fileValidations = [],
    ): UserAnswer {
        $editedBy = $editedBy ?? $user;

        return DB::transaction(function () use ($user, $onboarding, $questionId, $files, $editedBy, $fileValidations) {
            // Upload all files first
            $uploadedMeta = $this->fileUploadService->uploadMultiple($files, $user->id);

            // Build new value summary (JSON array of file paths)
            $newPaths = array_column($uploadedMeta, 's3_path');
            $newValue = json_encode($newPaths);

            $existing = UserAnswer::where('question_id', $questionId)
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

                if ($onboarding->hasEnteredReview()) {
                    AnswerAuditLog::create([
                        'user_answer_id' => $existing->id,
                        'question_id' => $questionId,
                        'user_id' => $existing->user_id,
                        'edited_by' => $editedBy->id,
                        'old_value' => json_encode($oldFileData),
                        'new_value' => $newValue,
                        'edited_at' => now(),
                    ]);
                }

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
            foreach ($uploadedMeta as $index => $meta) {
                AnswerFile::create(array_merge([
                    'user_answer_id' => $answer->id,
                    'original_filename' => $meta['original_filename'],
                    's3_path' => $meta['s3_path'],
                    'mime_type' => $meta['mime_type'],
                    'file_size' => $meta['file_size'],
                    'disk' => $meta['disk'],
                ], $fileValidations[$index] ?? []));
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
            $existing = UserAnswer::where('question_id', $questionId)
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
                    'url' => $meta['url'] ?? '',
                    'path' => $meta['s3_path'] ?? '',
                    'filename' => $meta['original_filename'] ?? '',
                    'mime' => $meta['mime_type'] ?? '',
                    'size' => $meta['file_size'] ?? '',
                    'disk' => $meta['disk'] ?? '',
                ];
            }

            $newValue = json_encode($rows);

            if ($existing) {
                if ($oldValue !== $newValue) {
                    if ($onboarding->hasEnteredReview()) {
                        AnswerAuditLog::create([
                            'user_answer_id' => $existing->id,
                            'question_id' => $questionId,
                            'user_id' => $existing->user_id,
                            'edited_by' => $editedBy->id,
                            'old_value' => $oldValue,
                            'new_value' => $newValue,
                            'edited_at' => now(),
                        ]);
                    }
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
        // One transaction so a conflict partway through doesn't leave half the
        // group saved (EOP-97).
        return DB::transaction(function () use ($user, $onboarding, $answers, $editedBy) {
            $saved = [];

            foreach ($answers as $answer) {
                $saved[] = $this->saveAnswer(
                    $user,
                    $onboarding,
                    $answer['question_id'],
                    $answer['value'],
                    $editedBy,
                    $answer['version'] ?? null,
                );
            }

            // Keep the denormalised company name current as the form is filled.
            \App\Support\CompanyName::sync($onboarding);

            return $saved;
        });
    }
}
