<?php

namespace App\Exceptions;

use App\Models\UserAnswer;
use RuntimeException;

/**
 * A collaborator saved this answer after the client loaded it, so accepting
 * the write would silently discard their edit (EOP-97).
 */
class StaleAnswerException extends RuntimeException
{
    public function __construct(
        public readonly UserAnswer $answer,
        public readonly int $questionId,
    ) {
        parent::__construct('This answer was changed by someone else while you were editing.');
    }

    public function questionLabel(): string
    {
        return $this->answer->question->label ?? 'a question';
    }

    /** Who changed it, for a message the client can act on. */
    public function changedBy(): ?string
    {
        return $this->answer->editor->name
            ?? $this->answer->user->name
            ?? null;
    }

    public function changedAt(): ?string
    {
        return $this->answer->updated_at?->diffForHumans();
    }
}
