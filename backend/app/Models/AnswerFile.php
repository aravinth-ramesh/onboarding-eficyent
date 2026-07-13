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
        'justification',
    ];

    protected $appends = ['url'];

    public function answer(): BelongsTo
    {
        return $this->belongsTo(UserAnswer::class, 'user_answer_id');
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
