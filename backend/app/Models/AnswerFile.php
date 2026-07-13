<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AnswerFile extends Model
{
    protected $fillable = [
        'user_answer_id',
        'original_filename',
        's3_path',
        'mime_type',
        'file_size',
        'disk',
        'validation_status',
        'detected_type',
        'issue_date',
        'expiry_date',
        'validation_summary',
        'extracted_excerpt',
        'justification',
        'reviewed_at',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    protected $appends = ['url'];

    public function answer(): BelongsTo
    {
        return $this->belongsTo(UserAnswer::class, 'user_answer_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    /**
     * Generate a temporary signed URL for the file.
     */
    public function getUrlAttribute(): ?string
    {
        $disk = Storage::disk($this->disk);

        if ($this->disk === 's3') {
            return $disk->temporaryUrl($this->s3_path, now()->addMinutes(
                (int)config('onboarding_uploads.url_expiry_minutes', 60)
            ));
        }

        return $disk->url($this->s3_path);
    }
}
