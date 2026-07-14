<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin-customized override of one outgoing email's wording.
 * Absence of a row for a key means the code default applies.
 */
class EmailTemplate extends Model
{
    protected $fillable = [
        'key',
        'subject',
        'body',
        'updated_by',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by');
    }
}
