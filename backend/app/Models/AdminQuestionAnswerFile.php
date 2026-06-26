<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AdminQuestionAnswerFile extends Model
{
    protected $fillable = [
        'admin_question_answer_id',
        'original_filename',
        's3_path',
        'mime_type',
        'file_size',
        'disk',
    ];

    protected $appends = ['url'];

    public function answer(): BelongsTo
    {
        return $this->belongsTo(AdminQuestionAnswer::class, 'admin_question_answer_id');
    }

    public function getUrlAttribute(): ?string
    {
        $disk = Storage::disk($this->disk);

        if ($this->disk === 's3') {
            return $disk->temporaryUrl($this->s3_path, now()->addMinutes(
                config('onboarding_uploads.url_expiry_minutes', 60)
            ));
        }

        return $disk->url($this->s3_path);
    }
}
