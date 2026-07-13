<?php

namespace App\Services\DocumentIntelligence\Rules;

/**
 * Classifies extracted document text by scoring anchor phrases per type.
 *
 * Each phrase from config('document_validation.rules.anchors') is matched
 * once, case-insensitively, on word boundaries. Negative weights let sibling
 * documents (e.g. certificate vs articles of association) suppress each
 * other. Scores below classification.min_score yield 'other', which the
 * policy engine routes to human review instead of hard-rejecting.
 */
class DocumentClassifier
{
    /**
     * @return array{type: string, score: int, matches: string[]}
     */
    public function classify(string $text): array
    {
        $best = ['type' => 'other', 'score' => 0, 'matches' => []];

        foreach (config('document_validation.rules.anchors', []) as $type => $phrases) {
            $score = 0;
            $matches = [];

            foreach ($phrases as $phrase => $weight) {
                if ($this->contains($text, (string) $phrase)) {
                    $score += (int) $weight;
                    if ($weight > 0) {
                        $matches[] = (string) $phrase;
                    }
                }
            }

            if ($score > $best['score']) {
                $best = ['type' => $type, 'score' => $score, 'matches' => $matches];
            }
        }

        if ($best['score'] < (int) config('document_validation.rules.classification.min_score')) {
            return ['type' => 'other', 'score' => $best['score'], 'matches' => $best['matches']];
        }

        return $best;
    }

    public function isHighConfidence(int $score): bool
    {
        return $score >= (int) config('document_validation.rules.classification.high_confidence_score');
    }

    private function contains(string $text, string $phrase): bool
    {
        $pattern = '/\b' . preg_quote($phrase, '/') . '\b/i';

        return (bool) preg_match($pattern, $text);
    }
}
